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

class CatalogManagementTest extends TestCase
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

    public function test_leader_cannot_manage_catalogs(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);

        $this->actingAs($leader)->get('/catalogs/components')->assertForbidden();
    }

    public function test_coordination_can_create_a_component(): void
    {
        $coordination = $this->userWithRole(RoleName::Coordination);

        Livewire::actingAs($coordination)
            ->test(ComponentIndex::class)
            ->set('name', 'Bienestar institucional')
            ->call('save');

        $this->assertDatabaseHas('components', ['name' => 'Bienestar institucional', 'is_active' => true]);
    }

    public function test_deactivating_a_component_does_not_delete_it(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(ComponentIndex::class)
            ->call('toggleActive', $component);

        $this->assertDatabaseHas('components', ['id' => $component->id, 'is_active' => false]);
    }

    public function test_subcomponent_requires_a_valid_component(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(SubcomponentIndex::class)
            ->set('name', 'Procesos OACA')
            ->set('component_id', null)
            ->call('save')
            ->assertHasErrors('component_id');
    }

    public function test_activity_subcomponent_must_belong_to_the_chosen_component(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $componentA = Component::factory()->create();
        $componentB = Component::factory()->create();
        $subcomponentOfB = Subcomponent::factory()->create(['component_id' => $componentB->id]);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->set('name', 'Actividad cruzada')
            ->set('component_id', $componentA->id)
            ->set('subcomponent_id', $subcomponentOfB->id)
            ->call('save')
            ->assertHasErrors('subcomponent_id');
    }

    public function test_activity_can_be_created_without_a_subcomponent(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create();

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->set('name', 'Clases teóricas')
            ->set('component_id', $component->id)
            ->set('subcomponent_id', null)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('activities', [
            'name' => 'Clases teóricas',
            'component_id' => $component->id,
            'subcomponent_id' => null,
        ]);
    }

    public function test_deactivating_an_activity_preserves_historical_reference(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $activity = Activity::factory()->create(['is_active' => true]);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->call('toggleActive', $activity);

        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'is_active' => false]);
    }

    // Regresión: un intento de creación fallido no debe dejar errores de
    // validación visibles al abrir "Editar" sobre un registro válido —
    // ver [[project-livewire4-gotchas]].

    public function test_component_edit_does_not_inherit_errors_from_a_failed_create(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create(['name' => 'Docencia']);

        $test = Livewire::actingAs($admin)
            ->test(ComponentIndex::class)
            ->call('openCreate')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors('name')
            ->call('closeModal')
            ->assertHasNoErrors();

        $test->call('openEdit', $component)
            ->assertHasNoErrors()
            ->assertSet('name', 'Docencia');
    }

    public function test_subcomponent_edit_does_not_inherit_errors_from_a_failed_create(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create();
        $subcomponent = Subcomponent::factory()->create(['component_id' => $component->id, 'name' => 'Procesos OACA']);

        Livewire::actingAs($admin)
            ->test(SubcomponentIndex::class)
            ->call('openCreate')
            ->call('save')
            ->assertHasErrors(['name', 'component_id'])
            ->call('openEdit', $subcomponent)
            ->assertHasNoErrors()
            ->assertSet('name', 'Procesos OACA');
    }

    public function test_activity_edit_does_not_inherit_errors_from_a_failed_create(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $activity = Activity::factory()->create(['name' => 'Clases teóricas']);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->call('openCreate')
            ->call('save')
            ->assertHasErrors(['name', 'component_id'])
            ->call('openEdit', $activity)
            ->assertHasNoErrors()
            ->assertSet('name', 'Clases teóricas');
    }

    public function test_program_unit_edit_does_not_inherit_errors_from_a_failed_create(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $programUnit = ProgramUnit::factory()->create(['name' => 'Facultad de Ciencias Naturales']);

        Livewire::actingAs($admin)
            ->test(ProgramUnitIndex::class)
            ->call('openCreate')
            ->call('save')
            ->assertHasErrors('name')
            ->call('openEdit', $programUnit)
            ->assertHasNoErrors()
            ->assertSet('name', 'Facultad de Ciencias Naturales');
    }

    public function test_cross_cutting_commitment_edit_does_not_inherit_errors_from_a_failed_create(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $commitment = CrossCuttingCommitment::factory()->create(['name' => 'Bienestar y desarrollo humano']);

        Livewire::actingAs($admin)
            ->test(CrossCuttingCommitmentIndex::class)
            ->call('openCreate')
            ->call('save')
            ->assertHasErrors('name')
            ->call('openEdit', $commitment)
            ->assertHasNoErrors()
            ->assertSet('name', 'Bienestar y desarrollo humano');
    }

    public function test_component_name_is_rejected_regardless_of_case_or_accents(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        Component::factory()->create(['name' => 'Inducción']);

        Livewire::actingAs($admin)
            ->test(ComponentIndex::class)
            ->set('name', 'INDUCCION')
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_editing_a_component_does_not_flag_its_own_unchanged_name(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create(['name' => 'Inducción']);

        Livewire::actingAs($admin)
            ->test(ComponentIndex::class)
            ->call('openEdit', $component)
            ->set('is_active', false)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_program_unit_name_is_rejected_regardless_of_case_or_accents(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);

        Livewire::actingAs($admin)
            ->test(ProgramUnitIndex::class)
            ->set('name', 'ingenieria de sistemas')
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_cross_cutting_commitment_name_is_rejected_regardless_of_case_or_accents(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        CrossCuttingCommitment::factory()->create(['name' => 'Bienestar y desarrollo humano']);

        Livewire::actingAs($admin)
            ->test(CrossCuttingCommitmentIndex::class)
            ->set('name', 'BIENESTAR Y DESARROLLO HUMANO')
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_subcomponent_name_is_rejected_regardless_of_case_or_accents_within_the_same_component(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create();
        Subcomponent::factory()->create(['component_id' => $component->id, 'name' => 'Procesos OACA']);

        Livewire::actingAs($admin)
            ->test(SubcomponentIndex::class)
            ->set('name', 'procesos oaca')
            ->set('component_id', $component->id)
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_subcomponent_name_is_allowed_to_repeat_under_a_different_component(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $componentA = Component::factory()->create();
        $componentB = Component::factory()->create();
        Subcomponent::factory()->create(['component_id' => $componentA->id, 'name' => 'Procesos OACA']);

        Livewire::actingAs($admin)
            ->test(SubcomponentIndex::class)
            ->set('name', 'Procesos OACA')
            ->set('component_id', $componentB->id)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_activity_name_is_rejected_regardless_of_case_or_accents_within_the_same_scope(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create();
        $subcomponent = Subcomponent::factory()->create(['component_id' => $component->id]);
        Activity::factory()->create([
            'component_id' => $component->id,
            'subcomponent_id' => $subcomponent->id,
            'name' => 'Inducción docente',
        ]);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->set('name', 'INDUCCION DOCENTE')
            ->set('component_id', $component->id)
            ->set('subcomponent_id', $subcomponent->id)
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_activity_name_is_allowed_to_repeat_under_a_different_subcomponent(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create();
        $subcomponentA = Subcomponent::factory()->create(['component_id' => $component->id]);
        $subcomponentB = Subcomponent::factory()->create(['component_id' => $component->id]);
        Activity::factory()->create([
            'component_id' => $component->id,
            'subcomponent_id' => $subcomponentA->id,
            'name' => 'Inducción docente',
        ]);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->set('name', 'Inducción docente')
            ->set('component_id', $component->id)
            ->set('subcomponent_id', $subcomponentB->id)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_activity_name_without_a_subcomponent_is_rejected_regardless_of_case_when_repeated(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $component = Component::factory()->create();
        Activity::factory()->create([
            'component_id' => $component->id,
            'subcomponent_id' => null,
            'name' => 'Clases teóricas',
        ]);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->set('name', 'CLASES TEORICAS')
            ->set('component_id', $component->id)
            ->set('subcomponent_id', null)
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_closing_the_component_modal_resets_the_form(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(ComponentIndex::class)
            ->call('openCreate')
            ->set('name', 'Texto sin guardar')
            ->call('closeModal')
            ->assertSet('name', '')
            ->assertSet('showModal', false);
    }

    public function test_activity_search_filters_by_name(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        Activity::factory()->create(['name' => 'Clases teóricas']);
        Activity::factory()->create(['name' => 'Dirección de trabajos de grado']);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->set('search', 'teóricas')
            ->assertViewHas('activities', fn ($activities) => $activities->count() === 1
                && $activities->first()->name === 'Clases teóricas');
    }

    public function test_activity_search_is_accent_insensitive(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        Activity::factory()->create(['name' => 'Clases teóricas']);
        Activity::factory()->create(['name' => 'Dirección de trabajos de grado']);

        Livewire::actingAs($admin)
            ->test(ActivityIndex::class)
            ->set('search', 'teoricas')
            ->assertViewHas('activities', fn ($activities) => $activities->count() === 1
                && $activities->first()->name === 'Clases teóricas');
    }

    public function test_activity_index_paginates_at_25_per_page(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        Activity::factory()->count(26)->create();

        $test = Livewire::actingAs($admin)->test(ActivityIndex::class);

        $test->assertViewHas('activities', function ($activities) {
            return $activities->perPage() === 25
                && $activities->count() === 25
                && $activities->total() === 26
                && $activities->hasMorePages();
        });

        $test->assertSee('Siguiente');
    }

    public function test_subcomponent_search_filters_by_name(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        Subcomponent::factory()->create(['name' => 'Procesos OACA']);
        Subcomponent::factory()->create(['name' => 'Procesos ODA']);

        Livewire::actingAs($admin)
            ->test(SubcomponentIndex::class)
            ->set('search', 'OACA')
            ->assertViewHas('subcomponents', fn ($subcomponents) => $subcomponents->count() === 1
                && $subcomponents->first()->name === 'Procesos OACA');
    }

    public function test_subcomponent_search_is_accent_insensitive(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        Subcomponent::factory()->create(['name' => 'Inducción docente']);
        Subcomponent::factory()->create(['name' => 'Procesos ODA']);

        Livewire::actingAs($admin)
            ->test(SubcomponentIndex::class)
            ->set('search', 'induccion')
            ->assertViewHas('subcomponents', fn ($subcomponents) => $subcomponents->count() === 1
                && $subcomponents->first()->name === 'Inducción docente');
    }
}
