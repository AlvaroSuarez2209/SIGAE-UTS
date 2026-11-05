<?php

namespace Tests\Feature\Reports;

use App\Enums\AcademicPeriodStatus;
use App\Enums\RoleName;
use App\Livewire\Reports\TeacherReport;
use App\Models\AcademicPeriod;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Punto 12 de la revisión de la directora: al elegir el periodo, el
 * informe por docente autoselecciona el primer docente (orden alfabético
 * insensible a tildes/mayúsculas) con al menos una TeacherAssignment en
 * ese periodo. El usuario puede cambiarlo después.
 */
class TeacherReportTest extends TestCase
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

    public function test_changing_the_period_autoselects_the_first_teacher_alphabetically_with_an_assignment(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        $beatriz = $this->userWithRole(RoleName::Teacher);
        $beatriz->update(['name' => 'Beatriz Pérez']);
        $alvaro = $this->userWithRole(RoleName::Teacher);
        $alvaro->update(['name' => 'Álvaro Gómez']);

        TeacherAssignment::factory()->create(['user_id' => $beatriz->id, 'academic_period_id' => $period->id]);
        TeacherAssignment::factory()->create(['user_id' => $alvaro->id, 'academic_period_id' => $period->id]);

        Livewire::actingAs($admin)
            ->test(TeacherReport::class)
            ->set('periodFilter', $period->id)
            ->assertSet('teacherFilter', $alvaro->id);
    }

    public function test_manually_changing_the_teacher_filter_is_respected(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        $first = $this->userWithRole(RoleName::Teacher);
        $first->update(['name' => 'Ana Torres']);
        $chosen = $this->userWithRole(RoleName::Teacher);
        $chosen->update(['name' => 'Zoila Ruiz']);

        TeacherAssignment::factory()->create(['user_id' => $first->id, 'academic_period_id' => $period->id]);
        TeacherAssignment::factory()->create(['user_id' => $chosen->id, 'academic_period_id' => $period->id]);

        Livewire::actingAs($admin)
            ->test(TeacherReport::class)
            ->set('periodFilter', $period->id)
            ->assertSet('teacherFilter', $first->id)
            ->set('teacherFilter', $chosen->id)
            ->assertSet('teacherFilter', $chosen->id);
    }

    public function test_a_period_with_no_assignments_leaves_the_teacher_filter_empty(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $this->userWithRole(RoleName::Teacher);

        Livewire::actingAs($admin)
            ->test(TeacherReport::class)
            ->set('periodFilter', $period->id)
            ->assertSet('teacherFilter', null)
            ->assertOk();
    }

    public function test_changing_the_period_recomputes_the_autoselected_teacher(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $periodA = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active, 'start_date' => now()->subMonths(6)]);
        $periodB = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Planning, 'start_date' => now()]);

        $teacherA = $this->userWithRole(RoleName::Teacher);
        $teacherA->update(['name' => 'Carlos Mora']);
        $teacherB = $this->userWithRole(RoleName::Teacher);
        $teacherB->update(['name' => 'Elena Ríos']);

        TeacherAssignment::factory()->create(['user_id' => $teacherA->id, 'academic_period_id' => $periodA->id]);
        TeacherAssignment::factory()->create(['user_id' => $teacherB->id, 'academic_period_id' => $periodB->id]);

        Livewire::actingAs($admin)
            ->test(TeacherReport::class)
            ->set('periodFilter', $periodA->id)
            ->assertSet('teacherFilter', $teacherA->id)
            ->set('periodFilter', $periodB->id)
            ->assertSet('teacherFilter', $teacherB->id);
    }
}
