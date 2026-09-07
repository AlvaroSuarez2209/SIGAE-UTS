<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_PASSWORD = 'Nueva-Clave123';

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
        $response->assertSeeLivewire(ForgotPassword::class);
    }

    public function test_requesting_a_reset_link_for_an_existing_email_sends_the_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Livewire::test(ForgotPassword::class)
            ->set('email', $user->email)
            ->call('sendResetLink')
            ->assertHasNoErrors()
            ->assertSet('status', fn ($status) => filled($status));

        Notification::assertSentTo($user, ResetPasswordNotification::class);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'password_reset_requested',
            'auditable_type' => $user->getMorphClass(),
            'auditable_id' => $user->id,
        ]);
    }

    public function test_requesting_a_reset_link_for_an_unknown_email_shows_an_error(): void
    {
        Notification::fake();

        Livewire::test(ForgotPassword::class)
            ->set('email', 'nadie@uts.edu.co')
            ->call('sendResetLink')
            ->assertHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $response = $this->get('/reset-password/some-token?email=docente@uts.edu.co');

        $response->assertStatus(200);
        $response->assertSeeLivewire(ResetPassword::class);
    }

    public function test_user_can_reset_password_with_a_valid_token(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = Password::createToken($user);

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', self::VALID_PASSWORD)
            ->set('password_confirmation', self::VALID_PASSWORD)
            ->call('resetPassword')
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check(self::VALID_PASSWORD, $user->fresh()->password));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'password_reset_completed',
            'auditable_type' => $user->getMorphClass(),
            'auditable_id' => $user->id,
        ]);

        // The new password actually works at the real login screen.
        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', self::VALID_PASSWORD)
            ->call('login')
            ->assertRedirect(route('dashboard'));
    }

    public function test_reset_fails_with_an_invalid_token_and_shows_a_generic_banner(): void
    {
        $user = User::factory()->create();

        Livewire::test(ResetPassword::class, ['token' => 'not-a-real-token'])
            ->set('email', $user->email)
            ->set('password', self::VALID_PASSWORD)
            ->set('password_confirmation', self::VALID_PASSWORD)
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertSet('genericError', fn ($message) => filled($message));

        $this->assertFalse(Hash::check(self::VALID_PASSWORD, $user->fresh()->password));
    }

    public function test_reset_requires_password_confirmation_to_match(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', self::VALID_PASSWORD)
            ->set('password_confirmation', 'Does-Not-Match123')
            ->call('resetPassword')
            ->assertSee('Las contraseñas no coinciden.');

        $this->assertFalse(Hash::check(self::VALID_PASSWORD, $user->fresh()->password));
    }

    public function test_mismatch_message_is_not_shown_before_confirmation_is_typed(): void
    {
        $token = Password::createToken(User::factory()->create());

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('password', 'Something123')
            ->assertDontSee('Las contraseñas no coinciden.');
    }

    public function test_password_requirements_checklist_updates_live_as_the_user_types(): void
    {
        $token = Password::createToken(User::factory()->create());

        $component = Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('password', 'abc');

        $requirements = $component->instance()->passwordRequirements();

        $this->assertFalse($requirements[0]['met']); // 8 caracteres
        $this->assertFalse($requirements[1]['met']); // mayúscula
        $this->assertTrue($requirements[2]['met']);  // minúscula
        $this->assertFalse($requirements[3]['met']); // número o símbolo

        $component->set('password', self::VALID_PASSWORD);
        $this->assertTrue($component->instance()->meetsAllRequirements());
    }

    public function test_unmet_requirements_are_neutral_until_a_submit_attempt(): void
    {
        $token = Password::createToken(User::factory()->create());

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('password', 'abc')
            ->assertSet('submitAttempted', false)
            ->assertDontSee('Tu contraseña no cumple con todos los requisitos.');
    }

    public function test_submitting_a_weak_password_flags_the_checklist_without_a_generic_error(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'abc')
            ->set('password_confirmation', 'abc')
            ->call('resetPassword')
            ->assertSet('submitAttempted', true)
            ->assertSet('genericError', null)
            ->assertSee('Tu contraseña no cumple con todos los requisitos.');

        $this->assertFalse(Hash::check('abc', $user->fresh()->password));
    }
}
