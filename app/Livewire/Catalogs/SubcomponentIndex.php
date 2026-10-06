<?php

namespace App\Livewire\Catalogs;

use App\Models\Component;
use App\Models\Subcomponent;
use App\Rules\CaseAccentInsensitiveUnique;
use App\Services\CatalogDependencyChecker;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component as LivewireComponent;

#[Layout('layouts.app')]
#[Title('Subcomponentes')]
class SubcomponentIndex extends LivewireComponent
{
    public bool $showModal = false;

    public ?Subcomponent $editing = null;

    public string $name = '';

    public ?int $component_id = null;

    public bool $is_active = true;

    public string $search = '';

    public string $statusFilter = '';

    public string $deactivationError = '';

    public function openCreate(): void
    {
        $this->reset(['editing', 'name', 'component_id', 'is_active']);
        $this->resetValidation();
        $this->is_active = true;
        $this->showModal = true;
    }

    public function openEdit(Subcomponent $subcomponent): void
    {
        $this->resetValidation();
        $this->editing = $subcomponent;
        $this->name = $subcomponent->name;
        $this->component_id = $subcomponent->component_id;
        $this->is_active = $subcomponent->is_active;
        $this->showModal = true;
    }

    /**
     * Cancelar, clic fuera de la tarjeta o Escape convergen aquí — nunca
     * deben dejar un error de una validación anterior visible la próxima
     * vez que se abra el modal (ver [[project-livewire4-gotchas]]).
     */
    public function closeModal(): void
    {
        $this->reset(['editing', 'name', 'component_id', 'is_active']);
        $this->resetValidation();
        $this->showModal = false;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => [
                'required', 'string', 'max:255',
                (new CaseAccentInsensitiveUnique('subcomponents'))->where('component_id', $this->component_id)->ignore($this->editing),
            ],
            'component_id' => ['required', 'exists:components,id'],
            'is_active' => ['boolean'],
        ]);

        if ($this->editing) {
            $this->editing->update($data);
            session()->flash('status', 'Subcomponente actualizado correctamente.');
        } else {
            Subcomponent::create($data);
            session()->flash('status', 'Subcomponente creado correctamente.');
        }

        $this->showModal = false;
    }

    public function toggleActive(Subcomponent $subcomponent): void
    {
        $this->deactivationError = '';

        if ($subcomponent->is_active && $this->blocksDeactivation($subcomponent)) {
            $this->dispatch('confirm-modal', title: 'No se puede desactivar', body: $this->deactivationError, confirmLabel: 'Entendido', variant: 'danger');

            return;
        }

        $subcomponent->update(['is_active' => ! $subcomponent->is_active]);

        session()->flash('status', $subcomponent->is_active ? 'Subcomponente activado.' : 'Subcomponente desactivado.');
    }

    /**
     * Prioridad 4: mismo criterio que ComponentIndex — un subcomponente con
     * actividades activas bajo él no se puede desactivar sin dejarlas
     * huérfanas de su agrupación vigente.
     */
    private function blocksDeactivation(Subcomponent $subcomponent): bool
    {
        $activityCount = CatalogDependencyChecker::activeActivityCountForSubcomponent($subcomponent);

        if ($activityCount === 0) {
            return false;
        }

        $this->deactivationError = 'No se puede desactivar este subcomponente: tiene '
            .($activityCount === 1 ? '1 actividad activa' : "{$activityCount} actividades activas")
            .'. Desactívalas primero.';

        return true;
    }

    public function render()
    {
        return view('livewire.catalogs.subcomponent-index', [
            'subcomponents' => Subcomponent::with('component')
                ->when($this->search, fn ($query) => $query->whereAccentInsensitive('name', $this->search))
                ->when($this->statusFilter !== '', fn ($query) => $query->where('is_active', $this->statusFilter === 'active'))
                ->newestFirst()
                ->get(),
            'components' => Component::orderBy('name')->get(),
        ]);
    }
}
