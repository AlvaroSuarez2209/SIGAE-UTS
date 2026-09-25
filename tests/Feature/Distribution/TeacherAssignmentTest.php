<?php

namespace Tests\Feature\Distribution;

use App\Enums\AcademicPeriodStatus;
use App\Enums\RoleName;
use App\Livewire\Distribution\AssignmentForm;
use App\Livewire\Distribution\AssignmentIndex;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeacherAssignmentTest extends TestCase
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

    private function teacher(): User
    {
        return $this->userWithRole(RoleName::Teacher);
    }

    public function test_leader_cannot_access_distribution(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);

        $this->actingAs($leader)->get('/distribution')->assertForbidden();
    }

    public function test_coordination_can_register_a_teacher_assignment(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $teacher = $this->teacher();
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $programUnit = ProgramUnit::factory()->create();

        Livewire::actingAs($coordination)
            ->test(AssignmentForm::class)
            ->set('user_id', $teacher->id)
            ->set('academic_period_id', $period->id)
            ->set('activity_id', $activity->id)
            ->set('program_unit_id', $programUnit->id)
            ->set('assigned_hours', '5')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teacher_assignments', [
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'assigned_hours' => 5,
        ]);
    }

    public function test_assigned_hours_do_not_create_any_deliverables(): void
    {
        // Regla de negocio central: las horas son informativas y jamás
        // determinan por sí solas la cantidad de entregables. Este test se
        // reforzará en el módulo 5 comparando contra deliverables reales;
        // por ahora confirma que registrar horas no dispara ningún efecto
        // colateral sobre otras tablas.
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->teacher();
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $programUnit = ProgramUnit::factory()->create();

        Livewire::actingAs($admin)
            ->test(AssignmentForm::class)
            ->set('user_id', $teacher->id)
            ->set('academic_period_id', $period->id)
            ->set('activity_id', $activity->id)
            ->set('program_unit_id', $programUnit->id)
            ->set('assigned_hours', '40')
            ->call('save');

        $this->assertDatabaseCount('teacher_assignments', 1);
    }

    public function test_cannot_create_duplicate_assignment_for_same_scope(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->teacher();
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $programUnit = ProgramUnit::factory()->create();

        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $programUnit->id,
        ]);

        Livewire::actingAs($admin)
            ->test(AssignmentForm::class)
            ->set('user_id', $teacher->id)
            ->set('academic_period_id', $period->id)
            ->set('activity_id', $activity->id)
            ->set('program_unit_id', $programUnit->id)
            ->set('assigned_hours', '3')
            ->call('save')
            ->assertHasErrors('activity_id');

        $this->assertDatabaseCount('teacher_assignments', 1);
    }

    public function test_a_teacher_can_have_multiple_activities_in_the_same_period(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->teacher();
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $programUnit = ProgramUnit::factory()->create();
        $activityA = Activity::factory()->create();
        $activityB = Activity::factory()->create();

        foreach ([$activityA, $activityB] as $activity) {
            Livewire::actingAs($admin)
                ->test(AssignmentForm::class)
                ->set('user_id', $teacher->id)
                ->set('academic_period_id', $period->id)
                ->set('activity_id', $activity->id)
                ->set('program_unit_id', $programUnit->id)
                ->set('assigned_hours', '2')
                ->call('save');
        }

        $this->assertDatabaseCount('teacher_assignments', 2);
    }

    public function test_an_activity_can_have_multiple_teachers(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacherA = $this->teacher();
        $teacherB = $this->teacher();
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $programUnit = ProgramUnit::factory()->create();
        $activity = Activity::factory()->create();

        foreach ([$teacherA, $teacherB] as $teacher) {
            Livewire::actingAs($admin)
                ->test(AssignmentForm::class)
                ->set('user_id', $teacher->id)
                ->set('academic_period_id', $period->id)
                ->set('activity_id', $activity->id)
                ->set('program_unit_id', $programUnit->id)
                ->set('assigned_hours', '2')
                ->call('save');
        }

        $this->assertDatabaseCount('teacher_assignments', 2);
    }

    public function test_cannot_create_assignment_for_a_closed_period(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->teacher();
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Closed]);
        $activity = Activity::factory()->create();
        $programUnit = ProgramUnit::factory()->create();

        Livewire::actingAs($admin)
            ->test(AssignmentForm::class)
            ->set('user_id', $teacher->id)
            ->set('academic_period_id', $period->id)
            ->set('activity_id', $activity->id)
            ->set('program_unit_id', $programUnit->id)
            ->set('assigned_hours', '2')
            ->call('save')
            ->assertHasErrors('academic_period_id');

        $this->assertDatabaseCount('teacher_assignments', 0);
    }

    public function test_editing_an_assignment_in_a_closed_period_is_locked(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $assignment = TeacherAssignment::factory()->create([
            'academic_period_id' => AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Closed])->id,
        ]);

        Livewire::actingAs($admin)
            ->test(AssignmentForm::class, ['teacherAssignment' => $assignment])
            ->assertSet('periodLocked', true)
            ->set('assigned_hours', '99')
            ->call('save')
            ->assertHasErrors('academic_period_id');

        $this->assertEquals(
            $assignment->assigned_hours,
            $assignment->fresh()->assigned_hours
        );
    }

    public function test_assignment_search_is_accent_insensitive(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->teacher();
        $teacher->update(['name' => 'Andrés Peña']);
        TeacherAssignment::factory()->create(['user_id' => $teacher->id]);

        Livewire::actingAs($admin)
            ->test(AssignmentIndex::class)
            ->set('periodFilter', null)
            ->set('search', 'andres pena')
            ->assertViewHas('assignments', fn ($assignments) => $assignments->total() === 1
                && $assignments->first()->user->name === 'Andrés Peña');
    }
}
