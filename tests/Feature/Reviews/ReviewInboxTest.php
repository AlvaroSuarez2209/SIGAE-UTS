<?php

namespace Tests\Feature\Reviews;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Livewire\Reviews\ReviewInbox;
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
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ReviewInbox filtraba "evidencias revisables por mí" trayendo TODAS las
 * evidencias Submitted del periodo y llamando isReviewableBy() (2
 * consultas nuevas por evidencia) una por una en PHP — ahora es
 * Evidence::scopeReviewableBy(), un JOIN. Estos tests verifican que el
 * resultado sigue siendo el mismo (solo lo que cae en el ámbito del líder)
 * y que el número de consultas no crece con la cantidad de evidencias.
 */
class ReviewInboxTest extends TestCase
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
     * ReviewInbox::render() accede a $evidence->currentVersion->submitted_at
     * — status => Submitted a mano vía factory deja current_version_id en
     * null y rompe la vista; hay que pasar por el flujo real de envío
     * (igual que ReviewShowTest::submittedEvidenceWithLeader()).
     */
    private function submitEvidence(User $teacher, Deliverable $deliverable): Evidence
    {
        $evidence = Evidence::factory()->create([
            'user_id' => $teacher->id,
            'deliverable_id' => $deliverable->id,
            'status' => EvidenceStatus::Pending,
        ]);

        $version = $evidence->startOrGetDraftVersion($teacher);
        $version->update(['description' => 'Contenido de prueba']);
        $evidence->submitCurrentVersion();

        return $evidence->fresh();
    }

    public function test_leader_only_sees_evidence_within_their_scope(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $ownProgramUnit = ProgramUnit::factory()->create();
        $otherProgramUnit = ProgramUnit::factory()->create();

        Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => $ownProgramUnit->id,
            'activity_id' => null,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        $ownTeacher = $this->userWithRole(RoleName::Teacher);
        $otherTeacher = $this->userWithRole(RoleName::Teacher);
        $activityA = Activity::factory()->create();
        $activityB = Activity::factory()->create();

        TeacherAssignment::factory()->create([
            'user_id' => $ownTeacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activityA->id,
            'program_unit_id' => $ownProgramUnit->id,
        ]);
        TeacherAssignment::factory()->create([
            'user_id' => $otherTeacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activityB->id,
            'program_unit_id' => $otherProgramUnit->id,
        ]);

        $ownDeliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activityA->id]);
        $otherDeliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activityB->id]);

        $ownEvidence = $this->submitEvidence($ownTeacher, $ownDeliverable);
        $this->submitEvidence($otherTeacher, $otherDeliverable);

        Livewire::actingAs($leader)->test(ReviewInbox::class)
            ->assertViewHas('pending', fn ($pending) => $pending->count() === 1
                && $pending->first()->id === $ownEvidence->id);
    }

    /**
     * RS-005/RF-027/RF-056 a RF-059: Administrador ya no ve evidencias de
     * actividad en la bandeja (eso es exclusivo del líder asignado) — solo
     * ve compromisos transversales, la única categoría sin líder posible.
     */
    public function test_administrator_only_sees_cross_cutting_evidence(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        $activityDeliverable = Deliverable::factory()->create(['academic_period_id' => $period->id]);
        $crossCuttingDeliverable = Deliverable::factory()->crossCutting()->create(['academic_period_id' => $period->id]);

        $this->submitEvidence($teacher, $activityDeliverable);
        $crossCuttingEvidence = $this->submitEvidence($teacher, $crossCuttingDeliverable);

        Livewire::actingAs($admin)->test(ReviewInbox::class)
            ->assertViewHas('pending', fn ($pending) => $pending->count() === 1
                && $pending->first()->id === $crossCuttingEvidence->id);
    }

    public function test_coordination_sees_nothing_in_the_review_inbox(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $deliverable = Deliverable::factory()->crossCutting()->create(['academic_period_id' => $period->id]);
        $this->submitEvidence($teacher, $deliverable);

        Livewire::actingAs($coordination)->test(ReviewInbox::class)
            ->assertViewHas('pending', fn ($pending) => $pending->count() === 0);
    }

    public function test_query_count_does_not_grow_with_submitted_evidence_count(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $programUnit = ProgramUnit::factory()->create();

        Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => $programUnit->id,
            'activity_id' => null,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        for ($i = 0; $i < 10; $i++) {
            $teacher = $this->userWithRole(RoleName::Teacher);
            $activity = Activity::factory()->create();

            TeacherAssignment::factory()->create([
                'user_id' => $teacher->id,
                'academic_period_id' => $period->id,
                'activity_id' => $activity->id,
                'program_unit_id' => $programUnit->id,
            ]);

            $deliverable = Deliverable::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activity->id]);
            $this->submitEvidence($teacher, $deliverable);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::actingAs($leader)->test(ReviewInbox::class);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(10, $queryCount, "Se esperaban menos de 10 consultas independientemente del número de evidencias, hubo {$queryCount}.");
    }
}
