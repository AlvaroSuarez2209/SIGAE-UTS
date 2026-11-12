<?php

namespace Tests\Feature\Evidence;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\EvidenceType;
use App\Enums\RoleName;
use App\Livewire\Evidence\EvidenceWorkspace;
use App\Livewire\Reviews\ReviewShow;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Deliverable;
use App\Models\DeliverableReferenceFile;
use App\Models\Evidence;
use App\Models\EvidenceFile;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Punto 4 del cuadro de seguimiento: "un archivo anterior no disponible
 * no debe impedir adjuntar el soporte corregido. Sustitución permitida
 * en el nuevo borrador, conservando antecedentes." Complementa
 * ReopenedEvidenceFileGapTest (que ya cubre el diagnóstico original y el
 * caso base con el archivo presente) con el resto del checklist: archivo
 * anterior ausente, el límite por versión con ambas versiones a la vez,
 * "Quitar" en un archivo que sí es del borrador (nunca toca la versión
 * anterior ni un disk_path compartido), permisos, y la auditoría.
 */
class EvidenceFileReplacementAfterReopeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('local');
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', $role->value)->first());

        return $user;
    }

    /**
     * @return array{0: Evidence, 1: User, 2: User, 3: User, 4: EvidenceFile}
     */
    private function approvedEvidenceWithOneFile(int $maxFiles = 1): array
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $leader = $this->userWithRole(RoleName::Leader);
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $programUnit = ProgramUnit::factory()->create();

        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $programUnit->id,
        ]);
        Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => $programUnit->id,
            'activity_id' => null,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'allowed_evidence_types' => [EvidenceType::File->value],
            'allowed_file_types' => ['pdf'],
            'max_files' => $maxFiles,
            'max_file_size_mb' => 5,
        ]);
        $evidence = Evidence::factory()->create([
            'deliverable_id' => $deliverable->id,
            'user_id' => $teacher->id,
            'status' => EvidenceStatus::Pending,
        ]);

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [UploadedFile::fake()->create('informe.pdf', 50, 'application/pdf')])
            ->call('submit')
            ->assertSet('submissionError', '');

        $evidence->refresh();
        $originalFile = $evidence->currentVersion->files->first();

        Livewire::actingAs($leader)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->call('approve')
            ->assertHasNoErrors();

        $evidence->refresh();

        return [$evidence, $teacher, $leader, $admin, $originalFile];
    }

    private function reopen(Evidence $evidence, User $admin): void
    {
        Livewire::actingAs($admin)->test(ReviewShow::class, ['evidence' => $evidence])->call('reopen');
        $evidence->refresh();
    }

    // --- Archivo anterior ausente: subir reemplazo y reenviar funciona ---

    public function test_can_upload_the_replacement_and_resubmit_when_the_previous_file_is_missing_from_disk(): void
    {
        [$evidence, $teacher, , $admin, $originalFile] = $this->approvedEvidenceWithOneFile(maxFiles: 1);

        $this->reopen($evidence, $admin);

        Storage::disk('local')->delete($originalFile->disk_path);

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->set('newFiles', [UploadedFile::fake()->create('reemplazo.pdf', 50, 'application/pdf')])
            ->call('submit')
            ->assertSet('submissionError', '');

        $this->assertEquals(EvidenceStatus::Submitted, $evidence->fresh()->status);
        $this->assertEquals('reemplazo.pdf', $evidence->fresh()->currentVersion->files->first()->original_name);
    }

    // --- El límite cuenta por versión, no por evidencia completa ---

    /**
     * La versión anterior (aprobada) y el borrador en edición pueden
     * cada uno tener su propio archivo al mismo tiempo (2 en total para
     * la evidencia) sin que max_files=1 bloquee nada — el límite nunca
     * suma archivos de las dos versiones.
     */
    public function test_the_file_limit_counts_only_the_draft_version_even_while_the_previous_version_still_has_its_own_file(): void
    {
        [$evidence, $teacher, , $admin] = $this->approvedEvidenceWithOneFile(maxFiles: 1);

        $this->reopen($evidence, $admin);

        $workspace = Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->set('newFiles', [UploadedFile::fake()->create('reemplazo.pdf', 50, 'application/pdf')])
            ->call('saveDraft')
            ->assertHasNoErrors();

        $evidence->refresh();
        $this->assertCount(2, $evidence->versions);
        $this->assertCount(1, $evidence->currentVersion->files);

        // Un segundo archivo en el MISMO borrador sí debe bloquearse
        // (ese límite es real) — a diferencia del archivo de la versión
        // anterior, que nunca cuenta.
        $workspace->set('newFiles', [UploadedFile::fake()->create('otro.pdf', 50, 'application/pdf')])
            ->call('saveDraft')
            ->assertHasErrors('newFiles');
    }

    // --- "Quitar" en el borrador nunca toca la versión anterior ni un disk_path compartido ---

    public function test_removing_a_draft_file_never_touches_the_previous_version_or_a_shared_physical_file(): void
    {
        [$evidence, $teacher, , $admin, $originalFile] = $this->approvedEvidenceWithOneFile(maxFiles: 2);

        $this->reopen($evidence, $admin);

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->set('newFiles', [UploadedFile::fake()->create('reemplazo.pdf', 50, 'application/pdf')])
            ->call('saveDraft');

        $evidence->refresh();
        $draftFile = $evidence->currentVersion->files->first();
        $this->assertNotEquals($originalFile->id, $draftFile->id);

        // El archivo del borrador comparte disk_path con otra fila
        // ajena (simula lo que hace DeliverableForm al copiar desde una
        // plantilla) — no debe borrarse el archivo físico, solo la fila.
        DeliverableReferenceFile::create([
            'deliverable_id' => Deliverable::factory()->create()->id,
            'original_name' => $draftFile->original_name,
            'stored_name' => 'copia-'.$draftFile->stored_name,
            'disk_path' => $draftFile->disk_path,
            'mime_type' => $draftFile->mime_type,
            'size_bytes' => $draftFile->size_bytes,
            'uploaded_by' => $teacher->id,
        ]);

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->call('removeFile', $draftFile->id);

        $this->assertDatabaseMissing('evidence_files', ['id' => $draftFile->id]);
        Storage::disk('local')->assertExists($draftFile->disk_path);

        // La versión anterior (aprobada) sigue totalmente intacta.
        $this->assertDatabaseHas('evidence_files', ['id' => $originalFile->id]);
        Storage::disk('local')->assertExists($originalFile->disk_path);
    }

    // --- Permisos: solo quien ya puede editar hoy ---

    public function test_only_the_owning_teacher_can_remove_a_draft_file_not_the_leader_or_the_administrator(): void
    {
        [$evidence, $teacher, $leader, $admin] = $this->approvedEvidenceWithOneFile(maxFiles: 2);

        $this->reopen($evidence, $admin);

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->set('newFiles', [UploadedFile::fake()->create('reemplazo.pdf', 50, 'application/pdf')])
            ->call('saveDraft');

        $evidence->refresh();
        $draftFile = $evidence->currentVersion->files->first();

        $this->assertFalse($admin->can('update', $evidence));
        $this->assertFalse($leader->can('update', $evidence));
        $this->assertTrue($teacher->can('update', $evidence));

        Livewire::actingAs($leader)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->call('removeFile', $draftFile->id)
            ->assertForbidden();

        $this->assertDatabaseHas('evidence_files', ['id' => $draftFile->id]);
    }

    // --- La versión 1 conserva su archivo y su revisión ---

    public function test_the_first_version_keeps_its_original_file_and_review_after_the_whole_cycle(): void
    {
        [$evidence, $teacher, $leader, $admin, $originalFile] = $this->approvedEvidenceWithOneFile(maxFiles: 1);

        $firstVersion = $originalFile->version;
        $firstVersionId = $firstVersion->id;
        $firstReview = $firstVersion->reviews()->first();

        $this->reopen($evidence, $admin);

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->set('newFiles', [UploadedFile::fake()->create('reemplazo.pdf', 50, 'application/pdf')])
            ->call('submit')
            ->assertSet('submissionError', '');

        // La versión 1 — ya enviada y revisada antes de reabrir — sigue
        // con su archivo y su revisión exactamente como quedaron.
        $this->assertDatabaseHas('evidence_files', ['id' => $originalFile->id, 'evidence_version_id' => $firstVersionId]);
        $this->assertDatabaseHas('reviews', ['id' => $firstReview->id, 'evidence_version_id' => $firstVersionId]);
        $this->assertEquals('approved', $firstReview->fresh()->decision->value);

        $evidence->refresh();
        $this->assertNotEquals($firstVersionId, $evidence->current_version_id);
        $this->assertCount(2, $evidence->versions);
    }

    // --- Auditoría ---

    public function test_removing_a_draft_file_is_audited_with_the_evidence_version_and_file_name_only(): void
    {
        [$evidence, $teacher, , $admin] = $this->approvedEvidenceWithOneFile(maxFiles: 2);

        $this->reopen($evidence, $admin);

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->set('newFiles', [UploadedFile::fake()->create('reemplazo.pdf', 50, 'application/pdf')])
            ->call('saveDraft');

        $evidence->refresh();
        $draftFile = $evidence->currentVersion->files->first();
        $versionId = $draftFile->evidence_version_id;

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->call('removeFile', $draftFile->id);

        $log = AuditLog::where('action', 'evidence_file_removed')
            ->where('auditable_type', Evidence::class)
            ->where('auditable_id', $evidence->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($teacher->id, $log->user_id);
        $this->assertSame($versionId, $log->metadata['version_id']);
        $this->assertSame('reemplazo.pdf', $log->metadata['file_name']);
        // Sin contenido: solo las 2 claves esperadas en la metadata.
        $this->assertEqualsCanonicalizing(['version_id', 'file_name'], array_keys($log->metadata));
    }
}
