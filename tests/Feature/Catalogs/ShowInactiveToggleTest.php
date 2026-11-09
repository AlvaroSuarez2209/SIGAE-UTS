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
 * Revisión de la directora, punto 1: cada pantalla de administración de
 * catálogo tiene su propio interruptor "Mostrar inactivos", apagado por
 * defecto (antes era un <select> de 3 estados cuyo valor por defecto
 * mostraba TODO, justo lo contrario de lo pedido). El estado no se
 * comparte entre pantallas: cada una arranca apagada sin importar lo que
 * hagan las demás.
 */
class ShowInactiveToggleTest extends TestCase
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

    public function test_component_index_hides_inactive_by_default_and_shows_them_when_toggled(): void
    {
        $admin = $this->admin();
        $active = Component::factory()->create(['name' => 'Docencia activa']);
        $inactive = Component::factory()->create(['name' => 'Investigación inactiva', 'is_active' => false]);

        Livewire::actingAs($admin)
            ->test(ComponentIndex::class)
            ->assertSee('Docencia activa')
            ->assertDontSee('Investigación inactiva')
            ->set('showInactive', true)
            ->assertSee('Docencia activa')
            ->assertSee('Investigación inactiva');
    }

    public function test_subcomponent_index_hides_inactive_by_default_and_shows_them_when_toggled(): void
    {
        $admin = $this->admin();
        $component = Component::factory()->create();
        Subcomponent::factory()->create(['component_id' => $component->id, 'name' => 'Procesos activos']);
        Subcomponent::factory()->create(['component_id' => $component->id, 'name' => 'Procesos inactivos', 'is_active' => false]);

        Livewire::actingAs($admin)
            ->test(SubcomponentIndex::class)
            ->assertSee('Procesos activos')
            ->assertDontSee('Procesos inactivos')
            ->set('showInactive', true)
            ->assertSee('Procesos inactivos');
    }

    public function test_activity_index_hides_inactive_by_default_and_shows_them_when_toggled(): void
    {
        $admin = $this->admin();
        Activity::factory()->create(['name' => 'Clases activas']);
        Activity::factory()->create(['name' => 'Clases inactivas', 'is_active' => false]);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->assertSee('Clases activas')
            ->assertDontSee('Clases inactivas')
            ->set('showInactive', true)
            ->assertSee('Clases inactivas');
    }

    public function test_program_unit_index_hides_inactive_by_default_and_shows_them_when_toggled(): void
    {
        $admin = $this->admin();
        ProgramUnit::factory()->create(['name' => 'Programa activo']);
        ProgramUnit::factory()->create(['name' => 'Programa inactivo', 'is_active' => false]);

        Livewire::actingAs($admin)
            ->test(ProgramUnitIndex::class)
            ->assertSee('Programa activo')
            ->assertDontSee('Programa inactivo')
            ->set('showInactive', true)
            ->assertSee('Programa inactivo');
    }

    public function test_cross_cutting_commitment_index_hides_inactive_by_default_and_shows_them_when_toggled(): void
    {
        $admin = $this->admin();
        CrossCuttingCommitment::factory()->create(['name' => 'Compromiso activo']);
        CrossCuttingCommitment::factory()->create(['name' => 'Compromiso inactivo', 'is_active' => false]);

        Livewire::actingAs($admin)
            ->test(CrossCuttingCommitmentIndex::class)
            ->assertSee('Compromiso activo')
            ->assertDontSee('Compromiso inactivo')
            ->set('showInactive', true)
            ->assertSee('Compromiso inactivo');
    }

    /**
     * El interruptor es por pantalla, no global — encenderlo en
     * Componentes nunca debe afectar lo que muestra Actividades.
     */
    public function test_the_toggle_state_is_never_shared_between_screens(): void
    {
        $admin = $this->admin();
        Component::factory()->create(['name' => 'Componente inactivo', 'is_active' => false]);
        Activity::factory()->create(['name' => 'Actividad inactiva', 'is_active' => false]);

        Livewire::actingAs($admin)
            ->test(ComponentIndex::class)
            ->set('showInactive', true)
            ->assertSee('Componente inactivo');

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->assertDontSee('Actividad inactiva');
    }

    /**
     * Las dos casillas del modal de Subcomponentes/Actividades (a qué
     * componente/subcomponente pertenece) también son "selectores de
     * uso": ocultan inactivos por defecto, SIN depender del interruptor
     * de la propia pantalla.
     */
    public function test_the_component_select_inside_the_subcomponent_modal_hides_inactive_components(): void
    {
        $admin = $this->admin();
        $active = Component::factory()->create(['name' => 'Componente activo']);
        $inactive = Component::factory()->create(['name' => 'Componente inactivo', 'is_active' => false]);

        Livewire::actingAs($admin)
            ->test(SubcomponentIndex::class)
            ->assertViewHas('components', fn ($components) => $components->contains('id', $active->id)
                && ! $components->contains('id', $inactive->id));
    }

    /**
     * Edición: si el componente de un subcomponente ya guardado se
     * desactivó después, el modal de edición debe seguir ofreciéndolo
     * (para no romper el valor ya guardado), aunque no aparezca para
     * crear uno nuevo.
     */
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

    public function test_the_subcomponent_select_inside_the_activity_modal_hides_inactive_subcomponents(): void
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
