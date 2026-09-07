<?php

namespace App\Livewire\Auth;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    /**
     * Mensaje genérico de nivel de formulario (no ligado a un campo específico):
     * credenciales incorrectas, cuenta inactiva, o límite de intentos. Nunca
     * distingue "correo no existe" de "contraseña incorrecta" — evita
     * enumeración de usuarios.
     */
    public ?string $genericError = null;

    public bool $showForgotPasswordHint = false;

    public function login(): void
    {
        $this->genericError = null;
        $this->showForgotPasswordHint = false;

        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Ingresa tu correo institucional.',
            'email.email' => 'Ingresa un correo institucional válido.',
            'password.required' => 'Ingresa tu contraseña.',
        ]);

        $throttleKey = Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $this->genericError = "Demasiados intentos. Intenta de nuevo en {$seconds} segundos.";
            $this->showForgotPasswordHint = true;

            return;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey, 60);

            AuditLog::record('login_failed', null, ['email' => $this->email]);

            // Mismo mensaje sin importar si el correo no existe o la contraseña
            // es incorrecta — revelar cuál de los dos casos ocurrió permitiría
            // a un atacante usar el login para descubrir correos registrados.
            $this->genericError = 'Correo o contraseña incorrectos.';
            $this->showForgotPasswordHint = RateLimiter::attempts($throttleKey) >= 3;

            return;
        }

        if (! Auth::user()->is_active) {
            AuditLog::record('login_blocked_inactive', Auth::user());

            Auth::logout();

            $this->genericError = 'Tu cuenta ha sido desactivada. Contacta al administrador del sistema.';

            return;
        }

        AuditLog::record('login', Auth::user());

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        $this->redirect(route('dashboard'), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
