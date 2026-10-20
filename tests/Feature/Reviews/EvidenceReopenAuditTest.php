<?php

namespace Tests\Feature\Reviews;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\ReviewDecision;
use App\Enums\RoleName;
use App\Livewire\Reviews\ReviewShow;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * RF-064: "Una evidencia aprobada solo podrá reabrirse mediante un
 * permiso especial y dejando registro de auditoría." Complementa
 * ReviewShowTest::test_only_administrator_can_reopen_an_approved_evidence()
 * (que ya cubre el permiso) con lo que todavía no estaba cubierto por
 * ninguna prueba: el registro de auditoría en sí, el bloqueo sobre una
 * evidencia no aprobada, el rechazo explícito de Coordinación/Docente/
 * Auditor (ReviewShowTest solo probaba Líder), y que versiones/
 * observaciones anteriores sobrevivan intactas a la reapertura.
 *
 * Nota importante confirmada en el código, no inventada: `ReviewShow::
 * reopen()` NO llama a `AuditLog::record()` con una acción dedicada (el
 * propio comentario del método dice "debe quedar registrado... cuando el
 * módulo 10 esté implementado") — el registro que sí queda es el
 * genérico `updated` que produce automáticamente el trait `Auditable` de
 * `Evidence` al hacer `update(['status' => ...])`. Las pruebas de abajo
 * verifican exactamente eso (acción `updated`, no una acción
 * `evidence_reopened` que no existe), no lo que se esperaría si existiera
 * una acción dedicada.
 */
class EvidenceReopenAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', $role->value)->first());

        return $user;
    }

    /**
     * Evidencia con historial real: versión 1 enviada y devuelta (con
     * observación), versión 2 enviada y aprobada (con observación) — para
     * poder comprobar, tras reabrir, que ninguna de las dos se pierde.
     * Escenario de ámbito (actividad + programa + periodo) idéntico al
     * que ya usa ReviewShowTest::submittedEvidenceWithLeader(), para que
     * el líder sí pueda pasar el gate 'review' (y así probar el rechazo
     * específico de reopen(), no un rechazo de ámbito ya cubierto en
     * otra parte).
     */
    private function approvedEvidenceWithHistory(): array
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
            'program_unit_id' => $programUnit->id,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ]);

        $evidence = Evidence::factory()->create([
            'user_id' => $teacher->id,
            'status' => EvidenceStatus::Pending,
            'deliverable_id' => Deliverable::factory()->create([
                'academic_period_id' => $period->id,
                'activity_id' => $activity->id,
            ])->id,
        ]);

        $versionOne = $evidence->startOrGetDraftVersion($teacher);
        $versionOne->update(['description' => 'Primer envío.']);
        $evidence->submitCurrentVersion();
        $evidence->returnCurrentReviewForAdjustment($leader, 'Falta un detalle, ajusta y reenvía.');

        $versionTwo = $evidence->startOrGetDraftVersion($teacher);
        $versionTwo->update(['description' => 'Versión corregida.']);
        $evidence->submitCurrentVersion();
        $evidence->approveCurrentReview($leader, 'Aprobada.');

        return [$evidence->fresh(), $leader, $teacher];
    }

    // --- Caso 1: Administrador reabre, cambia de estado y queda auditado ---

    public function test_administrator_reopening_an_approved_evidence_moves_it_to_needs_adjustment_and_is_audited(): void
    {
        [$evidence] = $this->approvedEvidenceWithHistory();
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->call('reopen')
            ->assertHasNoErrors();

        $evidence->refresh();
        $this->assertEquals(EvidenceStatus::NeedsAdjustment, $evidence->status);

        $log = AuditLog::where('auditable_type', Evidence::class)
            ->where('auditable_id', $evidence->id)
            ->where('action', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'La reapertura debió dejar un registro en audit_logs.');
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(Evidence::class, $log->auditable_type);
        $this->assertSame($evidence->id, $log->auditable_id);
        $this->assertNotNull($log->created_at);
        $this->assertSame('needs_adjustment', $log->metadata['changes']['status'] ?? null);
        $this->assertSame('approved', $log->metadata['previous']['status'] ?? null);
    }

    // --- Caso 2: ningún otro rol puede reabrir ---

    public function test_leader_within_scope_cannot_reopen_an_approved_evidence(): void
    {
        [$evidence, $leader] = $this->approvedEvidenceWithHistory();

        // El líder sí puede entrar a la página (pasa 'review', que no
        // depende del estado) — lo que debe fallar es específicamente la
        // acción reopen(), no el acceso a la pantalla.
        Livewire::actingAs($leader)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->call('reopen')
            ->assertForbidden();

        $this->assertEquals(EvidenceStatus::Approved, $evidence->fresh()->status);
    }

    public function test_coordination_cannot_reopen_an_approved_evidence(): void
    {
        [$evidence] = $this->approvedEvidenceWithHistory();
        $coordination = $this->userWithRole(RoleName::Coordination);

        // Coordinación no pasa ni 'review' ni 'reopen' para esta evidencia
        // (no es transversal) — el rechazo ocurre al entrar a la página.
        $this->actingAs($coordination)->get('/reviews/'.$evidence->id)->assertForbidden();

        $this->assertEquals(EvidenceStatus::Approved, $evidence->fresh()->status);
    }

    public function test_teacher_cannot_reopen_their_own_approved_evidence(): void
    {
        [$evidence, , $teacher] = $this->approvedEvidenceWithHistory();

        $this->actingAs($teacher)->get('/reviews/'.$evidence->id)->assertForbidden();

        $this->assertEquals(EvidenceStatus::Approved, $evidence->fresh()->status);
    }

    public function test_auditor_cannot_reopen_an_approved_evidence(): void
    {
        [$evidence] = $this->approvedEvidenceWithHistory();
        $auditor = $this->userWithRole(RoleName::Auditor);

        $this->actingAs($auditor)->get('/reviews/'.$evidence->id)->assertForbidden();

        $this->assertEquals(EvidenceStatus::Approved, $evidence->fresh()->status);
    }

    // --- Caso 3: una evidencia que no está Aprobada no se puede reabrir ---

    /**
     * Compromiso transversal (no una actividad con líder): es el único
     * caso en el que Administrador pasa el gate 'review' sin importar el
     * estado de la evidencia (Evidence::isReviewableBy()), así que se
     * puede llegar a reopen() de verdad en vez de quedar bloqueado antes,
     * en el mount() de la página — y así aislar exactamente el chequeo de
     * estado de EvidencePolicy::reopen(), no un rechazo de ámbito.
     *
     * Cada evidencia se envía primero (startOrGetDraftVersion() +
     * submitCurrentVersion()) y solo DESPUÉS se fuerza el estado a probar
     * — review-show.blade.php asume que existe una versión enviada
     * (muestra `$version->submitted_at`), igual que cualquier evidencia
     * real en estos estados (ninguna de ellas se alcanza sin pasar antes
     * por un envío real). `Pending` se deja fuera a propósito: una
     * evidencia Pendiente real nunca tiene una versión enviada, así que
     * nunca llega a esta pantalla por ningún enlace real de la
     * aplicación (la bandeja de revisión solo lista evidencias Enviadas).
     */
    public function test_an_evidence_that_is_not_approved_cannot_be_reopened(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $deliverable = Deliverable::factory()->crossCutting()->create();

        foreach ([EvidenceStatus::Submitted, EvidenceStatus::NeedsAdjustment, EvidenceStatus::Expired, EvidenceStatus::Exempt] as $status) {
            // Un docente distinto por iteración: evidences tiene una
            // única (deliverable_id, user_id), así que reutilizar el
            // mismo docente para el mismo entregable transversal en más
            // de una vuelta del bucle chocaría con esa restricción.
            $teacher = $this->userWithRole(RoleName::Teacher);

            $evidence = Evidence::factory()->create([
                'user_id' => $teacher->id,
                'status' => EvidenceStatus::Pending,
                'deliverable_id' => $deliverable->id,
            ]);

            $version = $evidence->startOrGetDraftVersion($teacher);
            $version->update(['description' => 'Contenido de prueba.']);
            $evidence->submitCurrentVersion();
            $evidence->update(['status' => $status]);

            Livewire::actingAs($admin)
                ->test(ReviewShow::class, ['evidence' => $evidence->fresh()])
                ->call('reopen')
                ->assertForbidden();

            $this->assertEquals($status, $evidence->fresh()->status, "No debió cambiar de estado ({$status->value}).");
        }
    }

    // --- Caso 4: versiones y observaciones anteriores se conservan ---

    public function test_reopening_preserves_all_previous_versions_and_observations(): void
    {
        [$evidence, $leader] = $this->approvedEvidenceWithHistory();
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->assertSame(2, $evidence->versions()->count());
        $this->assertSame(2, $evidence->reviews()->count());

        Livewire::actingAs($admin)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->call('reopen');

        $evidence->refresh();

        $this->assertSame(2, $evidence->versions()->count(), 'Reabrir no debió borrar ninguna versión.');
        $this->assertSame(2, $evidence->reviews()->count(), 'Reabrir no debió borrar ninguna revisión.');

        $versions = $evidence->versions()->orderBy('version_number')->get();
        $this->assertSame('Primer envío.', $versions[0]->description);
        $this->assertSame('Versión corregida.', $versions[1]->description);

        $returned = $evidence->reviews()->where('decision', ReviewDecision::Returned)->first();
        $approved = $evidence->reviews()->where('decision', ReviewDecision::Approved)->first();

        $this->assertNotNull($returned);
        $this->assertNotNull($approved);
        $this->assertSame('Falta un detalle, ajusta y reenvía.', $returned->observations->first()->body);
        $this->assertSame('Aprobada.', $approved->observations->first()->body);
        $this->assertSame($leader->id, $returned->reviewer_id);
        $this->assertSame($leader->id, $approved->reviewer_id);
    }
}
