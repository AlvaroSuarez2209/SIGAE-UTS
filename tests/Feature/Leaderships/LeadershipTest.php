<?php

namespace Tests\Feature\Leaderships;

use App\Enums\AcademicPeriodStatus;
use App\Enums\RoleName;
use App\Livewire\Leaderships\LeadershipForm;
use App\Livewire\Leaderships\LeadershipIndex;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeadershipTest extends TestCase
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

    public function test_teacher_cannot_manage_leaderships(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)->get('/leaderships')->assertForbidden();
    }

    public function test_administrator_can_assign_a_leadership_over_a_whole_program_unit(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $leader = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $programUnit = ProgramUnit::factory()->create();

        Livewire::actingAs($admin)
            ->test(LeadershipForm::class)
            ->set('user_id', $leader->id)
            ->set('academic_period_id', $period->id)
            ->set('program_unit_id', $programUnit->id)
            ->set('activity_id', null)
            ->set('starts_at', now()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('leaderships', [
            'user_id' => $leader->id,
            'program_unit_id' => $programUnit->id,
            'activity_id' => null,
        ]);
    }

    public function test_cannot_create_leadership_for_a_closed_period(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $leader = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Closed]);
        $programUnit = ProgramUnit::factory()->create();

        Livewire::actingAs($admin)
            ->test(LeadershipForm::class)
            ->set('user_id', $leader->id)
            ->set('academic_period_id', $period->id)
            ->set('program_unit_id', $programUnit->id)
            ->set('starts_at', now()->toDateString())
            ->call('save')
            ->assertHasErrors('academic_period_id');

        $this->assertDatabaseCount('leaderships', 0);
    }

    public function test_ending_a_leadership_sets_end_date_without_deleting_it(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $leadership = Leadership::factory()->create(['ends_at' => null]);

        Livewire::actingAs($admin)
            ->test(LeadershipIndex::class)
            ->call('endNow', $leadership);

        $leadership->refresh();
        $this->assertNotNull($leadership->ends_at);
        $this->assertDatabaseCount('leaderships', 1);
    }

    public function test_leader_with_whole_program_scope_can_lead_any_activity_in_it(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create();
        $programUnit = ProgramUnit::factory()->create();
        $assignment = TeacherAssignment::factory()->create([
            'academic_period_id' => $period->id,
            'program_unit_id' => $programUnit->id,
        ]);

        Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => $programUnit->id,
            'activity_id' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ]);

        $this->assertTrue($leader->canLeadAssignment($assignment));
    }

    public function test_leader_with_narrow_activity_scope_cannot_lead_a_different_activity(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create();
        $programUnit = ProgramUnit::factory()->create();
        $scopedActivity = Activity::factory()->create();
        $otherActivity = Activity::factory()->create();

        $assignment = TeacherAssignment::factory()->create([
            'academic_period_id' => $period->id,
            'program_unit_id' => $programUnit->id,
            'activity_id' => $otherActivity->id,
        ]);

        Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => $programUnit->id,
            'activity_id' => $scopedActivity->id,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ]);

        $this->assertFalse($leader->canLeadAssignment($assignment));
    }

    public function test_leader_cannot_see_assignments_outside_their_program_unit(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create();
        $ownProgramUnit = ProgramUnit::factory()->create();
        $otherProgramUnit = ProgramUnit::factory()->create();

        $assignment = TeacherAssignment::factory()->create([
            'academic_period_id' => $period->id,
            'program_unit_id' => $otherProgramUnit->id,
        ]);

        Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => $ownProgramUnit->id,
            'activity_id' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ]);

        $this->assertFalse($leader->canLeadAssignment($assignment));
    }

    public function test_expired_leadership_no_longer_grants_access(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create();
        $programUnit = ProgramUnit::factory()->create();

        $assignment = TeacherAssignment::factory()->create([
            'academic_period_id' => $period->id,
            'program_unit_id' => $programUnit->id,
        ]);

        Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => $programUnit->id,
            'activity_id' => null,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
        ]);

        $this->assertFalse($leader->canLeadAssignment($assignment));
    }

    public function test_leadership_search_is_accent_insensitive(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $leader = $this->userWithRole(RoleName::Leader);
        $leader->update(['name' => 'Andrés Peña']);
        Leadership::factory()->create(['user_id' => $leader->id]);

        Livewire::actingAs($admin)
            ->test(LeadershipIndex::class)
            ->set('periodFilter', null)
            ->set('search', 'andres pena')
            ->assertViewHas('leaderships', fn ($leaderships) => $leaderships->total() === 1
                && $leaderships->first()->user->name === 'Andrés Peña');
    }
}
