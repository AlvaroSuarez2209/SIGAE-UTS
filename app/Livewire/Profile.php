<?php

namespace App\Livewire;

use App\Enums\DocumentType;
use App\Enums\RoleName;
use App\Services\PasswordPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Mi perfil" — autoservicio de cualquier usuario autenticado sobre su
 * propia cuenta. Dos secciones independientes en la misma página, cada
 * una con su propio método de guardado/validación, que comparten el
 * mismo flash `status` (ver resources/views/components/flash-message.blade.php)
 * — nunca se ejecutan los dos a la vez en una misma petición, así que no
 * hay riesgo de que un guardado pise el mensaje del otro.
 *
 * `document_number`, `email`, `document_type` y `program_unit_id` son de
 * solo lectura aquí a propósito: son datos administrativos/de acceso, no
 * autoservicio libre. Los cuatro se pueden corregir desde "Usuarios"
 * (Administrador, ver App\Livewire\Admin\Users\UserForm) — aquí se
 * muestran puramente informativos, nunca editables, aunque la cuenta
 * autenticada sea la de un Administrador editando su propio perfil.
 * `saveProfile()` solo valida y guarda `name`; el resto de propiedades
 * solo pueblan campos deshabilitados en la vista, nunca se validan ni se
 * persisten desde aquí — ni siquiera si alguien manipulara la petición de
 * Livewire a mano, ya que `update()` ni las toca.
 *
 * No usa UserPolicy/Gate: a diferencia de app/Livewire/Admin/Users
 * (Administrador gestionando OTRAS cuentas), aquí no hace falta
 * autorización adicional — cualquier usuario autenticado ya tiene
 * permiso de editar su propia cuenta por definición; el middleware
 * `auth` de la ruta es la única puerta necesaria.
 */
#[Layout('layouts.app')]
#[Title('Mi perfil')]
class Profile extends Component
{
    public string $name = '';

    public string $document_number = '';

    public string $email = '';

    public ?string $document_type = null;

    public ?string $programUnitName = null;

    /**
     * El texto de ayuda de los 4 campos de solo lectura (ver
     * resources/views/components/readonly-field-help.blade.php) depende
     * de este flag: un Administrador viendo su PROPIO perfil ya puede
     * corregirlos él mismo desde "Usuarios", a diferencia de cualquier
     * otro rol.
     */
    public bool $isAdministrator = false;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->document_number = (string) $user->document_number;
        $this->email = $user->email;
        $this->document_type = $user->document_type ? DocumentType::tryFrom($user->document_type)?->label() ?? $user->document_type : null;
        $this->programUnitName = $user->programUnit?->name;
        $this->isAdministrator = $user->hasRole(RoleName::Administrator);
    }

    public function saveProfile(): void
    {
        $user = Auth::user();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user->update(['name' => $data['name']]);

        // Livewire retransmite esto como un CustomEvent de navegador real,
        // así que cualquier componente Livewire ya montado en la página lo
        // recibe — no solo un componente "padre" — ver App\Livewire\UserName,
        // que es quien realmente actualiza el nombre en el sidebar.
        $this->dispatch('profile-updated');

        session()->flash('status', 'Perfil actualizado correctamente.');
    }

    public function savePassword(): void
    {
        $user = Auth::user();

        $data = $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordPolicy::rules()],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        $this->reset(['current_password', 'password', 'password_confirmation']);

        session()->flash('status', 'Contraseña actualizada correctamente.');
    }

    public function render()
    {
        return view('livewire.profile');
    }
}
