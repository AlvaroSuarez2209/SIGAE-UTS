<?php

namespace Tests\Feature\Distribution;

use App\Enums\AcademicPeriodStatus;
use App\Enums\RoleName;
use App\Livewire\Distribution\AssignmentForm;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\EmploymentTypeHourLimit;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Revisión de la directora: AssignmentForm valida que la suma de horas
 * del docente en el periodo seleccionado (incluyendo la asignación nueva,
 * excluyendo la que se está editando) no supere el tope semanal de su
 * tipo de vinculación — ver App\Models\EmploymentTypeHourLimit.
 */
class TeacherAssignmentHourLimitTest extends TestCase
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

    private function teacherWithEmploymentType(string $employmentType): User
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $teacher->update(['employment_type' => $employmentType]);

        return $teacher;
    }

    public function test_an_assignment_within_the_weekly_limit_is_saved_without_errors(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $teacher = $this->teacherWithEmploymentType('medio_tiempo_1m'); // tope 20
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $programUnit = ProgramUnit::factory()->create();

        Livewire::actingAs($coordination)
            ->test(AssignmentForm::class)
            ->set('user_id', $teacher->id)
            ->set('academic_period_id', $period->id)
            ->set('activity_id', $activity->id)
            ->set('program_unit_id', $programUnit->id)
            ->set('assigned_hours', '15')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teacher_assignments', ['user_id' => $teacher->id, 'assigned_hours' => 15]);
    }

    public function test_an_assignment_that_exceeds_the_weekly_limit_is_blocked_with_a_clear_message(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $teacher = $this->teacherWithEmploymentType('medio_tiempo_1m'); // tope 20
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $existingActivity = Activity::factory()->create();
        $newActivity = Activity::factory()->create();
        $programUnit = ProgramUnit::factory()->create();

        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $existingActivity->id,
            'program_unit_id' => $programUnit->id,
            'assigned_hours' => 15,
        ]);

        $component = Livewire::actingAs($coordination)
            ->test(AssignmentForm::class)
            ->set('user_id', $teacher->id)
            ->set('academic_period_id', $period->id)
            ->set('activity_id', $newActivity->id)
            ->set('program_unit_id', $programUnit->id)
            ->set('assigned_hours', '10')
            ->call('save')
            ->assertHasErrors('assigned_hours');

        $message = $component->instance()->getErrorBag()->first('assigned_hours');
        $this->assertStringContainsString('15 hora(s) asignada(s)', $message);
        $this->assertStringContainsString('tope semanal', $message);
        $this->assertStringContainsString('20 hora(s)', $message);
        $this->assertStringContainsString('disponibles: 5', $message);

        $this->assertDatabaseCount('teacher_assignments', 1);
    }

    /**
     * Editar una asignación existente excluye sus propias horas de la
     * suma — subirla de 15 a 18 (sigue ≤ 20) debe guardar sin error.
     */
    public function test_editing_an_existing_assignment_excludes_its_own_hours_from_the_sum(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $teacher = $this->teacherWithEmploymentType('medio_tiempo_1m'); // tope 20
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $programUnit = ProgramUnit::factory()->create();

        $assignment = TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $programUnit->id,
            'assigned_hours' => 15,
        ]);

        Livewire::actingAs($coordination)
            ->test(AssignmentForm::class, ['teacherAssignment' => $assignment])
            ->set('assigned_hours', '18')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(18, $assignment->fresh()->assigned_hours);
    }

    public function test_editing_an_existing_assignment_can_still_exceed_the_limit(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $teacher = $this->teacherWithEmploymentType('medio_tiempo_1m'); // tope 20
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $programUnit = ProgramUnit::factory()->create();

        $assignment = TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $programUnit->id,
            'assigned_hours' => 15,
        ]);

        Livewire::actingAs($coordination)
            ->test(AssignmentForm::class, ['teacherAssignment' => $assignment])
            ->set('assigned_hours', '25')
            ->call('save')
            ->assertHasErrors('assigned_hours');

        $this->assertEquals(15, $assignment->fresh()->assigned_hours);
    }

    /**
     * "Si el docente no tiene vinculación definida, no bloquees: permite
     * guardar y muestra una advertencia."
     */
    public function test_a_teacher_without_an_employment_type_is_never_blocked_but_shows_a_warning(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $teacher = $this->userWithRole(RoleName::Teacher); // sin employment_type
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $programUnit = ProgramUnit::factory()->create();

        $component = Livewire::actingAs($coordination)
            ->test(AssignmentForm::class)
            ->set('user_id', $teacher->id)
            ->assertSee('Docente sin tipo de vinculación: no se validó el tope de horas.');

        $component->set('academic_period_id', $period->id)
            ->set('activity_id', $activity->id)
            ->set('program_unit_id', $programUnit->id)
            ->set('assigned_hours', '59')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teacher_assignments', ['user_id' => $teacher->id, 'assigned_hours' => 59]);
    }

    /**
     * Cambiar el tope en la tabla cambia el resultado de la validación —
     * confirma que de verdad se lee desde EmploymentTypeHourLimit en cada
     * guardado, no un valor fijo en el código.
     */
    public function test_changing_the_limit_in_the_table_changes_the_validation_outcome(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $teacher = $this->teacherWithEmploymentType('medio_tiempo_1m');
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $programUnit = ProgramUnit::factory()->create();

        EmploymentTypeHourLimit::where('employment_type', 'medio_tiempo_1m')->update(['weekly_hour_limit' => 10]);

        Livewire::actingAs($coordination)
            ->test(AssignmentForm::class)
            ->set('user_id', $teacher->id)
            ->set('academic_period_id', $period->id)
            ->set('activity_id', $activity->id)
            ->set('program_unit_id', $programUnit->id)
            ->set('assigned_hours', '15')
            ->call('save')
            ->assertHasErrors('assigned_hours');

        $this->assertDatabaseCount('teacher_assignments', 0);
    }
}
