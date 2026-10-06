<?php

namespace App\Livewire\Catalogs;

use App\Livewire\Concerns\HasStandardPagination;
use App\Models\Activity;
use App\Models\Component;
use App\Models\Subcomponent;
use App\Rules\CaseAccentInsensitiveUnique;
use App\Services\CatalogDependencyChecker;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component as LivewireComponent;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Actividades')]
class ActivityIndex extends LivewireComponent
{
    use HasStandardPagination, WithPagination;

    public bool $showModal = false;

    public ?Activity $editing = null;

    public string $name = '';

    public ?int $component_id = null;

    public ?int $subcomponent_id = null;

    public bool $is_active = true;

    public string $search = '';

    public string $statusFilter = '';

    public string $deactivationError = '';

    public function updatedComponentId(): void
    {
        if (! $this->editing || $this->editing->component_id !== $this->component_id) {
            $this->subcomponent_id = null;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset(['editing', 'name', 'component_id', 'subcomponent_id', 'is_active']);
        $this->resetValidation();
        $this->is_active = true;
        $this->showModal = true;
    }

    public function openEdit(Activity $activity): void
    {
        $this->resetValidation();
        $this->editing = $activity;
        $this->name = $activity->name;
        $this->component_id = $activity->component_id;
        $this->subcomponent_id = $activity->subcomponent_id;
        $this->is_active = $activity->is_active;
        $this->showModal = true;
    }

    /**
     * Cancelar, clic fuera de la tarjeta o Escape convergen aquí — nunca
     * deben dejar un error de una validación anterior visible la próxima
     * vez que se abra el modal (ver [[project-livewire4-gotchas]]).
     */
    public function closeModal(): void
    {
        $this->reset(['editing', 'name', 'component_id', 'subcomponent_id', 'is_active']);
        $this->resetValidation();
        $this->showModal = false;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => [
                'required', 'string', 'max:255',
                (new CaseAccentInsensitiveUnique('activities'))
                    ->where('component_id', $this->component_id)
                    ->where('subcomponent_id', $this->subcomponent_id)
                    ->ignore($this->editing),
            ],
            'component_id' => ['required', 'exists:components,id'],
            'subcomponent_id' => ['nullable', Rule::exists('subcomponents', 'id')->where('component_id', $this->component_id)],
            'is_active' => ['boolean'],
        ]);

        if ($this->editing) {
            $this->editing->update($data);
            session()->flash('status', 'Actividad actualizada correctamente.');
        } else {
            Activity::create($data);
            session()->flash('status', 'Actividad creada correctamente.');
        }

        $this->showModal = false;
    }

    public function toggleActive(Activity $activity): void
    {
        $this->deactivationError = '';

        if ($activity->is_active && $this->blocksDeactivation($activity)) {
            $this->dispatch('confirm-modal', title: 'No se puede desactivar', body: $this->deactivationError, confirmLabel: 'Entendido', variant: 'danger');

            return;
        }

        $activity->update(['is_active' => ! $activity->is_active]);

        session()->flash('status', $activity->is_active ? 'Actividad activada.' : 'Actividad desactivada.');
    }

    /**
     * Prioridad 4: Entregable y Asignación docente no tienen su propia
     * columna is_active — "activo" para ellos es pertenecer a un periodo
     * académico que no esté Cerrado ni Archivado (ver
     * CatalogDependencyChecker). Un entregable o asignación de un periodo
     * ya cerrado no bloquea nada.
     */
    private function blocksDeactivation(Activity $activity): bool
    {
        $deliverableCount = CatalogDependencyChecker::openDeliverableCountForActivity($activity);
        $assignmentCount = CatalogDependencyChecker::openTeacherAssignmentCountForActivity($activity);

        if ($deliverableCount === 0 && $assignmentCount === 0) {
            return false;
        }

        $parts = [];

        if ($deliverableCount > 0) {
            $parts[] = $deliverableCount === 1 ? '1 entregable' : "{$deliverableCount} entregables";
        }

        if ($assignmentCount > 0) {
            $parts[] = $assignmentCount === 1 ? '1 asignación docente' : "{$assignmentCount} asignaciones docentes";
        }

        $this->deactivationError = 'No se puede desactivar esta actividad: tiene '.implode(' y ', $parts)
            .' en un periodo académico vigente (en planeación o activo). Resuélvelos primero.';

        return true;
    }

    public function render()
    {
        return view('livewire.catalogs.activity-index', [
            'activities' => Activity::with(['component', 'subcomponent'])
                ->when($this->search, fn ($query) => $query->whereAccentInsensitive('name', $this->search))
                ->when($this->statusFilter !== '', fn ($query) => $query->where('is_active', $this->statusFilter === 'active'))
                ->newestFirst()
                ->paginate(self::PER_PAGE),
            'components' => Component::orderBy('name')->get(),
            'availableSubcomponents' => $this->component_id
                ? Subcomponent::where('component_id', $this->component_id)->orderBy('name')->get()
                : collect(),
        ]);
    }
}
