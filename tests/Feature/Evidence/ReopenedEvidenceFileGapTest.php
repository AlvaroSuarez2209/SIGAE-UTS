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
 * Causa exacta:
 * - ReviewShow::reopen() solo cambia el status a NeedsAdjustment; NUNCA
 *   toca current_version_id. La versión aprobada (con su archivo)
 *   sigue siendo la "actual" hasta que el docente haga su primera
 *   edición real — esto nunca cambió (es información correcta, no el
 *   bug) y sigue probado en el primer test de esta clase.
 * - CORREGIDO: EvidenceWorkspace::saveDraft() contaba los archivos
 *   existentes contra currentVersion directamente, que tras una
 *   reapertura sigue siendo la versión vieja ya aprobada. Ahora cuenta
 *   contra Evidence::draftVersionOrNull() (null si currentVersion ya
 *   se envió) — así el límite aplica contra la versión en edición que
 *   startOrGetDraftVersion() crearía, no contra la vieja.
 * - Punto 4 del cuadro de seguimiento (vuelta a revisar este mismo
 *   diagnóstico): la solución anterior había ido demasiado lejos —
 *   mostraba "Quitar" en el archivo "no disponible" de la versión YA
 *   APROBADA y dejaba que removeFile() lo borrara de ahí mismo,
 *   corrompiendo el historial ("Historial de versiones" perdía ese
 *   archivo para siempre). CORREGIDO otra vez, de la forma correcta:
 *   $currentFiles en la vista y removeFile() ahora solo reconocen
 *   archivos de Evidence::draftVersionOrNull() — nunca de una versión
 *   ya enviada. El archivo de la versión aprobada sigue totalmente
 *   visible (de solo lectura) en "Historial de versiones", que ahora
 *   aparece con solo una versión si esa versión ya se envió (antes
 *   exigía más de una). El cupo nunca necesitó "liberarse" quitando el
 *   archivo viejo: el nuevo borrador siempre arranca vacío, así que un
 *   solo archivo de reemplazo ya cabe en max_files=1 sin tocar nada de
 *   la versión anterior.
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
     * (b) CORREGIDO: el límite ahora se cuenta contra
     * Evidence::draftVersionOrNull() (null tras una reapertura, porque
     * la versión actual ya se envió), no contra currentVersion
     * directamente — así que un reemplazo sí cabe, y crea la versión
     * nueva que antes nunca llegaba a crearse.
     */
    public function test_teacher_can_attach_a_replacement_file_after_reopening_even_at_the_old_limit(): void
    {
        [$evidence, $teacher, $admin, $originalVersionId] = $this->approvedEvidenceWithOneFile(maxFiles: 1);

        Livewire::actingAs($admin)->test(ReviewShow::class, ['evidence' => $evidence])->call('reopen');
        $evidence->refresh();

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [UploadedFile::fake()->create('nuevo.pdf', 50, 'application/pdf')])
            ->call('saveDraft')
            ->assertHasNoErrors();

        $evidence->refresh();
        $this->assertNotEquals($originalVersionId, $evidence->current_version_id);
        $this->assertCount(1, $evidence->currentVersion->files);
        $this->assertEquals('nuevo.pdf', $evidence->currentVersion->files->first()->original_name);
    }

    /**
     * Punto 4 del cuadro de seguimiento: el archivo "no disponible" de
     * la versión YA APROBADA se ve (de solo lectura, en "Historial de
     * versiones") pero nunca lleva "Quitar" — ni la vista lo ofrece ni
     * removeFile() lo acepta si se llama directo con su id (defensa en
     * profundidad, nunca confiar solo en que la vista no muestre el
     * botón). Nada de esto le hace falta al docente de todas formas: el
     * siguiente test confirma que puede adjuntar el reemplazo
     * directamente, sin tener que "liberar" primero el archivo viejo.
     */
    public function test_the_old_approved_file_is_visible_read_only_in_history_and_remove_file_refuses_to_touch_it(): void
    {
        [$evidence, $teacher, $admin, , $originalFile] = $this->approvedEvidenceWithOneFile();

        Livewire::actingAs($admin)->test(ReviewShow::class, ['evidence' => $evidence])->call('reopen');
        $evidence->refresh();

        // Simula el disco efímero de Render: la fila sigue en la base,
        // pero el archivo físico ya no existe.
        Storage::disk('local')->delete($originalFile->disk_path);

        $workspace = Livewire::actingAs($teacher)->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()]);

        $workspace->assertSee('Historial de versiones')
            ->assertSee('Archivo no disponible')
            ->assertDontSee('Quitar');

        $workspace->call('removeFile', $originalFile->id);

        // No era del borrador (no existe ninguno todavía) — removeFile()
        // no hizo nada: la fila de la versión aprobada sigue intacta.
        $this->assertDatabaseHas('evidence_files', ['id' => $originalFile->id]);
        $this->assertDatabaseHas('evidence_versions', ['id' => $originalFile->evidence_version_id]);

        // Administrador sigue sin poder intentarlo siquiera — removeFile()
        // exige la policy 'update', exclusiva del propio docente.
        $this->assertFalse($admin->can('update', $evidence));
        $this->assertTrue($teacher->can('update', $evidence));
    }

    /**
     * (d) CORREGIDO: con max_files=1 y solo tipo "file" permitido, el
     * docente ya no queda atascado — puede reemplazar el archivo
     * directamente (b) y reenviar con normalidad.
     */
    public function test_teacher_can_replace_the_file_and_resubmit_after_reopening_at_the_limit(): void
    {
        [$evidence, $teacher, $admin] = $this->approvedEvidenceWithOneFile(maxFiles: 1);

        Livewire::actingAs($admin)->test(ReviewShow::class, ['evidence' => $evidence])->call('reopen');
        $evidence->refresh();

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [UploadedFile::fake()->create('nuevo.pdf', 50, 'application/pdf')])
            ->call('submit')
            ->assertSet('submissionError', '');

        $this->assertEquals(EvidenceStatus::Submitted, $evidence->fresh()->status);
    }
}
