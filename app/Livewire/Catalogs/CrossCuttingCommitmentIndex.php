<?php

namespace App\Livewire\Catalogs;

use App\Models\CrossCuttingCommitment;
use App\Rules\CaseAccentInsensitiveUnique;
use App\Services\CatalogDependencyChecker;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Compromisos transversales')]
class CrossCuttingCommitmentIndex extends Component
{
    public bool $showModal = false;

    public ?CrossCuttingCommitment $editing = null;

    public string $name = '';

    public bool $is_active = true;

    public string $search = '';

    public string $statusFilter = '';

    public string $deactivationError = '';

    public function openCreate(): void
    {
        $this->reset(['editing', 'name', 'is_active']);
        $this->resetValidation();
        $this->is_active = true;
        $this->showModal = true;
    }

    public function openEdit(CrossCuttingCommitment $commitment): void
    {
        $this->resetValidation();
        $this->editing = $commitment;
        $this->name = $commitment->name;
        $this->is_active = $commitment->is_active;
        $this->showModal = true;
    }

    /**
     * Cancelar, clic fuera de la tarjeta o Escape convergen aquí — nunca
     * deben dejar un error de una validación anterior visible la próxima
     * vez que se abra el modal (ver [[project-livewire4-gotchas]]).
     */
    public function closeModal(): void
    {
        $this->reset(['editing', 'name', 'is_active']);
        $this->resetValidation();
        $this->showModal = false;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255', (new CaseAccentInsensitiveUnique('cross_cutting_commitments'))->ignore($this->editing)],
            'is_active' => ['boolean'],
        ]);

        if ($this->editing) {
            $this->editing->update($data);
            session()->flash('status', 'Compromiso transversal actualizado correctamente.');
        } else {
            CrossCuttingCommitment::create($data);
            session()->flash('status', 'Compromiso transversal creado correctamente.');
        }

        $this->showModal = false;
    }

    public function toggleActive(CrossCuttingCommitment $commitment): void
    {
        $this->deactivationError = '';

        if ($commitment->is_active && $this->blocksDeactivation($commitment)) {
            $this->dispatch('confirm-modal', title: 'No se puede desactivar', body: $this->deactivationError, confirmLabel: 'Entendido', variant: 'danger');

            return;
        }

        $commitment->update(['is_active' => ! $commitment->is_active]);

        session()->flash('status', $commitment->is_active ? 'Compromiso transversal activado.' : 'Compromiso transversal desactivado.');
    }

    /**
     * Prioridad 4: Entregable no tiene su propia columna is_active —
     * "activo" es pertenecer a un periodo académico que no esté Cerrado ni
     * Archivado (ver CatalogDependencyChecker).
     */
    private function blocksDeactivation(CrossCuttingCommitment $commitment): bool
    {
        $deliverableCount = CatalogDependencyChecker::openDeliverableCountForCommitment($commitment);

        if ($deliverableCount === 0) {
            return false;
        }

        $this->deactivationError = 'No se puede desactivar este compromiso: tiene '
            .($deliverableCount === 1 ? '1 entregable' : "{$deliverableCount} entregables")
            .' en un periodo académico vigente (en planeación o activo). Resuélvelos primero.';

        return true;
    }

    public function render()
    {
        return view('livewire.catalogs.cross-cutting-commitment-index', [
            'commitments' => CrossCuttingCommitment::newestFirst()
                ->when($this->search, fn ($query) => $query->whereAccentInsensitive('name', $this->search))
                ->when($this->statusFilter !== '', fn ($query) => $query->where('is_active', $this->statusFilter === 'active'))
                ->get(),
        ]);
    }
}
