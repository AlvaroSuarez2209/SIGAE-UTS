<?php

namespace Tests\Feature\Profile;

use App\Enums\RoleName;
use App\Livewire\Profile;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
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

    public function test_any_authenticated_user_can_view_their_own_profile(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)->get('/profile')->assertOk();
    }

    public function test_form_is_prefilled_with_the_current_users_data(): void
    {
        $user = $this->userWithRole(RoleName::Teacher, [
            'name' => 'Nombre Original',
            'document_number' => '123456789',
            'email' => 'original@sigae.local',
        ]);

        Livewire::actingAs($user)->test(Profile::class)
            ->assertSet('name', 'Nombre Original')
            ->assertSet('document_number', '123456789')
            ->assertSet('email', 'original@sigae.local');
    }

    public function test_user_can_update_their_own_profile_information(): void
    {
        $user = $this->userWithRole(RoleName::Teacher);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('name', 'Nombre Nuevo')
            ->set('document_number', '999888777')
            ->set('email', 'nuevo@sigae.local')
            ->call('saveProfile')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertEquals('Nombre Nuevo', $user->name);
        $this->assertEquals('999888777', $user->document_number);
        $this->assertEquals('nuevo@sigae.local', $user->email);
    }

    public function test_profile_form_has_no_roles_or_active_account_fields(): void
    {
        $user = $this->userWithRole(RoleName::Teacher);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertDontSee('Roles');
        $response->assertDontSee('Cuenta activa');
    }

    public function test_email_must_be_unique_excluding_the_current_user(): void
    {
        $user = $this->userWithRole(RoleName::Teacher, ['email' => 'mio@sigae.local']);
        $other = $this->userWithRole(RoleName::Teacher, ['email' => 'otro@sigae.local']);

        // Guardar el propio correo sin cambios no debe fallar por "único".
        Livewire::actingAs($user)->test(Profile::class)
            ->set('email', 'mio@sigae.local')
            ->call('saveProfile')
            ->assertHasNoErrors();

        // Pero tomar el correo de otro usuario sí debe fallar.
        Livewire::actingAs($user)->test(Profile::class)
            ->set('email', 'otro@sigae.local')
            ->call('saveProfile')
            ->assertHasErrors('email');

        $this->assertEquals('otro@sigae.local', $other->fresh()->email);
    }

    public function test_updating_password_requires_the_correct_current_password(): void
    {
        $user = $this->userWithRole(RoleName::Teacher, ['password' => 'clave-original-A1']);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('current_password', 'clave-equivocada')
            ->set('password', 'ClaveNueva123')
            ->set('password_confirmation', 'ClaveNueva123')
            ->call('savePassword')
            ->assertHasErrors('current_password');

        $this->assertTrue(Hash::check('clave-original-A1', $user->fresh()->password));
    }

    public function test_new_password_must_meet_the_complexity_requirements(): void
    {
        $user = $this->userWithRole(RoleName::Teacher, ['password' => 'clave-original-A1']);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('current_password', 'clave-original-A1')
            ->set('password', 'todaminuscula1')
            ->set('password_confirmation', 'todaminuscula1')
            ->call('savePassword')
            ->assertHasErrors('password');
    }

    public function test_new_password_must_be_confirmed(): void
    {
        $user = $this->userWithRole(RoleName::Teacher, ['password' => 'clave-original-A1']);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('current_password', 'clave-original-A1')
            ->set('password', 'ClaveNueva123')
            ->set('password_confirmation', 'no-coincide')
            ->call('savePassword')
            ->assertHasErrors('password');
    }

    public function test_user_can_update_their_own_password(): void
    {
        $user = $this->userWithRole(RoleName::Teacher, ['password' => 'clave-original-A1']);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('current_password', 'clave-original-A1')
            ->set('password', 'ClaveNueva123')
            ->set('password_confirmation', 'ClaveNueva123')
            ->call('savePassword')
            ->assertHasNoErrors()
            ->assertSet('current_password', '')
            ->assertSet('password', '')
            ->assertSet('password_confirmation', '');

        $this->assertTrue(Hash::check('ClaveNueva123', $user->fresh()->password));
    }

    public function test_updating_password_does_not_affect_the_profile_information_form_state(): void
    {
        $user = $this->userWithRole(RoleName::Teacher, [
            'name' => 'Nombre Original',
            'password' => 'clave-original-A1',
        ]);

        $component = Livewire::actingAs($user)->test(Profile::class)
            ->set('name', 'Nombre Sin Guardar')
            ->set('current_password', 'clave-original-A1')
            ->set('password', 'ClaveNueva123')
            ->set('password_confirmation', 'ClaveNueva123')
            ->call('savePassword')
            ->assertHasNoErrors();

        // La sección de contraseña se guardó y limpió; la de perfil, que
        // nunca se envió, conserva lo escrito sin guardarlo — son
        // formularios independientes.
        $component->assertSet('name', 'Nombre Sin Guardar');
        $this->assertEquals('Nombre Original', $user->fresh()->name);
    }
}
