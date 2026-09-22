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

    public function test_user_can_update_their_own_name(): void
    {
        $user = $this->userWithRole(RoleName::Teacher);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('name', 'Nombre Nuevo')
            ->call('saveProfile')
            ->assertHasNoErrors();

        $this->assertEquals('Nombre Nuevo', $user->fresh()->name);
    }

    public function test_document_number_and_email_are_read_only_fields_in_the_view(): void
    {
        $user = $this->userWithRole(RoleName::Teacher);

        $html = $this->actingAs($user)->get('/profile')->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*wire:model="document_number"[^>]*\bdisabled\b[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*wire:model="email"[^>]*\bdisabled\b[^>]*>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*wire:model="name"[^>]*\bdisabled\b[^>]*>/', $html);
        $this->assertStringContainsString('Si necesitas actualizar este dato, contacta a un Administrador.', $html);
    }

    public function test_saving_the_profile_never_changes_document_number_or_email_even_if_tampered_with(): void
    {
        $user = $this->userWithRole(RoleName::Teacher, [
            'document_number' => '111111111',
            'email' => 'original@sigae.local',
        ]);

        // Simula que alguien manipuló la petición de Livewire a mano para
        // escribir en propiedades que la interfaz deja deshabilitadas.
        Livewire::actingAs($user)->test(Profile::class)
            ->set('document_number', '999999999')
            ->set('email', 'cambiado@sigae.local')
            ->set('name', 'Nombre Nuevo')
            ->call('saveProfile')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertEquals('Nombre Nuevo', $user->name);
        $this->assertEquals('111111111', $user->document_number);
        $this->assertEquals('original@sigae.local', $user->email);
    }

    public function test_profile_form_has_no_roles_or_active_account_fields(): void
    {
        $user = $this->userWithRole(RoleName::Teacher);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertDontSee('Roles');
        $response->assertDontSee('Cuenta activa');
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
