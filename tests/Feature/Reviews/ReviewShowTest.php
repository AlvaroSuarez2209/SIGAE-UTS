<?php

namespace Tests\Feature\Reviews;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\ReviewDecision;
use App\Enums\RoleName;
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
use Livewire\Livewire;
use Tests\TestCase;

class ReviewShowTest extends TestCase
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
     * Builds a submitted evidence for a teacher, plus a leader whose
     * leadership does or doesn't cover that teacher's activity/program.
     */
    private function submittedEvidenceWithLeader(bool $leaderInScope = true): array
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

        $evidence = Evidence::factory()->create([
            'user_id' => $teacher->id,
            'status' => EvidenceStatus::Pending,
            'deliverable_id' => Deliverable::factory()->create([
                'academic_period_id' => $period->id,
                'activity_id' => $activity->id,
            ])->id,
        ]);

        $version = $evidence->startOrGetDraftVersion($teacher);
        $version->update(['description' => 'Contenido de prueba']);
        $evidence->submitCurrentVersion();

        return [$evidence->fresh(), $leader, $teacher];
    }

    public function test_teacher_cannot_access_review_routes(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)->get('/reviews')->assertForbidden();
    }

    public function test_leader_within_scope_can_approve(): void
    {
        [$evidence, $leader] = $this->submittedEvidenceWithLeader(leaderInScope: true);

        Livewire::actingAs($leader)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->call('approve')
            ->assertHasNoErrors();

        $evidence->refresh();
        $this->assertEquals(EvidenceStatus::Approved, $evidence->status);
        $this->assertEquals(ReviewDecision::Approved, $evidence->currentVersion->reviews()->first()->decision);
    }

    public function test_leader_outside_scope_cannot_review(): void
    {
        [$evidence, $leader] = $this->submittedEvidenceWithLeader(leaderInScope: false);

        $this->actingAs($leader)->get('/reviews/'.$evidence->id)->assertForbidden();
    }

    public function test_returning_requires_an_observation(): void
    {
        [$evidence, $leader] = $this->submittedEvidenceWithLeader();

        Livewire::actingAs($leader)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->set('observation', '')
            ->call('returnForAdjustment')
            ->assertHasErrors('observation');

        $this->assertEquals(EvidenceStatus::Submitted, $evidence->fresh()->status);
    }

    public function test_returning_with_an_observation_moves_evidence_to_needs_adjustment(): void
    {
        [$evidence, $leader] = $this->submittedEvidenceWithLeader();

        Livewire::actingAs($leader)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->set('observation', 'Falta el archivo de soporte.')
            ->call('returnForAdjustment')
            ->assertHasNoErrors();

        $evidence->refresh();
        $this->assertEquals(EvidenceStatus::NeedsAdjustment, $evidence->status);

        $review = $evidence->currentVersion->reviews()->first();
        $this->assertEquals(ReviewDecision::Returned, $review->decision);
        $this->assertEquals('Falta el archivo de soporte.', $review->observations->first()->body);
    }

    public function test_teacher_cannot_review_their_own_evidence_even_as_leader(): void
    {
        [$evidence, , $teacher] = $this->submittedEvidenceWithLeader();

        // Le damos también rol de líder al propio docente para probar que
        // el bloqueo es por identidad, no por rol.
        $teacher->roles()->attach(Role::where('name', RoleName::Leader->value)->first());

        $this->actingAs($teacher)->get('/reviews/'.$evidence->id)->assertForbidden();
    }

    public function test_administrator_can_review_evidence_outside_any_leadership_scope(): void
    {
        [$evidence] = $this->submittedEvidenceWithLeader(leaderInScope: false);
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->call('approve')
            ->assertHasNoErrors();

        $this->assertEquals(EvidenceStatus::Approved, $evidence->fresh()->status);
    }

    public function test_cannot_act_on_an_evidence_that_is_no_longer_pending_review(): void
    {
        [$evidence, $leader] = $this->submittedEvidenceWithLeader();
        $evidence->update(['status' => EvidenceStatus::Approved]);

        Livewire::actingAs($leader)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->call('approve')
            ->assertHasErrors('observation');

        // El estado ya no era Submitted, así que el guard debe impedir que
        // se cree ninguna revisión duplicada.
        $this->assertEquals(0, $evidence->currentVersion->reviews()->count());
    }

    public function test_only_administrator_can_reopen_an_approved_evidence(): void
    {
        [$evidence, $leader] = $this->submittedEvidenceWithLeader();
        $evidence->update(['status' => EvidenceStatus::Approved]);

        Livewire::actingAs($leader)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->call('reopen')
            ->assertForbidden();

        $this->assertEquals(EvidenceStatus::Approved, $evidence->fresh()->status);

        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->call('reopen');

        $this->assertEquals(EvidenceStatus::NeedsAdjustment, $evidence->fresh()->status);
    }
}
