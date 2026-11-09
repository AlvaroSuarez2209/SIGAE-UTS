<?php

namespace Tests\Feature\Catalogs;

use App\Enums\AcademicPeriodStatus;
use App\Enums\RoleName;
use App\Livewire\Dashboard;
use App\Livewire\Deliverables\DeliverableForm;
use App\Livewire\Reports\ActivityReport;
use App\Livewire\Reports\ConsolidatedReport;
use App\Livewire\Reports\CrossCuttingReport;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\CrossCuttingCommitment;
use App\Models\Deliverable;
use App\Models\DeliverableTemplate;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Revisión de la directora, punto 1: selectores de uso y filtros de
 * reporte que antes no filtraban por activos en absoluto (a diferencia
 * de los <select> dentro de los propios formularios de creación, que ya
 * lo hacían desde una revisión anterior) — ver inventario de este lote.
 * Los filtros de reporte ocultan inactivos siempre, sin interruptor (ese
 * es exclusivo de administración del catálogo).
 */
class InactiveCatalogSelectorTest extends TestCase
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

    public function test_dashboards_program_unit_and_activity_filters_hide_inactive(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $activeProgram = ProgramUnit::factory()->create(['name' => 'Programa activo']);
        ProgramUnit::factory()->create(['name' => 'Programa inactivo', 'is_active' => false]);
        Activity::factory()->create(['name' => 'Actividad activa']);
        Activity::factory()->create(['name' => 'Actividad inactiva', 'is_active' => false]);

        Livewire::actingAs($coordination)
            ->test(Dashboard::class)
            ->assertViewHas('programUnits', fn ($units) => $units->pluck('name')->contains('Programa activo')
                && ! $units->pluck('name')->contains('Programa inactivo'))
            ->assertViewHas('filterableActivities', fn ($activities) => $activities->pluck('name')->contains('Actividad activa')
                && ! $activities->pluck('name')->contains('Actividad inactiva'));
    }

    public function test_activity_reports_filter_hides_inactive_and_never_autoselects_one(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $inactiveActivity = Activity::factory()->create(['is_active' => false]);
        TeacherAssignment::factory()->create(['activity_id' => $inactiveActivity->id, 'academic_period_id' => $period->id]);
        $activeActivity = Activity::factory()->create(['name' => 'Actividad activa']);

        $component = Livewire::actingAs($coordination)
            ->test(ActivityReport::class)
            ->set('periodFilter', $period->id);

        $component->assertViewHas('activities', fn ($activities) => ! $activities->contains('id', $inactiveActivity->id));
        $this->assertNotEquals($inactiveActivity->id, $component->get('activityFilter'));
    }

    public function test_consolidated_reports_program_and_activity_filters_hide_inactive(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        ProgramUnit::factory()->create(['name' => 'Programa inactivo', 'is_active' => false]);
        Activity::factory()->create(['name' => 'Actividad inactiva', 'is_active' => false]);

        Livewire::actingAs($coordination)
            ->test(ConsolidatedReport::class)
            ->assertViewHas('programUnits', fn ($units) => ! $units->pluck('name')->contains('Programa inactivo'))
            ->assertViewHas('activities', fn ($activities) => ! $activities->pluck('name')->contains('Actividad inactiva'));
    }

    public function test_cross_cutting_reports_commitment_filter_hides_inactive(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        CrossCuttingCommitment::factory()->create(['name' => 'Compromiso inactivo', 'is_active' => false]);

        Livewire::actingAs($coordination)
            ->test(CrossCuttingReport::class)
            ->assertViewHas('commitments', fn ($commitments) => ! $commitments->pluck('name')->contains('Compromiso inactivo'));
    }

    /**
     * template_id es un ayudante de un solo uso para "copiar los valores
     * de esta plantilla" durante el asistente (ver el bug corregido en
     * la revisión anterior, "volver a sin plantilla restablece todo") —
     * nunca se restaura desde deliverable_template_id al editar, así que
     * el selector de plantilla siempre arranca vacío en edición. Aun así,
     * el selector preserva la plantilla actualmente elegida en la MISMA
     * sesión del formulario aunque se desactive mientras se edita.
     */
    public function test_the_template_select_preserves_the_currently_chosen_template_even_if_it_becomes_inactive_mid_session(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        $template = DeliverableTemplate::factory()->create();

        $component = Livewire::actingAs($coordination)
            ->test(DeliverableForm::class)
            ->set('template_id', $template->id);

        $template->update(['is_active' => false]);

        $component->assertViewHas('templates', fn ($templates) => $templates->contains('id', $template->id));
    }

    /**
     * "Datos históricos: un entregable... que ya referencia un registro
     * inactivo sigue mostrando su nombre; solo deja de ofrecerse como
     * opción nueva." — nunca se rompe por una relación Eloquent normal,
     * no por un selector.
     */
    public function test_a_deliverable_still_shows_the_name_of_its_now_inactive_activity(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $activity = Activity::factory()->create(['name' => 'Actividad Histórica']);
        $deliverable = Deliverable::factory()->create(['activity_id' => $activity->id, 'name' => 'Entregable Histórico']);

        $activity->update(['is_active' => false]);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\Deliverables\DeliverableIndex::class)
            ->assertSee('Entregable Histórico')
            ->assertSee('Actividad Histórica');
    }
}
