<?php

namespace App\Livewire\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class ResetPassword extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $result = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();

                Event::dispatch(new PasswordReset($user));

                AuditLog::record('password_reset_completed', $user);
            }
        );

        if ($result !== Password::PASSWORD_RESET) {
            $this->addError('email', __($result));

            return;
        }

        session()->flash('status', __($result));

        $this->redirect(route('login'), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.reset-password');
    }
}
