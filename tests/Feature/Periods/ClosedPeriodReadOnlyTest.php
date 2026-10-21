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

/**
 * RF-010: "El sistema deberá conservar histórico de periodos cerrados en
 * modo consulta." Antes de esta prueba, PeriodIndex::save() solo
 * bloqueaba las fechas para cualquier periodo que no estuviera en
 * Planeación (Activo, Cerrado o Archivado) — el nombre seguía siendo
 * editable en los tres. Esto deja el bloqueo real en el backend
 * (PeriodIndex::save(), recalculado siempre desde $this->editing->status,
 * nunca desde la propiedad pública $periodLocked que viaja al cliente) y
 * prueba que:
 * - Cerrado y Archivado quedan en modo consulta completo (ningún campo).
 * - Planeación sigue totalmente editable y Activo conserva exactamente
 *   su comportamiento anterior (nombre editable, fechas bloqueadas) — ver
 *   AcademicPeriodTest::test_dates_cannot_be_changed_once_period_is_no_longer_in_planning().
 * - Las transiciones de estado (activate/close/archive/reopen) no se
 *   tocaron y siguen funcionando exactamente igual.
 */
class ClosedPeriodReadOnlyTest extends TestCase
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

    private function attemptFullEdit(User $admin, AcademicPeriod $period): void
    {
        Livewire::actingAs($admin)
            ->test(PeriodIndex::class)
            ->call('openEdit', $period)
            ->set('name', 'Nombre que no debería guardarse')
            ->set('start_date', '2099-01-01')
            ->set('end_date', '2099-06-01')
            ->call('save');
    }

    // --- Cerrado: ningún campo editable, y nada cambia en la base de datos ---

    public function test_cannot_change_the_name_of_a_closed_period(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create([
            'status' => AcademicPeriodStatus::Closed,
            'name' => '2026-1',
        ]);

        $this->attemptFullEdit($admin, $period);

        $this->assertSame('2026-1', $period->fresh()->name);
    }

    public function test_cannot_change_the_dates_of_a_closed_period(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create([
            'status' => AcademicPeriodStatus::Closed,
            'start_date' => '2026-01-20',
            'end_date' => '2026-06-15',
        ]);

        $this->attemptFullEdit($admin, $period);

        $period->refresh();
        $this->assertSame('2026-01-20', $period->start_date->toDateString());
        $this->assertSame('2026-06-15', $period->end_date->toDateString());
    }

    public function test_editing_a_closed_period_leaves_the_database_row_completely_unchanged(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create([
            'status' => AcademicPeriodStatus::Closed,
            'name' => '2026-1',
            'start_date' => '2026-01-20',
            'end_date' => '2026-06-15',
        ]);
        $updatedAtBefore = $period->updated_at;

        $this->attemptFullEdit($admin, $period);

        // updated_at intacto prueba algo más fuerte que solo comparar
        // columna por columna: que ningún update() llegó a ejecutarse
        // contra esta fila en absoluto (Eloquent solo toca updated_at
        // cuando de verdad corre un UPDATE).
        $fresh = $period->fresh();
        $this->assertSame('2026-1', $fresh->name);
        $this->assertSame('2026-01-20', $fresh->start_date->toDateString());
        $this->assertSame('2026-06-15', $fresh->end_date->toDateString());
        $this->assertTrue($updatedAtBefore->equalTo($fresh->updated_at));
    }

    // --- Archivado: mismo bloqueo completo ---

    public function test_cannot_change_the_name_of_an_archived_period(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create([
            'status' => AcademicPeriodStatus::Archived,
            'name' => '2025-2',
        ]);

        $this->attemptFullEdit($admin, $period);

        $this->assertSame('2025-2', $period->fresh()->name);
    }

    public function test_cannot_change_the_dates_of_an_archived_period(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create([
            'status' => AcademicPeriodStatus::Archived,
            'start_date' => '2025-07-15',
            'end_date' => '2025-12-05',
        ]);

        $this->attemptFullEdit($admin, $period);

        $period->refresh();
        $this->assertSame('2025-07-15', $period->start_date->toDateString());
        $this->assertSame('2025-12-05', $period->end_date->toDateString());
    }

    public function test_editing_an_archived_period_leaves_the_database_row_completely_unchanged(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create([
            'status' => AcademicPeriodStatus::Archived,
            'name' => '2025-2',
            'start_date' => '2025-07-15',
            'end_date' => '2025-12-05',
        ]);
        $updatedAtBefore = $period->updated_at;

        $this->attemptFullEdit($admin, $period);

        $fresh = $period->fresh();
        $this->assertSame('2025-2', $fresh->name);
        $this->assertSame('2025-07-15', $fresh->start_date->toDateString());
        $this->assertSame('2025-12-05', $fresh->end_date->toDateString());
        $this->assertTrue($updatedAtBefore->equalTo($fresh->updated_at));
    }

    public function test_the_backend_guard_does_not_trust_a_tampered_periodlocked_property(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create([
            'status' => AcademicPeriodStatus::Closed,
            'name' => '2026-1',
        ]);

        // Un cliente manipulado podría enviar periodLocked=false junto con
        // save() sin pasar por openEdit() — el bloqueo real nunca debe
        // depender de esa propiedad.
        Livewire::actingAs($admin)
            ->test(PeriodIndex::class)
            ->set('editing', $period)
            ->set('periodLocked', false)
            ->set('name', 'Nombre que no debería guardarse')
            ->call('save');

        $this->assertSame('2026-1', $period->fresh()->name);
    }

    // --- Planeación y Activo: sin cambios de comportamiento ---

    public function test_a_planning_period_remains_fully_editable(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create([
            'status' => AcademicPeriodStatus::Planning,
            'name' => '2026-1',
            'start_date' => '2026-01-20',
            'end_date' => '2026-06-15',
        ]);

        Livewire::actingAs($admin)
            ->test(PeriodIndex::class)
            ->call('openEdit', $period)
            ->set('name', '2026-1 (ajustado)')
            ->set('start_date', '2026-01-25')
            ->set('end_date', '2026-06-20')
            ->call('save')
            ->assertHasNoErrors();

        $period->refresh();
        $this->assertSame('2026-1 (ajustado)', $period->name);
        $this->assertSame('2026-01-25', $period->start_date->toDateString());
        $this->assertSame('2026-06-20', $period->end_date->toDateString());
    }

    public function test_an_active_period_still_allows_renaming_but_not_dates(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create([
            'status' => AcademicPeriodStatus::Active,
            'name' => '2026-1',
            'start_date' => '2026-01-20',
            'end_date' => '2026-06-15',
        ]);

        $this->attemptFullEdit($admin, $period);

        $period->refresh();
        $this->assertSame('Nombre que no debería guardarse', $period->name);
        $this->assertSame('2026-01-20', $period->start_date->toDateString());
        $this->assertSame('2026-06-15', $period->end_date->toDateString());
    }

    // --- Las transiciones de estado existentes no se tocaron ---

    public function test_existing_state_transitions_still_work_after_restricting_edits(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Planning]);

        $component = Livewire::actingAs($admin)->test(PeriodIndex::class);

        $component->call('activate', $period);
        $this->assertEquals(AcademicPeriodStatus::Active, $period->fresh()->status);

        $component->call('close', $period);
        $this->assertEquals(AcademicPeriodStatus::Closed, $period->fresh()->status);

        $component->call('reopen', $period);
        $this->assertEquals(AcademicPeriodStatus::Active, $period->fresh()->status);

        $component->call('close', $period);
        $this->assertEquals(AcademicPeriodStatus::Closed, $period->fresh()->status);

        $component->call('archive', $period);
        $this->assertEquals(AcademicPeriodStatus::Archived, $period->fresh()->status);
    }

    // --- Interfaz: modo solo lectura con mensaje claro ---

    public function test_opening_a_closed_period_shows_a_read_only_message_and_no_save_button(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Closed]);

        $html = Livewire::actingAs($admin)
            ->test(PeriodIndex::class)
            ->call('openEdit', $period)
            ->html();

        $this->assertStringContainsString('Este periodo está cerrado o archivado y no se puede modificar.', $html);
        $this->assertStringNotContainsString('wire:target="save"', $html);
    }

    public function test_opening_an_archived_period_shows_a_read_only_message_and_no_save_button(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Archived]);

        $html = Livewire::actingAs($admin)
            ->test(PeriodIndex::class)
            ->call('openEdit', $period)
            ->html();

        $this->assertStringContainsString('Este periodo está cerrado o archivado y no se puede modificar.', $html);
        $this->assertStringNotContainsString('wire:target="save"', $html);
    }
}
