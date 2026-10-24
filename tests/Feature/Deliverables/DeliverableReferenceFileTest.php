<?php

namespace Tests\Feature\Deliverables;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceType;
use App\Enums\RoleName;
use App\Livewire\Deliverables\DeliverableForm;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Deliverable;
use App\Models\DeliverableReferenceFile;
use App\Models\Evidence;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * RF-038: "El sistema deberá permitir adjuntar una plantilla, instructivo
 * o formato de referencia al entregable." Cubre la carga/validación desde
 * el asistente (DeliverableForm), la descarga protegida por
 * DeliverableReferenceFilePolicy (sin URL pública), y que adjuntar/quitar
 * quedan en la bitácora sin borrar nada físicamente.
 */
class DeliverableReferenceFileTest extends TestCase
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

    private function baseFormState(): array
    {
        return [
            'name' => 'Entregable de prueba',
            'periodicity_type' => 'single',
            'opens_at' => now()->toDateTimeString(),
            'due_at' => now()->addWeek()->toDateTimeString(),
            'allowed_evidence_types' => [EvidenceType::File->value],
        ];
    }

    /**
     * Entregable publicado con un docente destinatario real (con su
     * Evidence ya creada, como en la app real) y un líder cuyo liderazgo
     * sí (o no, según $leaderInScope) cubre esa actividad/programa — el
     * mismo criterio de ámbito que ya usa el resto del sistema.
     *
     * @return array{0: Deliverable, 1: User, 2: User} [$deliverable, $teacher, $leader]
     */
    private function publishedDeliverableWithRecipient(bool $leaderInScope = true): array
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $leader = $this->userWithRole(RoleName::Leader);
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
            'activity_id' => null,
            'program_unit_id' => $leaderInScope ? $programUnit->id : ProgramUnit::factory()->create()->id,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ]);

        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
        ]);
        $deliverable->recipients()->attach($teacher->id);
        Evidence::factory()->create(['deliverable_id' => $deliverable->id, 'user_id' => $teacher->id]);

        return [$deliverable, $teacher, $leader];
    }

    // --- Carga desde el asistente ---

    public function test_a_valid_reference_file_can_be_attached_while_creating_a_deliverable(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        TeacherAssignment::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activity->id]);

        $file = UploadedFile::fake()->create('instructivo.pdf', 500, 'application/pdf');

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->set('academic_period_id', $period->id)
            ->set('scope_type', 'activity')
            ->set('activity_id', $activity->id)
            ->set($this->baseFormState())
            ->set('newReferenceFiles', [$file])
            ->assertHasNoErrors('newReferenceFiles')
            ->call('saveAsDraft');

        $deliverable = Deliverable::where('name', 'Entregable de prueba')->first();
        $this->assertNotNull($deliverable);

        $referenceFile = $deliverable->referenceFiles()->first();
        $this->assertNotNull($referenceFile);
        $this->assertSame('instructivo.pdf', $referenceFile->original_name);
        $this->assertTrue($referenceFile->is_active);
        $this->assertNotSame('instructivo.pdf', $referenceFile->stored_name);
        Storage::disk('local')->assertExists($referenceFile->disk_path);
    }

    public function test_an_invalid_extension_is_rejected(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $file = UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload');

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->set('newReferenceFiles', [$file])
            ->assertHasErrors('newReferenceFiles')
            ->assertSet('newReferenceFiles', []);
    }

    public function test_a_file_exceeding_the_max_size_is_rejected(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $file = UploadedFile::fake()->create('grande.pdf', (DeliverableReferenceFile::MAX_FILE_SIZE_MB + 1) * 1024, 'application/pdf');

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->set('newReferenceFiles', [$file])
            ->assertHasErrors('newReferenceFiles')
            ->assertSet('newReferenceFiles', []);
    }

    public function test_cannot_exceed_the_max_reference_files_limit(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $files = collect(range(1, DeliverableReferenceFile::MAX_FILES + 1))
            ->map(fn ($n) => UploadedFile::fake()->create("doc{$n}.pdf", 100, 'application/pdf'))
            ->all();

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->set('newReferenceFiles', $files)
            ->assertHasErrors('newReferenceFiles')
            ->assertSet('newReferenceFiles', fn ($kept) => count($kept) === DeliverableReferenceFile::MAX_FILES);
    }

    // --- Descarga: roles autorizados ---

    public function test_the_assigned_teacher_can_download_the_reference_file(): void
    {
        [$deliverable, $teacher] = $this->publishedDeliverableWithRecipient();
        $file = $deliverable->attachReferenceFile(UploadedFile::fake()->create('plantilla.pdf', 100, 'application/pdf'), $teacher);

        $this->actingAs($teacher)
            ->get(route('deliverable-reference-files.download', $file))
            ->assertOk();
    }

    public function test_leader_within_scope_can_download_the_reference_file(): void
    {
        [$deliverable, , $leader] = $this->publishedDeliverableWithRecipient(leaderInScope: true);
        $file = $deliverable->attachReferenceFile(UploadedFile::fake()->create('plantilla.pdf', 100, 'application/pdf'), $leader);

        $this->actingAs($leader)
            ->get(route('deliverable-reference-files.download', $file))
            ->assertOk();
    }

    public function test_coordination_can_download_the_reference_file(): void
    {
        [$deliverable] = $this->publishedDeliverableWithRecipient();
        $coordination = $this->userWithRole(RoleName::Coordination);
        $file = $deliverable->attachReferenceFile(UploadedFile::fake()->create('plantilla.pdf', 100, 'application/pdf'), $coordination);

        $this->actingAs($coordination)
            ->get(route('deliverable-reference-files.download', $file))
            ->assertOk();
    }

    public function test_administrator_can_download_the_reference_file(): void
    {
        [$deliverable] = $this->publishedDeliverableWithRecipient();
        $admin = $this->userWithRole(RoleName::Administrator);
        $file = $deliverable->attachReferenceFile(UploadedFile::fake()->create('plantilla.pdf', 100, 'application/pdf'), $admin);

        $this->actingAs($admin)
            ->get(route('deliverable-reference-files.download', $file))
            ->assertOk();
    }

    public function test_auditor_can_download_the_reference_file(): void
    {
        [$deliverable] = $this->publishedDeliverableWithRecipient();
        $auditor = $this->userWithRole(RoleName::Auditor);
        $file = $deliverable->attachReferenceFile(UploadedFile::fake()->create('plantilla.pdf', 100, 'application/pdf'), $auditor);

        $this->actingAs($auditor)
            ->get(route('deliverable-reference-files.download', $file))
            ->assertOk();
    }

    // --- Descarga: denegada ---

    public function test_an_unassigned_teacher_cannot_download_the_reference_file(): void
    {
        [$deliverable, $teacher] = $this->publishedDeliverableWithRecipient();
        $file = $deliverable->attachReferenceFile(UploadedFile::fake()->create('plantilla.pdf', 100, 'application/pdf'), $teacher);

        $otherTeacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($otherTeacher)
            ->get(route('deliverable-reference-files.download', $file))
            ->assertForbidden();
    }

    public function test_leader_outside_scope_cannot_download_the_reference_file(): void
    {
        [$deliverable, , $leader] = $this->publishedDeliverableWithRecipient(leaderInScope: false);
        $file = $deliverable->attachReferenceFile(UploadedFile::fake()->create('plantilla.pdf', 100, 'application/pdf'), $leader);

        $this->actingAs($leader)
            ->get(route('deliverable-reference-files.download', $file))
            ->assertForbidden();
    }

    public function test_the_file_is_not_reachable_through_a_public_url(): void
    {
        [$deliverable, $teacher] = $this->publishedDeliverableWithRecipient();
        $file = $deliverable->attachReferenceFile(UploadedFile::fake()->create('plantilla.pdf', 100, 'application/pdf'), $teacher);

        // Sin sesión (nunca se llamó actingAs), la única ruta que sirve el
        // archivo redirige a login en vez de entregarlo.
        $this->get(route('deliverable-reference-files.download', $file))
            ->assertRedirect(route('login'));

        // El disco 'local' (storage/app/private) no tiene configurada
        // ninguna 'url' propia — a diferencia del disco 'public', no
        // comparte raíz con el único symlink público del proyecto
        // (public/storage -> storage/app/public, ver config/filesystems.php
        // 'links'). No existe ninguna URL pública posible para este archivo.
        $this->assertArrayNotHasKey('url', config('filesystems.disks.local'));
    }

    // --- Auditoría y "quitar no borra" ---

    public function test_attaching_a_reference_file_is_audited(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        TeacherAssignment::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activity->id]);

        $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activity->id]);

        // AuditLog::record() toma el actor de auth()->id(), nunca del
        // parámetro $actor de attachReferenceFile() (ese solo alimenta
        // uploaded_by) — mismo criterio que el resto del proyecto
        // (Evidence::markExempt()). actingAs() refleja cómo se llega aquí
        // de verdad: siempre dentro de una sesión autenticada.
        $this->actingAs($admin);
        $file = $deliverable->attachReferenceFile(UploadedFile::fake()->create('instructivo.pdf', 100, 'application/pdf'), $admin);

        $log = AuditLog::where('action', 'deliverable_reference_file_attached')
            ->where('auditable_type', Deliverable::class)
            ->where('auditable_id', $deliverable->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('instructivo.pdf', $log->metadata['file_name']);
        $this->assertSame($file->id, $log->metadata['file_id']);
    }

    public function test_removing_a_reference_file_is_audited_and_keeps_the_record_and_the_physical_file(): void
    {
        [$deliverable, $teacher] = $this->publishedDeliverableWithRecipient();
        $admin = $this->userWithRole(RoleName::Administrator);
        $file = $deliverable->attachReferenceFile(UploadedFile::fake()->create('plantilla.pdf', 100, 'application/pdf'), $teacher);

        $this->actingAs($admin);
        $deliverable->removeReferenceFile($file, $admin);

        $log = AuditLog::where('action', 'deliverable_reference_file_removed')
            ->where('auditable_type', Deliverable::class)
            ->where('auditable_id', $deliverable->id)
            ->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('plantilla.pdf', $log->metadata['file_name']);

        // "Quitar" nunca borra: la fila sigue existiendo (solo is_active
        // en false) y el archivo real sigue en disco.
        $file->refresh();
        $this->assertFalse($file->is_active);
        $this->assertDatabaseHas('deliverable_reference_files', ['id' => $file->id]);
        Storage::disk('local')->assertExists($file->disk_path);
    }

    public function test_a_removed_reference_file_can_no_longer_be_downloaded_by_anyone(): void
    {
        [$deliverable, $teacher] = $this->publishedDeliverableWithRecipient();
        $admin = $this->userWithRole(RoleName::Administrator);
        $file = $deliverable->attachReferenceFile(UploadedFile::fake()->create('plantilla.pdf', 100, 'application/pdf'), $teacher);
        $deliverable->removeReferenceFile($file, $admin);

        $this->actingAs($admin)
            ->get(route('deliverable-reference-files.download', $file))
            ->assertForbidden();
    }

    public function test_removing_an_existing_reference_file_through_the_form_takes_effect_immediately(): void
    {
        [$deliverable, $teacher] = $this->publishedDeliverableWithRecipient();
        $admin = $this->userWithRole(RoleName::Administrator);
        $file = $deliverable->attachReferenceFile(UploadedFile::fake()->create('plantilla.pdf', 100, 'application/pdf'), $teacher);

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class, ['deliverable' => $deliverable])
            ->call('removeReferenceFile', $file);

        $this->assertFalse($file->fresh()->is_active);
    }

    // --- Diseño: mismo componente de arrastrar-y-soltar que la carga de evidencia ---

    /**
     * El campo debe usar <x-file-dropzone> (resources/views/components/
     * file-dropzone.blade.php) — el mismo componente ya usado por
     * EvidenceWorkspace y TeacherImportWizard — no el <input type="file">
     * nativo que tenía antes.
     */
    public function test_the_reference_files_field_uses_the_shared_dropzone_component(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $html = Livewire::actingAs($admin)->test(DeliverableForm::class)->html();

        $this->assertStringContainsString('Arrastra los archivos aquí', $html);
        $this->assertStringContainsString('haz clic para seleccionar', $html);
        $this->assertStringContainsString('id="new-reference-files-dropzone"', $html);
        $this->assertStringNotContainsString('wire:model="newReferenceFiles" multiple class="field-input"', $html);
    }

    /**
     * Mismo criterio visual que EvidenceWorkspace::$newFiles: nombre +
     * tamaño legible, no la etiqueta genérica "(pendiente de guardar)" que
     * tenía antes.
     */
    public function test_selected_but_unsaved_reference_files_show_their_name_and_size(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $file = UploadedFile::fake()->create('instructivo.pdf', 200, 'application/pdf');

        $html = Livewire::actingAs($admin)->test(DeliverableForm::class)
            ->set('newReferenceFiles', [$file])
            ->html();

        $this->assertStringContainsString('instructivo.pdf', $html);
        $this->assertStringContainsString(\App\Models\EvidenceFile::formatReadableSize($file->getSize()), $html);
        $this->assertStringNotContainsString('pendiente de guardar', $html);
    }

    /**
     * Mismo criterio que EvidenceWorkspace cuando ya se alcanzó
     * $deliverable->max_files: el dropzone desaparece y se explica por qué,
     * en vez de ocultar el campo en silencio.
     */
    public function test_the_dropzone_is_replaced_by_a_limit_message_once_the_maximum_is_reached(): void
    {
        [$deliverable, $teacher] = $this->publishedDeliverableWithRecipient();
        $admin = $this->userWithRole(RoleName::Administrator);

        for ($i = 0; $i < DeliverableReferenceFile::MAX_FILES; $i++) {
            $deliverable->attachReferenceFile(UploadedFile::fake()->create("doc{$i}.pdf", 100, 'application/pdf'), $admin);
        }

        $html = Livewire::actingAs($admin)
            ->test(DeliverableForm::class, ['deliverable' => $deliverable])
            ->html();

        $this->assertStringContainsString('Ya alcanzaste el máximo de '.DeliverableReferenceFile::MAX_FILES, $html);
        $this->assertStringNotContainsString('Arrastra los archivos aquí', $html);
    }

    /**
     * Los archivos ya guardados deben seguir visibles y descargables en
     * edición, con el mismo estilo (ícono + nombre + tamaño) que el resto
     * de filas de archivo de la aplicación.
     */
    public function test_already_saved_reference_files_remain_visible_and_downloadable_with_the_new_style(): void
    {
        [$deliverable, $teacher] = $this->publishedDeliverableWithRecipient();
        $admin = $this->userWithRole(RoleName::Administrator);
        $file = $deliverable->attachReferenceFile(UploadedFile::fake()->create('plantilla.pdf', 100, 'application/pdf'), $teacher);

        $html = Livewire::actingAs($admin)
            ->test(DeliverableForm::class, ['deliverable' => $deliverable])
            ->html();

        $this->assertStringContainsString('plantilla.pdf', $html);
        $this->assertStringContainsString(route('deliverable-reference-files.download', $file), $html);
        $this->assertStringContainsString('removeReferenceFile('.$file->id.')', $html);
    }

    /**
     * Mismo criterio que EvidenceWorkspaceTest::test_downloading_an_evidence_file_whose_physical_file_is_missing_returns_a_controlled_404()
     * — el disco de Render es efímero, el registro puede sobrevivir a su
     * archivo físico tras un redeploy. Debe ser un 404 controlado, no el
     * 500 crudo de Flysystem.
     */
    public function test_downloading_a_reference_file_whose_physical_file_is_missing_returns_a_controlled_404(): void
    {
        Log::spy();

        [$deliverable, $teacher] = $this->publishedDeliverableWithRecipient();
        $admin = $this->userWithRole(RoleName::Administrator);
        $file = $deliverable->attachReferenceFile(UploadedFile::fake()->create('plantilla.pdf', 100, 'application/pdf'), $teacher);

        Storage::disk('local')->delete($file->disk_path);

        $response = $this->actingAs($admin)
            ->get(route('deliverable-reference-files.download', $file));

        $response->assertNotFound();
        $this->assertSame(
            'El archivo ya no está disponible. Es posible que se haya perdido tras un reinicio del servidor.',
            $response->exception->getMessage()
        );

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn ($message, $context) => $context === ['deliverable_reference_file_id' => $file->id]);
    }
}
