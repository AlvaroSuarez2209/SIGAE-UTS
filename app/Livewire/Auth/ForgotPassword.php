<?php

namespace App\Livewire\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Recuperar contraseña')]
class ForgotPassword extends Component
{
    public string $email = '';

    public ?string $status = null;

    public function sendResetLink(): void
    {
        $this->status = null;

        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $throttleKey = 'password-reset|'.Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $this->addError('email', "Demasiados intentos. Intenta de nuevo en {$seconds} segundos.");

            return;
        }

        RateLimiter::hit($throttleKey, 60);

        $result = Password::sendResetLink(['email' => $this->email]);

        if ($result === Password::RESET_LINK_SENT) {
            $user = User::where('email', $this->email)->first();
            AuditLog::record('password_reset_requested', $user, ['email' => $this->email]);

            $this->status = __($result);
            $this->reset('email');

            return;
        }

        $this->addError('email', __($result));
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
