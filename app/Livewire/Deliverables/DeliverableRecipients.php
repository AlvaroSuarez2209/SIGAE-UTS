<?php

namespace App\Livewire\Deliverables;

use App\Models\Deliverable;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Punto de entrada para Administración/Coordinación desde "Entregables"
 * hacia la evidencia de cada destinatario — entre otras cosas, hacia la
 * acción "Marcar como exento" (ver EvidencePolicy::markExempt), que de
 * otro modo no tendría ninguna pantalla desde la que llegar para
 * evidencias que nunca pasan por la bandeja de revisión (pendiente,
 * borrador, vencida). El acceso ya lo restringe el middleware
 * `role:administrator,coordination` de la ruta (mismo grupo que
 * `deliverables.index`), no hace falta repetirlo aquí.
 */
#[Layout('layouts.app')]
class DeliverableRecipients extends Component
{
    public Deliverable $deliverable;

    public function mount(Deliverable $deliverable): void
    {
        $this->deliverable = $deliverable;
    }

    public function render()
    {
        $this->deliverable->load([
            'activity.component',
            'crossCuttingCommitment',
            'evidences.user',
        ]);

        $rows = $this->deliverable->evidences
            ->sortBy(fn ($evidence) => $evidence->user->name)
            ->values();

        return view('livewire.deliverables.deliverable-recipients', [
            'rows' => $rows,
        ]);
    }
}
