<?php

namespace App\Livewire\Auth;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\PasswordPolicy;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Restablecer contraseña')]
class ResetPassword extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Se pone en true la primera vez que el usuario intenta enviar el
     * formulario. Antes de eso, los requisitos de contraseña que aún no se
     * cumplen se muestran en gris neutro, nunca en rojo — el rojo se reserva
     * para después de un intento de envío fallido.
     */
    public bool $submitAttempted = false;

    public ?string $genericError = null;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->query('email', '');
    }

    public function meetsLength(): bool
    {
        return mb_strlen($this->password) >= 8;
    }

    public function hasUppercase(): bool
    {
        return (bool) preg_match('/\p{Lu}/u', $this->password);
    }

    public function hasLowercase(): bool
    {
        return (bool) preg_match('/\p{Ll}/u', $this->password);
    }

    /**
     * Mismo criterio que el `numbers()` de PasswordPolicy::rules()
     * (`preg_match('/\pN/u', ...)` — cualquier dígito Unicode, no solo
     * ASCII) — este checklist en vivo es solo la traducción visual, campo
     * por campo, de esa misma política única; nunca una segunda regla que
     * pueda desalinearse de la real (ver el gate en resetPassword()).
     */
    public function hasNumber(): bool
    {
        return (bool) preg_match('/\pN/u', $this->password);
    }

    /**
     * Mismo criterio que el `symbols()` de PasswordPolicy::rules()
     * (`preg_match('/\p{Z}|\p{S}|\p{P}/u', ...)` — separador, símbolo o
     * puntuación Unicode, exactamente la misma clase de carácter que usa
     * Illuminate\Validation\Rules\Password internamente).
     */
    public function hasSymbol(): bool
    {
        return (bool) preg_match('/\p{Z}|\p{S}|\p{P}/u', $this->password);
    }

    /**
     * @return array<int, array{label: string, met: bool}>
     */
    public function passwordRequirements(): array
    {
        return [
            ['label' => 'Mínimo 8 caracteres', 'met' => $this->meetsLength()],
            ['label' => 'Una letra mayúscula', 'met' => $this->hasUppercase()],
            ['label' => 'Una letra minúscula', 'met' => $this->hasLowercase()],
            ['label' => 'Un número', 'met' => $this->hasNumber()],
            ['label' => 'Un símbolo (ej. !@#$%)', 'met' => $this->hasSymbol()],
        ];
    }

    public function meetsAllRequirements(): bool
    {
        return collect($this->passwordRequirements())->every(fn (array $r) => $r['met']);
    }

    /**
     * 0 = sin escribir, 1 = débil, 2 = media, 3 = fuerte.
     */
    public function passwordStrengthLevel(): int
    {
        if ($this->password === '') {
            return 0;
        }

        $met = collect($this->passwordRequirements())->filter(fn (array $r) => $r['met'])->count();

        return match (true) {
            $met <= 1 => 1,
            $met <= 3 => 2,
            default => 3,
        };
    }

    public function passwordStrengthLabel(): string
    {
        return match ($this->passwordStrengthLevel()) {
            1 => 'Débil',
            2 => 'Media',
            3 => 'Fuerte',
            default => '',
        };
    }

    /**
     * Mensaje de error para "confirmar contraseña", o null si no aplica.
     * Antes de intentar enviar, un campo de confirmación vacío no muestra
     * nada (el usuario aún no ha terminado de escribir). El desajuste
     * ("no coinciden") sí se evalúa en vivo una vez hay algo escrito —
     * pensado para dispararse en el evento blur del campo (ver la vista).
     */
    public function confirmationError(): ?string
    {
        if ($this->password_confirmation === '') {
            return $this->submitAttempted ? 'Confirma tu nueva contraseña.' : null;
        }

        return $this->password !== $this->password_confirmation ? 'Las contraseñas no coinciden.' : null;
    }

    public function resetPassword(): void
    {
        $this->genericError = null;
        $this->submitAttempted = true;

        // El correo llega de forma fija desde el enlace del token (mount()),
        // nunca lo edita el usuario en esta pantalla — si viniera vacío por un
        // enlace malformado, Password::reset() abajo lo reporta igual como
        // un enlace inválido, sin necesidad de validarlo aquí como si fuera
        // un campo de formulario.
        if (! $this->meetsAllRequirements() || $this->confirmationError() !== null) {
            return;
        }

        // meetsAllRequirements() de arriba ya replica, campo por campo, los
        // mismos 3 requisitos para el checklist en vivo — este es el punto
        // que efectivamente autoriza el cambio, aplicando la política real
        // (PasswordPolicy::rules(), la misma que Profile y UserForm) en vez
        // de confiar solo en las comprobaciones manuales.
        if (Validator::make(['password' => $this->password], ['password' => PasswordPolicy::rules()])->fails()) {
            $this->genericError = 'La contraseña no cumple los requisitos mínimos.';

            return;
        }

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
            $this->genericError = __($result);

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
