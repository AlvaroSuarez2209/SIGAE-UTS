<?php

namespace App\Livewire\Deliverables;

use App\Models\DeliverableTemplate;
use App\Services\CatalogDependencyChecker;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Plantillas de entregables')]
class TemplateIndex extends Component
{
    public string $search = '';

    public string $statusFilter = '';

    public string $deactivationError = '';

    public function toggleActive(DeliverableTemplate $template): void
    {
        $this->deactivationError = '';

        if ($template->is_active && $this->blocksDeactivation($template)) {
            $this->dispatch('confirm-modal', title: 'No se puede desactivar', body: $this->deactivationError, confirmLabel: 'Entendido', variant: 'danger');

            return;
        }

        $template->update(['is_active' => ! $template->is_active]);
    }

    /**
     * Prioridad 4: igual criterio que los demás catálogos — Entregable no
     * tiene su propia columna is_active, "activo" es pertenecer a un
     * periodo académico que no esté Cerrado ni Archivado (ver
     * CatalogDependencyChecker). El vínculo deliverable_template_id es
     * nullOnDelete a propósito (perder la referencia a la plantilla al
     * borrarla no rompe nada), pero eso no significa que desactivarla sin
     * avisar esté bien.
     */
    private function blocksDeactivation(DeliverableTemplate $template): bool
    {
        $deliverableCount = CatalogDependencyChecker::openDeliverableCountForTemplate($template);

        if ($deliverableCount === 0) {
            return false;
        }

        $this->deactivationError = 'No se puede desactivar esta plantilla: tiene '
            .($deliverableCount === 1 ? '1 entregable' : "{$deliverableCount} entregables")
            .' en un periodo académico vigente (en planeación o activo). Resuélvelos primero.';

        return true;
    }

    public function render()
    {
        return view('livewire.deliverables.template-index', [
            'templates' => DeliverableTemplate::orderBy('name')
                ->when($this->search, fn ($query) => $query->whereAccentInsensitive('name', $this->search))
                ->when($this->statusFilter !== '', fn ($query) => $query->where('is_active', $this->statusFilter === 'active'))
                ->get(),
        ]);
    }
}
