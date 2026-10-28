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
use App\Models\Deliverable;
use App\Models\Evidence;
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
 * Diagnóstico de la directora, punto 4 de la revisión de perfil
 * Administrador: evidencia 24, reabierta de Aprobado a Requiere
 * ajustes. Como Administrador vio el archivo como "no disponible",
 * ocupando el máximo permitido, sin ninguna forma de retirarlo.
 *
 * SOLO DIAGNÓSTICO: esta prueba reproduce el flujo completo
 * (reapertura -> el docente abre el borrador -> intenta subir un
 * archivo -> intenta reenviar) y documenta el comportamiento actual,
 * confirmado como un hueco real. No se aplica ningún cambio todavía —
 * pendiente de confirmación sobre la corrección mínima propuesta.
 *
 * Causa exacta:
 * - ReviewShow::reopen() solo cambia el status a NeedsAdjustment; NUNCA
 *   toca current_version_id. La versión aprobada (con su archivo)
 *   sigue siendo la "actual" hasta que el docente haga su primera
 *   edición real.
 * - Evidence::startOrGetDraftVersion() sí crea una versión nueva (con
 *   cero archivos) la primera vez que se llama — pero
 *   EvidenceWorkspace::saveDraft() calcula el conteo de archivos
 *   existentes ANTES de llamarla (contra la versión vieja, todavía
 *   "actual"), no después (contra la versión nueva que de verdad
 *   recibiría el archivo). Con max_files ya alcanzado en la versión
 *   vieja, cualquier intento de adjuntar un archivo nuevo se rechaza
 *   de inmediato, sin que la versión nueva llegue a crearse.
 * - evidence-workspace.blade.php solo renderiza el botón "Quitar"
 *   (<x-evidence-file-row :removable="...">) cuando existsOnDisk() es
 *   true — cuando es false ("Archivo no disponible"), no hay ningún
 *   control para retirarlo, ni para el propio docente (único rol que
 *   EvidencePolicy::update() autoriza) ni para Administrador (que de
 *   todas formas no pasaría esa policy, aunque el botón existiera).
 */
class ReopenedEvidenceFileGapTest extends TestCase
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

        // El docente envía con un archivo real.
        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [UploadedFile::fake()->create('informe.pdf', 50, 'application/pdf')])
            ->call('submit')
            ->assertSet('submissionError', '');

        $evidence->refresh();
        $originalVersionId = $evidence->current_version_id;
        $originalFile = $evidence->currentVersion->files->first();

        // El líder aprueba.
        Livewire::actingAs($leader)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->call('approve')
            ->assertHasNoErrors();

        $evidence->refresh();
        $this->assertEquals(EvidenceStatus::Approved, $evidence->status);

        return [$evidence, $teacher, $admin, $originalVersionId, $originalFile];
    }

    public function test_reopening_leaves_the_approved_version_as_current_until_the_teacher_edits(): void
    {
        [$evidence, , $admin, $originalVersionId] = $this->approvedEvidenceWithOneFile();

        Livewire::actingAs($admin)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->call('reopen')
            ->assertHasNoErrors();

        $evidence->refresh();
        $this->assertEquals(EvidenceStatus::NeedsAdjustment, $evidence->status);

        // (a) Respuesta al diagnóstico: NO hereda, pero tampoco arranca de
        // cero de inmediato — current_version_id sigue siendo la versión
        // ya aprobada (con su archivo) hasta la primera edición real.
        $this->assertEquals($originalVersionId, $evidence->current_version_id);
    }

    /**
     * (b) Respuesta al diagnóstico: el límite SÍ pretende ser por
     * versión (startOrGetDraftVersion() crea una con cero archivos),
     * pero el conteo en saveDraft() se calcula contra la versión vieja
     * todavía vigente — así que, en la práctica, bloquea como si fuera
     * acumulado entre versiones mientras el docente no haya hecho
     * ninguna edición todavía.
     */
    public function test_teacher_cannot_attach_a_replacement_file_after_reopening_when_at_the_limit(): void
    {
        [$evidence, $teacher, $admin, $originalVersionId] = $this->approvedEvidenceWithOneFile(maxFiles: 1);

        Livewire::actingAs($admin)->test(ReviewShow::class, ['evidence' => $evidence])->call('reopen');
        $evidence->refresh();

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [UploadedFile::fake()->create('nuevo.pdf', 50, 'application/pdf')])
            ->call('saveDraft')
            ->assertHasErrors('newFiles');

        // Ninguna versión nueva llegó a crearse: el rechazo ocurre antes
        // de llamar a startOrGetDraftVersion().
        $this->assertEquals($originalVersionId, $evidence->fresh()->current_version_id);
    }

    /**
     * (c) Respuesta al diagnóstico: no existe ninguna acción en la UI
     * para retirar un archivo "no disponible" — ni para el docente
     * (único rol autorizado por EvidencePolicy::update(), ver más
     * abajo) ni, con más razón, para Administrador.
     */
    public function test_no_remove_control_is_rendered_for_a_file_marked_as_unavailable(): void
    {
        [$evidence, $teacher, $admin, , $originalFile] = $this->approvedEvidenceWithOneFile();

        Livewire::actingAs($admin)->test(ReviewShow::class, ['evidence' => $evidence])->call('reopen');
        $evidence->refresh();

        // Simula el disco efímero de Render: la fila sigue en la base,
        // pero el archivo físico ya no existe.
        Storage::disk('local')->delete($originalFile->disk_path);

        $html = Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->html();

        $this->assertStringContainsString('Archivo no disponible', $html);
        $this->assertStringNotContainsString('Quitar', $html);

        // Confirma además que, aunque hubiera un botón, Administrador no
        // podría usarlo: removeFile() exige la policy 'update', exclusiva
        // del propio docente.
        $this->assertFalse($admin->can('update', $evidence));
        $this->assertTrue($teacher->can('update', $evidence));
    }

    /**
     * (d) Respuesta al diagnóstico: con max_files=1 y solo tipo "file"
     * permitido, el docente queda completamente atascado. No puede
     * reemplazar el archivo (bloqueado por el límite contra la versión
     * vieja, antes de llegar a crear una nueva) ni retirar el que ya no
     * existe (sin ningún control en la pantalla). Si de todas formas
     * reintenta sin ningún archivo seleccionado, saveDraft() SÍ llega a
     * startOrGetDraftVersion() esta vez (nada la detiene antes, al no
     * haber archivos que contar) y crea una versión nueva vacía — pero
     * justo por estar vacía, submit() la rechaza por falta de contenido
     * mínimo. De cualquiera de las dos formas, nunca llega a reenviar.
     */
    public function test_teacher_is_stuck_and_cannot_resubmit_when_the_only_allowed_type_is_file_and_the_limit_is_reached(): void
    {
        [$evidence, $teacher, $admin] = $this->approvedEvidenceWithOneFile(maxFiles: 1);

        Livewire::actingAs($admin)->test(ReviewShow::class, ['evidence' => $evidence])->call('reopen');
        $evidence->refresh();

        $workspace = Livewire::actingAs($teacher)->test(EvidenceWorkspace::class, ['evidence' => $evidence]);

        // Intenta adjuntar un reemplazo: bloqueado.
        $workspace->set('newFiles', [UploadedFile::fake()->create('nuevo.pdf', 50, 'application/pdf')])
            ->call('saveDraft')
            ->assertHasErrors('newFiles');

        // Sin ningún archivo nuevo adjunto y sin poder retirar el
        // anterior, intentar reenviar falla por falta de contenido
        // válido (allowed_evidence_types solo admite "file").
        $workspace->set('newFiles', [])
            ->call('submit');

        $this->assertNotEquals(EvidenceStatus::Submitted, $evidence->fresh()->status);
    }
}
