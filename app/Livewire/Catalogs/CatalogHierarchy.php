<?php

namespace App\Livewire\Catalogs;

use App\Models\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component as LivewireComponent;

/**
 * Prioridad 4 de la revisión de la directora: Componentes > Subcomponentes
 * > Actividades anidados en una sola pantalla, de solo lectura, para
 * entender la estructura del catálogo de un vistazo en vez de cruzar 3
 * listados planos por separado. No reemplaza los CRUDs existentes
 * (ComponentIndex/SubcomponentIndex/ActivityIndex) — es un complemento.
 */
#[Layout('layouts.app')]
#[Title('Vista jerárquica de catálogos')]
class CatalogHierarchy extends LivewireComponent
{
    public function render()
    {
        return view('livewire.catalogs.catalog-hierarchy', [
            'components' => Component::with([
                'subcomponents' => fn ($query) => $query->orderBy('name'),
                'subcomponents.activities' => fn ($query) => $query->orderBy('name'),
                // Actividades adscritas directamente al componente, sin
                // subcomponente — Activity::component_id nunca es nulo,
                // así que toca pedirlas aparte de 'subcomponents.activities'.
                'activities' => fn ($query) => $query->whereNull('subcomponent_id')->orderBy('name'),
            ])->orderBy('name')->get(),
        ]);
    }
}
