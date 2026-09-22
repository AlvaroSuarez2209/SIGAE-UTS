<?php

namespace Tests\Feature\Profile;

use App\Enums\RoleName;
use App\Livewire\UserName;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(RoleName $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['is_active' => true], $attributes));
        $user->roles()->attach(Role::where('name', $role->value)->first());

        return $user;
    }

    public function test_mounts_with_the_authenticated_users_current_name(): void
    {
        $user = $this->userWithRole(RoleName::Teacher, ['name' => 'Nombre Original']);

        Livewire::actingAs($user)->test(UserName::class)
            ->assertSet('name', 'Nombre Original');
    }

    public function test_refreshes_the_name_when_it_receives_the_profile_updated_event(): void
    {
        $user = $this->userWithRole(RoleName::Teacher, ['name' => 'Nombre Original']);

        $component = Livewire::actingAs($user)->test(UserName::class)
            ->assertSet('name', 'Nombre Original');

        // Simula lo que Profile::saveProfile() ya hizo en la base de datos
        // antes de emitir el evento que este componente escucha.
        $user->update(['name' => 'Nombre Actualizado']);

        $component->call('refreshName')
            ->assertSet('name', 'Nombre Actualizado');
    }

    public function test_listens_for_the_profile_updated_event(): void
    {
        $user = $this->userWithRole(RoleName::Teacher, ['name' => 'Nombre Original']);

        $component = Livewire::actingAs($user)->test(UserName::class)
            ->assertSet('name', 'Nombre Original');

        $user->update(['name' => 'Nombre Actualizado']);

        $component->dispatch('profile-updated')
            ->assertSet('name', 'Nombre Actualizado');
    }
}
