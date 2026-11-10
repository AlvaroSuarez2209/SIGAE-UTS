<?php

namespace App\Livewire\Catalogs;

use App\Models\Component;
use App\Rules\CaseAccentInsensitiveUnique;
use App\Services\CatalogDependencyChecker;
use App\Services\Catalog\CatalogDeletionChecker;
use App\Services\Catalog\CatalogDeletionService;
use Illuminate\Support\Facades\Gate;
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

    /**
     * Corrección tras pruebas manuales al punto 1: el interruptor
     * "Mostrar inactivos" se revierte — los inactivos ahora SIEMPRE
     * aparecen en esta lista (con su insignia de estado), para poder
     * editarlos o reactivarlos sin un paso extra. Vuelve el filtro de 3
     * estados que ya existía antes de ese cambio.
     */
    public string $statusFilter = '';

    public string $deactivationError = '';

    public ?Component $deleting = null;

    /** @var list<string> */
    public array $deletionBlockers = [];

    public ?string $deletionSuggestion = null;

    public string $deletionError = '';

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

        // Ajuste al punto 2: "Desactivar" también se dispara desde el
        // modal de borrado bloqueado (<x-catalog-delete-modal>) — si
        // de verdad desactivó, ese modal ya no tiene sentido abierto.
        $this->closeDeleteConfirmation();

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

    /**
     * Ajuste al punto 2: la comprobación de dependencias ya no se hace
     * al pintar la lista (sería una consulta por fila) — se hace aquí,
     * justo al abrir el modal, con datos frescos. La regla es más
     * estricta que blocksDeactivation() de arriba — cuenta CUALQUIER
     * subcomponente/actividad, activo o no.
     */
    public function openDeleteConfirmation(Component $component): void
    {
        Gate::authorize('delete', $component);

        $this->deleting = $component;
        $this->deletionBlockers = CatalogDeletionChecker::blockersForComponent($component);
        $this->deletionSuggestion = CatalogDeletionChecker::deactivationSuggestionForComponent();
        $this->deletionError = '';
    }

    public function closeDeleteConfirmation(): void
    {
        $this->reset(['deleting', 'deletionBlockers', 'deletionSuggestion', 'deletionError']);
    }

    /**
     * Punto 2: borrado real (físico), exclusivo de Administrador.
     * CatalogDeletionService vuelve a comprobar dentro de una
     * transacción con bloqueo antes de borrar de verdad, por si algo
     * cambió desde que se abrió el modal (condición de carrera).
     */
    public function deleteComponent(): void
    {
        Gate::authorize('delete', $this->deleting);

        $result = CatalogDeletionService::deleteComponent($this->deleting);

        if (! $result->deleted) {
            $this->deletionError = $result->message;

            return;
        }

        $this->closeDeleteConfirmation();
        session()->flash('status', 'Componente eliminado correctamente.');
    }

    public function render()
    {
        $components = Component::orderByAccentInsensitive('name')
            ->orderBy('id')
            ->when($this->search, fn ($query) => $query->whereAccentInsensitive('name', $this->search))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('is_active', $this->statusFilter === 'active'))
            ->get();

        return view('livewire.catalogs.component-index', [
            'components' => $components,
        ]);
    }
}
