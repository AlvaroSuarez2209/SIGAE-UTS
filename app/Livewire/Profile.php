<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Mi perfil" — autoservicio de cualquier usuario autenticado sobre su
 * propia cuenta. Dos secciones independientes en la misma página, cada
 * una con su propio método de guardado/validación/mensaje de éxito, para
 * que un error en una no afecte ni limpie el estado de la otra.
 *
 * `document_number` y `email` son de solo lectura aquí a propósito: son
 * datos administrativos/de acceso, no autoservicio libre — corregirlos
 * requiere un Administrador desde "Usuarios" (misma validación de
 * unicidad que ya existía ahí, y queda registrado en Auditoría vía el
 * trait `Auditable`). `saveProfile()` solo valida y guarda `name`; las
 * otras dos propiedades siguen existiendo para poblar los campos
 * deshabilitados en la vista, pero nunca se validan ni se persisten
 * desde aquí — ni siquiera si alguien manipulara la petición de
 * Livewire a mano, ya que `update()` ni las toca.
 *
 * No usa UserPolicy/Gate: a diferencia de app/Livewire/Admin/Users
 * (Administrador gestionando OTRAS cuentas), aquí no hace falta
 * autorización adicional — cualquier usuario autenticado ya tiene
 * permiso de editar su propia cuenta por definición; el middleware
 * `auth` de la ruta es la única puerta necesaria.
 */
#[Layout('layouts.app')]
class Profile extends Component
{
    public string $name = '';

    public string $document_number = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->document_number = (string) $user->document_number;
        $this->email = $user->email;
    }

    public function saveProfile(): void
    {
        $user = Auth::user();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user->update(['name' => $data['name']]);

        session()->flash('profileStatus', 'Perfil actualizado correctamente.');
    }

    public function savePassword(): void
    {
        $user = Auth::user();

        $data = $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        $this->reset(['current_password', 'password', 'password_confirmation']);

        session()->flash('passwordStatus', 'Contraseña actualizada correctamente.');
    }

    public function render()
    {
        return view('livewire.profile');
    }
}
