<?php

namespace App\Livewire\Leaderships;

use App\Models\Leadership;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Mi ámbito": de solo lectura, exclusiva del rol Líder — a diferencia de
 * /leaderships (Administrador/Coordinación, cualquier liderazgo, con
 * acciones de crear/editar/finalizar), esta pantalla solo muestra los
 * liderazgos del propio usuario autenticado, sin ninguna acción. Antes el
 * Líder no tenía ninguna forma de ver su propio ámbito sin pedirle a
 * Coordinación que abriera /leaderships por él.
 */
#[Layout('layouts.app')]
#[Title('Mi ámbito')]
class MyLeadership extends Component
{
    public function render()
    {
        $leaderships = Leadership::query()
            ->where('user_id', auth()->id())
            ->with(['activity.component', 'programUnit', 'academicPeriod'])
            ->orderByDesc('starts_at')
            ->get();

        return view('livewire.leaderships.my-leadership', [
            'leaderships' => $leaderships,
        ]);
    }
}
