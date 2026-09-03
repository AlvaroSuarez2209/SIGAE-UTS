<?php

namespace Tests\Feature\Catalogs;

use App\Enums\RoleName;
use App\Livewire\Catalogs\ActivityIndex;
use App\Livewire\Catalogs\ComponentIndex;
use App\Livewire\Catalogs\SubcomponentIndex;
use App\Models\Activity;
use App\Models\Component;
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
}
