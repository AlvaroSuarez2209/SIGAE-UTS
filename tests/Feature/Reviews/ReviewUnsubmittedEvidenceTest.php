<?php

namespace Tests\Feature\Reviews;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
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
use Tests\TestCase;

/**
 * Investigación: ¿qué pasa al abrir /reviews/{evidence} por URL directa
 * sobre una evidencia que nunca fue enviada (Pendiente o Borrador)?
 *
 * Coordinación nunca llega a renderizar la página para ninguna evidencia
 * (Evidence::isReviewableBy() le niega el paso siempre, sin importar el
 * estado) — ya responde 403 hoy, sin cambios.
 *
 * Administrador (solo sobre un compromiso transversal) y Líder (dentro de
 * su ámbito, sobre cualquier estado — isReviewableBy() no mira el estado)
 * SÍ pasan el Gate de mount() para una evidencia Pendiente o Borrador. Ahí
 * es donde aparece el problema real: review-show.blade.php asume
 * `$evidence->currentVersion` (línea 2: `$version = $evidence->currentVersion`)
 * y lo usa sin null-safe en `$version->submitted_at` (línea 31) y
 * `$version->version_number` (línea 35) — una evidencia Pendiente nunca
 * tiene versión (`current_version_id` es null), así que esas dos líneas
 * truenan con un 500 real.
 */
class ReviewUnsubmittedEvidenceTest extends TestCase
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

    private function crossCuttingEvidence(EvidenceStatus $status): Evidence
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $deliverable = Deliverable::factory()->crossCutting()->create();
        $evidence = Evidence::factory()->create([
            'user_id' => $teacher->id,
            'status' => EvidenceStatus::Pending,
            'deliverable_id' => $deliverable->id,
        ]);

        return $this->applyStatus($evidence, $teacher, $status);
    }

    private function activityEvidenceLedByLeader(EvidenceStatus $status): array
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

        return [$this->applyStatus($evidence, $teacher, $status), $leader];
    }

    /**
     * `Pending` se deja tal cual sale del factory (sin versión alguna).
     * `Draft` se alcanza igual que en la aplicación real: creando una
     * versión real con startOrGetDraftVersion() y sin enviarla.
     */
    private function applyStatus(Evidence $evidence, User $teacher, EvidenceStatus $status): Evidence
    {
        if ($status === EvidenceStatus::Draft) {
            $evidence->startOrGetDraftVersion($teacher);
        }

        return $evidence->fresh();
    }

    public function test_coordination_opening_a_pending_evidence_is_forbidden_not_a_crash(): void
    {
        $evidence = $this->crossCuttingEvidence(EvidenceStatus::Pending);
        $coordination = $this->userWithRole(RoleName::Coordination);

        $this->actingAs($coordination)->get('/reviews/'.$evidence->id)->assertForbidden();
    }

    public function test_coordination_opening_a_draft_evidence_is_forbidden_not_a_crash(): void
    {
        $evidence = $this->crossCuttingEvidence(EvidenceStatus::Draft);
        $coordination = $this->userWithRole(RoleName::Coordination);

        $this->actingAs($coordination)->get('/reviews/'.$evidence->id)->assertForbidden();
    }

    public function test_administrator_opening_a_pending_cross_cutting_evidence_is_redirected_not_a_crash(): void
    {
        $evidence = $this->crossCuttingEvidence(EvidenceStatus::Pending);
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)->get('/reviews/'.$evidence->id)
            ->assertRedirect(route('reviews.index'));
    }

    public function test_administrator_opening_a_draft_cross_cutting_evidence_does_not_crash(): void
    {
        $evidence = $this->crossCuttingEvidence(EvidenceStatus::Draft);
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)->get('/reviews/'.$evidence->id)->assertOk();
    }

    public function test_leader_opening_a_pending_evidence_in_their_scope_is_redirected_not_a_crash(): void
    {
        [$evidence, $leader] = $this->activityEvidenceLedByLeader(EvidenceStatus::Pending);

        $this->actingAs($leader)->get('/reviews/'.$evidence->id)
            ->assertRedirect(route('reviews.index'));
    }

    public function test_redirecting_away_from_a_pending_evidence_flashes_an_explanatory_message(): void
    {
        $evidence = $this->crossCuttingEvidence(EvidenceStatus::Pending);
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)->get('/reviews/'.$evidence->id);

        $this->actingAs($admin)
            ->get(route('reviews.index'))
            ->assertSee('Esta evidencia todavía no tiene ninguna versión enviada; no hay nada que revisar.');
    }

    public function test_leader_opening_a_draft_evidence_in_their_scope_does_not_crash(): void
    {
        [$evidence, $leader] = $this->activityEvidenceLedByLeader(EvidenceStatus::Draft);

        $this->actingAs($leader)->get('/reviews/'.$evidence->id)->assertOk();
    }
}
