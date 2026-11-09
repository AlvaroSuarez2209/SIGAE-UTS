<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Livewire\Dashboard;
use App\Livewire\Deliverables\DeliverableForm;
use App\Livewire\Distribution\AssignmentForm;
use App\Livewire\Reports\ActivityReport;
use App\Livewire\Reports\ConsolidatedReport;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Component;
use App\Models\Deliverable;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\Subcomponent;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\ActivitySelectOptions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Corrección posterior a LOTE 1/6/13: los 5 selectores de actividad del
 * sistema (DeliverableForm, AssignmentForm, ActivityReport,
 * ConsolidatedReport, Dashboard) ahora ordenan por componente ->
 * subcomponente -> actividad (insensible a tildes/mayúsculas) y agrupan
 * con <optgroup> por componente, vía el helper centralizado
 * Activity::orderedByHierarchy() + ActivitySelectOptions::grouped(). La
 * cascada de LeadershipForm queda fuera a propósito (ya ordena cada
 * nivel por separado, ver LeadershipCascadeTest).
 */
class ActivitySelectorTest extends TestCase
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
     * @return array{componentA: Component, componentZ: Component, subAlvaro: Subcomponent, subBeta: Subcomponent}
     */
    private function hierarchyFixture(): array
    {
        // "Ángel" antes que "Zonas..." (A antes de Z); "Álvaro" antes
        // que "Beta" dentro de "Ángel" — mezcla tildes y mayúsculas a
        // propósito.
        $componentA = Component::factory()->create(['name' => 'Ángel']);
        $componentZ = Component::factory()->create(['name' => 'Zonas de bienestar']);
        $subAlvaro = Subcomponent::factory()->create(['component_id' => $componentA->id, 'name' => 'Álvaro']);
        $subBeta = Subcomponent::factory()->create(['component_id' => $componentA->id, 'name' => 'Beta']);

        Activity::factory()->create(['component_id' => $componentA->id, 'subcomponent_id' => null, 'name' => 'Zeta sin subcomponente']);
        Activity::factory()->create(['component_id' => $componentA->id, 'subcomponent_id' => $subBeta->id, 'name' => 'Última']);
        Activity::factory()->create(['component_id' => $componentA->id, 'subcomponent_id' => $subAlvaro->id, 'name' => 'Primera']);
        Activity::factory()->create(['component_id' => $componentZ->id, 'subcomponent_id' => null, 'name' => 'Solitaria']);

        return compact('componentA', 'componentZ', 'subAlvaro', 'subBeta');
    }

    public function test_activity_select_options_groups_by_component_and_orders_hierarchically(): void
    {
        $this->hierarchyFixture();

        $activities = Activity::with(['component', 'subcomponent'])->orderedByHierarchy()->get();
        $groups = ActivitySelectOptions::grouped($activities);

        $this->assertSame(['Ángel', 'Zonas de bienestar'], $groups->pluck('component')->all());

        $angelOptions = $groups->firstWhere('component', 'Ángel')['options'];
        $this->assertSame([
            'Zeta sin subcomponente', // sin subcomponente: va primero dentro del grupo.
            'Álvaro / Primera',
            'Beta / Última',
        ], $angelOptions->pluck('label')->all());

        $zonasOptions = $groups->firstWhere('component', 'Zonas de bienestar')['options'];
        $this->assertSame(['Solitaria'], $zonasOptions->pluck('label')->all());
    }

    public function test_deliverable_form_activity_select_is_grouped_and_ordered_by_hierarchy(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $this->hierarchyFixture();

        $html = Livewire::actingAs($admin)->test(DeliverableForm::class)->html();

        $this->assertStringContainsString('<optgroup label="Ángel">', $html);
        $this->assertStringContainsString('<optgroup label="Zonas de bienestar">', $html);
        $this->assertTrue(
            strpos($html, '<optgroup label="Ángel">') < strpos($html, '<optgroup label="Zonas de bienestar">'),
            'El grupo "Ángel" debe aparecer antes que "Zonas de bienestar".'
        );
        $this->assertStringContainsString('Álvaro / Primera', $html);
        $this->assertStringContainsString('Beta / Última', $html);
    }

    public function test_assignment_form_activity_select_is_grouped_and_ordered_by_hierarchy(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $this->hierarchyFixture();

        $html = Livewire::actingAs($admin)->test(AssignmentForm::class)->html();

        $this->assertStringContainsString('<optgroup label="Ángel">', $html);
        $this->assertStringContainsString('<optgroup label="Zonas de bienestar">', $html);
        $this->assertStringContainsString('Álvaro / Primera', $html);
    }

    public function test_activity_report_activity_select_is_grouped_and_ordered_by_hierarchy(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $this->hierarchyFixture();

        $html = Livewire::actingAs($admin)->test(ActivityReport::class)->html();

        $this->assertStringContainsString('<optgroup label="Ángel">', $html);
        $this->assertStringContainsString('<optgroup label="Zonas de bienestar">', $html);
    }

    public function test_consolidated_report_activity_select_is_grouped_and_ordered_by_hierarchy(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $this->hierarchyFixture();

        $html = Livewire::actingAs($admin)->test(ConsolidatedReport::class)->html();

        $this->assertStringContainsString('<optgroup label="Ángel">', $html);
        $this->assertStringContainsString('<optgroup label="Zonas de bienestar">', $html);
    }

    public function test_dashboard_coordination_activity_filter_is_grouped_and_ordered_by_hierarchy(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);
        AcademicPeriod::factory()->create(['status' => 'active']);
        $this->hierarchyFixture();

        $html = Livewire::actingAs($coordination)->test(Dashboard::class)->html();

        $this->assertStringContainsString('<optgroup label="Ángel">', $html);
        $this->assertStringContainsString('<optgroup label="Zonas de bienestar">', $html);
    }

    /**
     * Ya implementado desde el punto 1 original: al editar, el selector
     * de actividad sigue ofreciendo la actividad guardada aunque se haya
     * desactivado después — verificado aquí tras el cambio de ordenByAccentInsensitive()
     * a orderedByHierarchy() para confirmar que ese comportamiento no se rompió.
     */
    public function test_deliverable_form_still_offers_a_now_inactive_saved_activity_when_editing(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $activity = Activity::factory()->create(['is_active' => false]);
        $deliverable = Deliverable::factory()->create(['activity_id' => $activity->id]);

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class, ['deliverable' => $deliverable])
            ->assertViewHas('activities', fn ($activities) => $activities->contains('id', $activity->id));
    }

    public function test_assignment_form_still_offers_a_now_inactive_saved_activity_when_editing(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $activity = Activity::factory()->create(['is_active' => false]);
        $period = AcademicPeriod::factory()->create();
        $programUnit = ProgramUnit::factory()->create();
        $assignment = TeacherAssignment::factory()->create([
            'activity_id' => $activity->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => $programUnit->id,
        ]);

        Livewire::actingAs($admin)
            ->test(AssignmentForm::class, ['teacherAssignment' => $assignment])
            ->assertViewHas('activities', fn ($activities) => $activities->contains('id', $activity->id));
    }
}
