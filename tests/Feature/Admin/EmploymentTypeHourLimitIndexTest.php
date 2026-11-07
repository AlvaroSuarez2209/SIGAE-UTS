<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Livewire\Admin\EmploymentTypeHourLimitIndex;
use App\Models\AuditLog;
use App\Models\EmploymentTypeHourLimit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Revisión de la directora: pantalla para editar los topes semanales de
 * horas por tipo de vinculación — accesible a Administrador y
 * Coordinación, nunca crea ni borra filas (los 4 tipos son fijos, ver
 * migración 2026_11_06_000003_create_employment_type_hour_limits_table).
 */
class EmploymentTypeHourLimitIndexTest extends TestCase
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

    public function test_the_migration_seeds_the_four_provisional_limits(): void
    {
        $this->assertSame(40, EmploymentTypeHourLimit::where('employment_type', 'planta')->value('weekly_hour_limit'));
        $this->assertSame(40, EmploymentTypeHourLimit::where('employment_type', 'tiempo_completo')->value('weekly_hour_limit'));
        $this->assertSame(20, EmploymentTypeHourLimit::where('employment_type', 'medio_tiempo_1m')->value('weekly_hour_limit'));
        $this->assertSame(20, EmploymentTypeHourLimit::where('employment_type', 'medio_tiempo_4m')->value('weekly_hour_limit'));
        $this->assertNull(EmploymentTypeHourLimit::where('employment_type', 'planta')->value('duration_months'));
        $this->assertSame(1, EmploymentTypeHourLimit::where('employment_type', 'medio_tiempo_1m')->value('duration_months'));
        $this->assertSame(4, EmploymentTypeHourLimit::where('employment_type', 'medio_tiempo_4m')->value('duration_months'));
    }

    public function test_administrator_can_view_the_screen(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)->get('/admin/employment-type-hour-limits')->assertOk();
    }

    public function test_coordination_can_view_the_screen(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        $this->actingAs($coordination)->get('/admin/employment-type-hour-limits')->assertOk();
    }

    public function test_teacher_cannot_view_the_screen(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)->get('/admin/employment-type-hour-limits')->assertForbidden();
    }

    public function test_administrator_can_update_a_limit(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $limit = EmploymentTypeHourLimit::where('employment_type', 'planta')->first();

        Livewire::actingAs($admin)
            ->test(EmploymentTypeHourLimitIndex::class)
            ->set("weeklyHourLimits.{$limit->id}", '35')
            ->call('save', $limit->id)
            ->assertHasNoErrors();

        $this->assertSame(35, $limit->fresh()->weekly_hour_limit);
    }

    public function test_coordination_can_update_a_limit(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $limit = EmploymentTypeHourLimit::where('employment_type', 'medio_tiempo_1m')->first();

        Livewire::actingAs($coordination)
            ->test(EmploymentTypeHourLimitIndex::class)
            ->set("weeklyHourLimits.{$limit->id}", '18')
            ->call('save', $limit->id)
            ->assertHasNoErrors();

        $this->assertSame(18, $limit->fresh()->weekly_hour_limit);
    }

    public function test_a_non_positive_limit_is_rejected(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $limit = EmploymentTypeHourLimit::where('employment_type', 'planta')->first();

        Livewire::actingAs($admin)
            ->test(EmploymentTypeHourLimitIndex::class)
            ->set("weeklyHourLimits.{$limit->id}", '0')
            ->call('save', $limit->id)
            ->assertHasErrors("weeklyHourLimits.{$limit->id}");

        $this->assertSame(40, $limit->fresh()->weekly_hour_limit);
    }

    public function test_a_non_integer_limit_is_rejected(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $limit = EmploymentTypeHourLimit::where('employment_type', 'planta')->first();

        Livewire::actingAs($admin)
            ->test(EmploymentTypeHourLimitIndex::class)
            ->set("weeklyHourLimits.{$limit->id}", '40.5')
            ->call('save', $limit->id)
            ->assertHasErrors("weeklyHourLimits.{$limit->id}");

        $this->assertSame(40, $limit->fresh()->weekly_hour_limit);
    }

    public function test_updating_a_limit_is_audited_with_the_previous_and_new_value(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $limit = EmploymentTypeHourLimit::where('employment_type', 'planta')->first();

        Livewire::actingAs($admin)
            ->test(EmploymentTypeHourLimitIndex::class)
            ->set("weeklyHourLimits.{$limit->id}", '38')
            ->call('save', $limit->id);

        $log = AuditLog::where('action', 'updated')
            ->where('auditable_type', EmploymentTypeHourLimit::class)
            ->where('auditable_id', $limit->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(40, $log->metadata['previous']['weekly_hour_limit']);
        $this->assertSame(38, $log->metadata['changes']['weekly_hour_limit']);
    }
}
