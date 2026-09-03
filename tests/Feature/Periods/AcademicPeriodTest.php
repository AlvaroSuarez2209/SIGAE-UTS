<?php

namespace Tests\Feature\Periods;

use App\Enums\AcademicPeriodStatus;
use App\Enums\RoleName;
use App\Livewire\Periods\PeriodIndex;
use App\Models\AcademicPeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AcademicPeriodTest extends TestCase
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

    public function test_administrator_can_view_periods(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)->get('/periods')->assertOk();
    }

    public function test_teacher_cannot_view_periods(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)->get('/periods')->assertForbidden();
    }

    public function test_creating_a_period_defaults_to_planning_status(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(PeriodIndex::class)
            ->set('name', '2026-2')
            ->set('start_date', '2026-07-15')
            ->set('end_date', '2026-12-05')
            ->call('save');

        $period = AcademicPeriod::where('name', '2026-2')->first();
        $this->assertNotNull($period);
        $this->assertEquals(AcademicPeriodStatus::Planning, $period->status);
    }

    public function test_end_date_must_not_be_before_start_date(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(PeriodIndex::class)
            ->set('name', '2026-2')
            ->set('start_date', '2026-12-05')
            ->set('end_date', '2026-07-15')
            ->call('save')
            ->assertHasErrors('end_date');
    }

    public function test_period_follows_the_valid_state_transition_path(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Planning]);

        $component = Livewire::actingAs($admin)->test(PeriodIndex::class);

        $component->call('activate', $period);
        $this->assertEquals(AcademicPeriodStatus::Active, $period->fresh()->status);

        $component->call('close', $period);
        $this->assertEquals(AcademicPeriodStatus::Closed, $period->fresh()->status);

        $component->call('archive', $period);
        $this->assertEquals(AcademicPeriodStatus::Archived, $period->fresh()->status);
    }

    public function test_cannot_skip_directly_from_planning_to_closed(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Planning]);

        Livewire::actingAs($admin)
            ->test(PeriodIndex::class)
            ->call('close', $period);

        $this->assertEquals(AcademicPeriodStatus::Planning, $period->fresh()->status);
    }

    public function test_closed_period_can_be_exceptionally_reopened(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Closed]);

        Livewire::actingAs($admin)
            ->test(PeriodIndex::class)
            ->call('reopen', $period);

        $this->assertEquals(AcademicPeriodStatus::Active, $period->fresh()->status);
    }

    public function test_dates_cannot_be_changed_once_period_is_no_longer_in_planning(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create([
            'status' => AcademicPeriodStatus::Active,
            'start_date' => '2026-01-20',
            'end_date' => '2026-06-15',
        ]);

        Livewire::actingAs($admin)
            ->test(PeriodIndex::class)
            ->call('openEdit', $period)
            ->set('name', 'Nombre actualizado')
            ->call('save');

        $period->refresh();
        $this->assertEquals('Nombre actualizado', $period->name);
        $this->assertEquals('2026-01-20', $period->start_date->toDateString());
        $this->assertEquals('2026-06-15', $period->end_date->toDateString());
    }
}
