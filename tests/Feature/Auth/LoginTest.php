<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleName;
use App\Livewire\Auth\Login;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSeeLivewire(Login::class);
    }

    public function test_guest_is_redirected_to_login_when_visiting_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_active_user_can_authenticate_with_correct_credentials(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', RoleName::Teacher->value)->first());

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_shows_the_generic_message_not_a_field_error(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertSet('genericError', 'Correo o contraseña incorrectos.');

        $this->assertGuest();
    }

    public function test_nonexistent_email_shows_the_exact_same_generic_message(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'nadie@sigae.local')
            ->set('password', 'whatever-123')
            ->call('login')
            ->assertHasNoErrors()
            ->assertSet('genericError', 'Correo o contraseña incorrectos.');

        $this->assertGuest();
    }

    public function test_inactive_user_gets_a_distinct_account_disabled_message(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertSet('genericError', 'Tu cuenta ha sido desactivada. Contacta al administrador del sistema.');

        $this->assertGuest();
    }

    public function test_empty_fields_show_field_specific_messages_not_a_generic_banner(): void
    {
        Livewire::test(Login::class)
            ->set('email', '')
            ->set('password', '')
            ->call('login')
            ->assertHasErrors(['email' => 'required', 'password' => 'required'])
            ->assertSet('genericError', null)
            ->assertSee('Ingresa tu correo institucional')
            ->assertSee('Ingresa tu contraseña');
    }

    public function test_only_the_empty_field_is_flagged_when_the_other_is_filled(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'docente@sigae.local')
            ->set('password', '')
            ->call('login')
            ->assertHasErrors('password')
            ->assertHasNoErrors('email');
    }

    public function test_forgot_password_hint_appears_after_repeated_failed_attempts(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $component = Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'wrong-password');

        $component->call('login')->assertSet('showForgotPasswordHint', false);
        $component->call('login')->assertSet('showForgotPasswordHint', false);
        $component->call('login')->assertSet('showForgotPasswordHint', true);
    }

    public function test_deactivated_user_is_logged_out_mid_session(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', RoleName::Teacher->value)->first());

        $this->actingAs($user);

        $user->update(['is_active' => false]);

        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
