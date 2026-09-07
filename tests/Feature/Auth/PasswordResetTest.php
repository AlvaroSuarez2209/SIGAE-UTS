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
            ->set('password', 'new-password-123')
            ->set('password_confirmation', 'new-password-123')
            ->call('resetPassword')
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'password_reset_completed',
            'auditable_type' => $user->getMorphClass(),
            'auditable_id' => $user->id,
        ]);

        // The new password actually works at the real login screen.
        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'new-password-123')
            ->call('login')
            ->assertRedirect(route('dashboard'));
    }

    public function test_reset_fails_with_an_invalid_token(): void
    {
        $user = User::factory()->create();

        Livewire::test(ResetPassword::class, ['token' => 'not-a-real-token'])
            ->set('email', $user->email)
            ->set('password', 'new-password-123')
            ->set('password_confirmation', 'new-password-123')
            ->call('resetPassword')
            ->assertHasErrors('email');

        $this->assertFalse(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_reset_requires_password_confirmation_to_match(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'new-password-123')
            ->set('password_confirmation', 'does-not-match')
            ->call('resetPassword')
            ->assertHasErrors('password');
    }
}
