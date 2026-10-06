<?php

namespace App\Livewire\Catalogs;

use App\Models\Component;
use App\Rules\CaseAccentInsensitiveUnique;
use App\Services\CatalogDependencyChecker;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component as LivewireComponent;

#[Layout('layouts.app')]
#[Title('Componentes')]
class ComponentIndex extends LivewireComponent
{
    public bool $showModal = false;

    public ?Component $editing = null;

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

    public function openEdit(Component $component): void
    {
        $this->resetValidation();
        $this->editing = $component;
        $this->name = $component->name;
        $this->is_active = $component->is_active;
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
            'name' => ['required', 'string', 'max:255', (new CaseAccentInsensitiveUnique('components'))->ignore($this->editing)],
            'is_active' => ['boolean'],
        ]);

        if ($this->editing) {
            $this->editing->update($data);
            session()->flash('status', 'Componente actualizado correctamente.');
        } else {
            Component::create($data);
            session()->flash('status', 'Componente creado correctamente.');
        }

        $this->showModal = false;
    }

    public function toggleActive(Component $component): void
    {
        $this->deactivationError = '';

        if ($component->is_active && $this->blocksDeactivation($component)) {
            $this->dispatch('confirm-modal', title: 'No se puede desactivar', body: $this->deactivationError, confirmLabel: 'Entendido', variant: 'danger');

            return;
        }

        $component->update(['is_active' => ! $component->is_active]);

        session()->flash('status', $component->is_active ? 'Componente activado.' : 'Componente desactivado.');
    }

    /**
     * Prioridad 4: desactivar un componente con subcomponentes o
     * actividades activos dependiendo de él dejaría esos registros
     * huérfanos de su padre vigente — se bloquea, con el detalle de
     * cuántos y de qué tipo, no un mensaje genérico. Ver
     * CatalogDependencyChecker.
     */
    private function blocksDeactivation(Component $component): bool
    {
        $subcomponentCount = CatalogDependencyChecker::activeSubcomponentCount($component);
        $activityCount = CatalogDependencyChecker::activeActivityCountForComponent($component);

        if ($subcomponentCount === 0 && $activityCount === 0) {
            return false;
        }

        $parts = [];

        if ($subcomponentCount > 0) {
            $parts[] = $subcomponentCount === 1 ? '1 subcomponente activo' : "{$subcomponentCount} subcomponentes activos";
        }

        if ($activityCount > 0) {
            $parts[] = $activityCount === 1 ? '1 actividad activa' : "{$activityCount} actividades activas";
        }

        $this->deactivationError = 'No se puede desactivar este componente: tiene '.implode(' y ', $parts).'. Desactívalos primero.';

        return true;
    }

    public function render()
    {
        return view('livewire.catalogs.component-index', [
            'components' => Component::newestFirst()
                ->when($this->search, fn ($query) => $query->whereAccentInsensitive('name', $this->search))
                ->when($this->statusFilter !== '', fn ($query) => $query->where('is_active', $this->statusFilter === 'active'))
                ->get(),
        ]);
    }
}
