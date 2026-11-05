<?php

namespace Tests\Feature\Reports;

use App\Enums\AcademicPeriodStatus;
use App\Enums\RoleName;
use App\Livewire\Reports\ActivityReport;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Punto 12 de la revisión de la directora: al elegir el periodo, el
 * informe por actividad autoselecciona la primera actividad (orden
 * alfabético insensible a tildes/mayúsculas) con al menos una
 * TeacherAssignment en ese periodo. El usuario puede cambiarla después.
 */
class ActivityReportTest extends TestCase
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

    public function test_changing_the_period_autoselects_the_first_activity_alphabetically_with_an_assignment(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        $extension = Activity::factory()->create(['name' => 'Extensión comunitaria']);
        $atencion = Activity::factory()->create(['name' => 'Ática en la docencia']);

        TeacherAssignment::factory()->create(['activity_id' => $extension->id, 'academic_period_id' => $period->id]);
        TeacherAssignment::factory()->create(['activity_id' => $atencion->id, 'academic_period_id' => $period->id]);

        Livewire::actingAs($admin)
            ->test(ActivityReport::class)
            ->set('periodFilter', $period->id)
            ->assertSet('activityFilter', $atencion->id);
    }

    public function test_manually_changing_the_activity_filter_is_respected(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        $first = Activity::factory()->create(['name' => 'Acompañamiento docente']);
        $chosen = Activity::factory()->create(['name' => 'Zonas de práctica']);

        TeacherAssignment::factory()->create(['activity_id' => $first->id, 'academic_period_id' => $period->id]);
        TeacherAssignment::factory()->create(['activity_id' => $chosen->id, 'academic_period_id' => $period->id]);

        Livewire::actingAs($admin)
            ->test(ActivityReport::class)
            ->set('periodFilter', $period->id)
            ->assertSet('activityFilter', $first->id)
            ->set('activityFilter', $chosen->id)
            ->assertSet('activityFilter', $chosen->id);
    }

    public function test_a_period_with_no_assignments_leaves_the_activity_filter_empty(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        Activity::factory()->create();

        Livewire::actingAs($admin)
            ->test(ActivityReport::class)
            ->set('periodFilter', $period->id)
            ->assertSet('activityFilter', null)
            ->assertOk();
    }

    public function test_changing_the_period_recomputes_the_autoselected_activity(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $periodA = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active, 'start_date' => now()->subMonths(6)]);
        $periodB = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Planning, 'start_date' => now()]);

        $activityA = Activity::factory()->create(['name' => 'Actividad del periodo A']);
        $activityB = Activity::factory()->create(['name' => 'Actividad del periodo B']);

        TeacherAssignment::factory()->create(['activity_id' => $activityA->id, 'academic_period_id' => $periodA->id]);
        TeacherAssignment::factory()->create(['activity_id' => $activityB->id, 'academic_period_id' => $periodB->id]);

        Livewire::actingAs($admin)
            ->test(ActivityReport::class)
            ->set('periodFilter', $periodA->id)
            ->assertSet('activityFilter', $activityA->id)
            ->set('periodFilter', $periodB->id)
            ->assertSet('activityFilter', $activityB->id);
    }
}
