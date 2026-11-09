<?php

namespace Tests\Feature\Catalogs;

use App\Enums\RoleName;
use App\Livewire\Catalogs\ActivityIndex;
use App\Livewire\Catalogs\ComponentIndex;
use App\Livewire\Catalogs\CrossCuttingCommitmentIndex;
use App\Livewire\Catalogs\ProgramUnitIndex;
use App\Livewire\Catalogs\SubcomponentIndex;
use App\Models\Activity;
use App\Models\Component;
use App\Models\CrossCuttingCommitment;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\Subcomponent;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Corrección tras pruebas manuales al punto 1: se revirtió el
 * interruptor "Mostrar inactivos" — ahora los inactivos SIEMPRE
 * aparecen en estas listas (con su insignia de estado), para poder
 * editarlos/reactivarlos sin un paso extra. El filtro de 3 estados
 * (Todos/Activos/Inactivos) solo acota la lista que ya los muestra a
 * todos por defecto. Reemplaza a ShowInactiveToggleTest.
 */
class CatalogStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', RoleName::Administrator->value)->first());

        return $user;
    }

    public function test_component_index_always_shows_inactive_records_with_their_badge(): void
    {
        $admin = $this->admin();
        Component::factory()->create(['name' => 'Docencia activa']);
        Component::factory()->create(['name' => 'Investigación inactiva', 'is_active' => false]);

        Livewire::actingAs($admin)
            ->test(ComponentIndex::class)
            ->assertSee('Docencia activa')
            ->assertSee('Investigación inactiva')
            ->assertSee('Inactivo');
    }

    public function test_component_index_status_filter_narrows_the_always_visible_list(): void
    {
        $admin = $this->admin();
        Component::factory()->create(['name' => 'Docencia activa']);
        Component::factory()->create(['name' => 'Investigación inactiva', 'is_active' => false]);

        Livewire::actingAs($admin)
            ->test(ComponentIndex::class)
            ->set('statusFilter', 'active')
            ->assertSee('Docencia activa')
            ->assertDontSee('Investigación inactiva')
            ->set('statusFilter', 'inactive')
            ->assertDontSee('Docencia activa')
            ->assertSee('Investigación inactiva');
    }

    public function test_reactivating_from_the_list_still_works(): void
    {
        $admin = $this->admin();
        $inactive = Component::factory()->create(['name' => 'Investigación inactiva', 'is_active' => false]);

        Livewire::actingAs($admin)
            ->test(ComponentIndex::class)
            ->call('toggleActive', $inactive)
            ->assertSee('Investigación inactiva');

        $this->assertTrue($inactive->refresh()->is_active);
    }

    public function test_subcomponent_index_always_shows_inactive_records(): void
    {
        $admin = $this->admin();
        $component = Component::factory()->create();
        Subcomponent::factory()->create(['component_id' => $component->id, 'name' => 'Procesos activos']);
        Subcomponent::factory()->create(['component_id' => $component->id, 'name' => 'Procesos inactivos', 'is_active' => false]);

        Livewire::actingAs($admin)
            ->test(SubcomponentIndex::class)
            ->assertSee('Procesos activos')
            ->assertSee('Procesos inactivos');
    }

    public function test_activity_index_always_shows_inactive_records(): void
    {
        $admin = $this->admin();
        Activity::factory()->create(['name' => 'Clases activas']);
        Activity::factory()->create(['name' => 'Clases inactivas', 'is_active' => false]);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->assertSee('Clases activas')
            ->assertSee('Clases inactivas');
    }

    public function test_program_unit_index_always_shows_inactive_records(): void
    {
        $admin = $this->admin();
        ProgramUnit::factory()->create(['name' => 'Programa activo']);
        ProgramUnit::factory()->create(['name' => 'Programa inactivo', 'is_active' => false]);

        Livewire::actingAs($admin)
            ->test(ProgramUnitIndex::class)
            ->assertSee('Programa activo')
            ->assertSee('Programa inactivo');
    }

    public function test_cross_cutting_commitment_index_always_shows_inactive_records(): void
    {
        $admin = $this->admin();
        CrossCuttingCommitment::factory()->create(['name' => 'Compromiso activo']);
        CrossCuttingCommitment::factory()->create(['name' => 'Compromiso inactivo', 'is_active' => false]);

        Livewire::actingAs($admin)
            ->test(CrossCuttingCommitmentIndex::class)
            ->assertSee('Compromiso activo')
            ->assertSee('Compromiso inactivo');
    }

    /**
     * Los "selectores de uso" dentro de los modales (a qué
     * componente/subcomponente pertenece) siguen ocultando inactivos por
     * defecto — esto no cambia con la reversión del punto 1.
     */
    public function test_the_component_select_inside_the_subcomponent_modal_still_hides_inactive_components(): void
    {
        $admin = $this->admin();
        $active = Component::factory()->create(['name' => 'Componente activo']);
        $inactive = Component::factory()->create(['name' => 'Componente inactivo', 'is_active' => false]);

        Livewire::actingAs($admin)
            ->test(SubcomponentIndex::class)
            ->assertViewHas('components', fn ($components) => $components->contains('id', $active->id)
                && ! $components->contains('id', $inactive->id));
    }

    public function test_editing_a_subcomponent_whose_component_is_now_inactive_still_offers_it(): void
    {
        $admin = $this->admin();
        $component = Component::factory()->create(['is_active' => false]);
        $subcomponent = Subcomponent::factory()->create(['component_id' => $component->id, 'is_active' => true]);

        Livewire::actingAs($admin)
            ->test(SubcomponentIndex::class)
            ->call('openEdit', $subcomponent)
            ->assertViewHas('components', fn ($components) => $components->contains('id', $component->id));
    }

    public function test_the_subcomponent_select_inside_the_activity_modal_still_hides_inactive_subcomponents(): void
    {
        $admin = $this->admin();
        $component = Component::factory()->create();
        $active = Subcomponent::factory()->create(['component_id' => $component->id]);
        $inactive = Subcomponent::factory()->create(['component_id' => $component->id, 'is_active' => false]);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->set('component_id', $component->id)
            ->assertViewHas('availableSubcomponents', fn ($subcomponents) => $subcomponents->contains('id', $active->id)
                && ! $subcomponents->contains('id', $inactive->id));
    }

    public function test_editing_an_activity_whose_subcomponent_is_now_inactive_still_offers_it(): void
    {
        $admin = $this->admin();
        $component = Component::factory()->create();
        $subcomponent = Subcomponent::factory()->create(['component_id' => $component->id, 'is_active' => false]);
        $activity = Activity::factory()->create([
            'component_id' => $component->id,
            'subcomponent_id' => $subcomponent->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->call('openEdit', $activity)
            ->assertViewHas('availableSubcomponents', fn ($subcomponents) => $subcomponents->contains('id', $subcomponent->id));
    }

    public function test_editing_an_activity_whose_component_is_now_inactive_still_offers_it(): void
    {
        $admin = $this->admin();
        $component = Component::factory()->create(['is_active' => false]);
        $activity = Activity::factory()->create(['component_id' => $component->id]);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->call('openEdit', $activity)
            ->assertViewHas('components', fn ($components) => $components->contains('id', $component->id));
    }
}
