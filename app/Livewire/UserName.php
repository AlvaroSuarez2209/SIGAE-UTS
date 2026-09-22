<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Fragmento reactivo del nombre del usuario autenticado, montado dos
 * veces en layouts/app.blade.php (sidebar de escritorio y drawer
 * móvil) — el nombre es lo único de ese bloque que puede cambiar sin
 * recargar la página (desde "Mi perfil"), así que es lo único que se
 * extrajo a su propio componente Livewire; roles, el enlace "Mi
 * perfil" y "Cerrar sesión" siguen siendo Blade plano en el layout.
 *
 * Por qué hace falta un componente aparte: `layouts/app.blade.php` NO
 * es en sí mismo un componente Livewire — es el layout que Livewire
 * renderiza una sola vez por carga de página, y en el que luego
 * inserta el "slot" del componente activo (Profile, Dashboard, etc.)
 * en cada actualización AJAX. Un `{{ $user->name }}` puesto
 * directamente en el layout nunca se refrescaría solo porque Profile
 * actualice su propio HTML — necesita vivir en su propio componente
 * Livewire, capaz de escuchar el evento `profile-updated` que dispara
 * `Profile::saveProfile()` (ver App\Livewire\Profile).
 */
class UserName extends Component
{
    public string $name = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
    }

    #[On('profile-updated')]
    public function refreshName(): void
    {
        $this->name = Auth::user()->name;
    }

    public function render()
    {
        return view('livewire.user-name');
    }
}
