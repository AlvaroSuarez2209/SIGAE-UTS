<?php

namespace Tests\Feature\Profile;

use App\Enums\RoleName;
use App\Livewire\Profile;
use App\Models\ProgramUnit;
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

    /**
     * Bloque de ajustes de interfaz, punto 7: dos formularios independientes
     * en la misma pantalla, cada uno sin ningún estado de carga ni
     * wire:target que los distinga — mismo patrón ya usado en
     * Login/InstitutionSettingsForm/TeacherImportWizard, uno por método.
     */
    public function test_both_save_buttons_disable_themselves_and_show_their_own_loading_state(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $html = Livewire::actingAs($teacher)->test(Profile::class)->html();

        $this->assertStringContainsString('wire:target="saveProfile"', $html);
        $this->assertStringContainsString('wire:target="savePassword"', $html);
        $this->assertStringContainsString('wire:loading.attr="disabled"', $html);
        $this->assertSame(2, substr_count($html, 'Guardando...'));
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

    /**
     * Bloque de ajustes de interfaz, punto 9: document_type y
     * program_unit_id se suman como campos de solo lectura, mismo
     * tratamiento visual que document_number/email.
     *
     * Revisión del diagnóstico de document_type/program_unit_id: muestra
     * la etiqueta completa (App\Enums\DocumentType), nunca el código
     * crudo "CC" — mismo mapa que ahora usan UserForm y AuditLogPresenter.
     */
    public function test_shows_document_type_and_program_unit_as_read_only_fields(): void
    {
        $programUnit = ProgramUnit::factory()->create(['name' => 'Ingeniería de Sistemas']);
        $user = $this->userWithRole(RoleName::Teacher, [
            'document_type' => 'CC',
            'program_unit_id' => $programUnit->id,
        ]);

        $html = Livewire::actingAs($user)->test(Profile::class)->html();

        $this->assertStringContainsString('Tipo de documento', $html);
        $this->assertStringContainsString('value="Cédula de ciudadanía"', $html);
        $this->assertStringNotContainsString('value="CC"', $html);
        $this->assertStringContainsString('Programa de adscripción', $html);
        $this->assertStringContainsString('value="Ingeniería de Sistemas"', $html);
    }

    public function test_shows_a_placeholder_when_document_type_or_program_unit_are_not_set(): void
    {
        $user = $this->userWithRole(RoleName::Teacher, [
            'document_type' => null,
            'program_unit_id' => null,
        ]);

        $html = Livewire::actingAs($user)->test(Profile::class)->html();

        $this->assertSame(2, substr_count($html, 'value="—"'));
    }

    /**
     * Bloque de ajustes de interfaz, punto 5: Profile usaba sus propias
     * claves `profileStatus`/`passwordStatus` en vez de la convención
     * compartida `status` — migrado para reutilizar <x-flash-message />
     * igual que el resto del sistema. Ninguna de las 2 acciones redirige
     * (ambas se quedan en la misma página), así que el flash y el
     * re-render ocurren en la misma petición — <x-flash-message /> (ya
     * incluido directamente en profile.blade.php) lo muestra en el HTML
     * que devuelve ese mismo ->call().
     */
    public function test_saving_profile_and_password_both_flash_the_shared_status_key(): void
    {
        $user = $this->userWithRole(RoleName::Teacher);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('name', 'Nombre Nuevo')->call('saveProfile')
            ->assertSee('Perfil actualizado correctamente.');

        Livewire::actingAs($user)->test(Profile::class)
            ->set('current_password', 'password')
            ->set('password', 'NuevaClave123!')
            ->set('password_confirmation', 'NuevaClave123!')
            ->call('savePassword')
            ->assertSee('Contraseña actualizada correctamente.');
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

    public function test_saving_the_profile_dispatches_an_event_so_the_sidebar_name_updates_live(): void
    {
        $user = $this->userWithRole(RoleName::Teacher);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('name', 'Nombre Nuevo')
            ->call('saveProfile')
            ->assertDispatched('profile-updated');
    }

    public function test_document_number_and_email_are_read_only_fields_in_the_view(): void
    {
        $user = $this->userWithRole(RoleName::Teacher);

        $html = $this->actingAs($user)->get('/profile')->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*wire:model="document_number"[^>]*\bdisabled\b[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*wire:model="email"[^>]*\bdisabled\b[^>]*>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*wire:model="name"[^>]*\bdisabled\b[^>]*>/', $html);
        $this->assertStringContainsString('Para actualizar este dato, contacta a un Administrador.', $html);
    }

    /**
     * Cambio de presentación: las 4 ayudas (document_number, email,
     * document_type, program_unit_id) comparten un único texto — ver
     * resources/views/components/readonly-field-help.blade.php — en vez
     * de la copia repetida/distinta que tenía cada campo antes.
     */
    public function test_readonly_fields_show_the_unified_help_text_four_times(): void
    {
        $user = $this->userWithRole(RoleName::Teacher);

        $html = Livewire::actingAs($user)->test(Profile::class)->html();

        $this->assertSame(4, substr_count($html, 'Para actualizar este dato, contacta a un Administrador.'));
        $this->assertStringNotContainsString('Puedes modificarlo desde Usuarios.', $html);
    }

    /**
     * Un Administrador viendo su PROPIO perfil ya puede corregir estos
     * datos él mismo desde "Usuarios" — recibe un mensaje distinto al de
     * cualquier otro rol.
     */
    public function test_an_administrator_viewing_their_own_profile_sees_the_administrator_variant_of_the_help_text(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        $html = Livewire::actingAs($admin)->test(Profile::class)->html();

        $this->assertSame(4, substr_count($html, 'Puedes modificarlo desde Usuarios.'));
        $this->assertStringNotContainsString('Para actualizar este dato, contacta a un Administrador.', $html);
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
            ->set('password', 'ClaveNueva123!')
            ->set('password_confirmation', 'ClaveNueva123!')
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
            ->set('password', 'ClaveNueva123!')
            ->set('password_confirmation', 'no-coincide')
            ->call('savePassword')
            ->assertHasErrors('password');
    }

    public function test_user_can_update_their_own_password(): void
    {
        $user = $this->userWithRole(RoleName::Teacher, ['password' => 'clave-original-A1']);

        Livewire::actingAs($user)->test(Profile::class)
            ->set('current_password', 'clave-original-A1')
            ->set('password', 'ClaveNueva123!')
            ->set('password_confirmation', 'ClaveNueva123!')
            ->call('savePassword')
            ->assertHasNoErrors()
            ->assertSet('current_password', '')
            ->assertSet('password', '')
            ->assertSet('password_confirmation', '');

        $this->assertTrue(Hash::check('ClaveNueva123!', $user->fresh()->password));
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
            ->set('password', 'ClaveNueva123!')
            ->set('password_confirmation', 'ClaveNueva123!')
            ->call('savePassword')
            ->assertHasNoErrors();

        // La sección de contraseña se guardó y limpió; la de perfil, que
        // nunca se envió, conserva lo escrito sin guardarlo — son
        // formularios independientes.
        $component->assertSet('name', 'Nombre Sin Guardar');
        $this->assertEquals('Nombre Original', $user->fresh()->name);
    }
}
