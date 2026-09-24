<?php

namespace App\Livewire\Catalogs;

use App\Models\Activity;
use App\Models\Component;
use App\Models\Subcomponent;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component as LivewireComponent;

#[Layout('layouts.app')]
#[Title('Actividades')]
class ActivityIndex extends LivewireComponent
{
    public bool $showModal = false;

    public ?Activity $editing = null;

    public string $name = '';

    public ?int $component_id = null;

    public ?int $subcomponent_id = null;

    public bool $is_active = true;

    public string $search = '';

    public function updatedComponentId(): void
    {
        if (! $this->editing || $this->editing->component_id !== $this->component_id) {
            $this->subcomponent_id = null;
        }
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
            'name' => ['required', 'string', 'max:255'],
            'component_id' => ['required', 'exists:components,id'],
            'subcomponent_id' => ['nullable', Rule::exists('subcomponents', 'id')->where('component_id', $this->component_id)],
            'is_active' => ['boolean'],
        ]);

        if ($this->editing) {
            $this->editing->update($data);
        } else {
            Activity::create($data);
        }

        $this->showModal = false;
    }

    public function toggleActive(Activity $activity): void
    {
        $activity->update(['is_active' => ! $activity->is_active]);
    }

    public function render()
    {
        return view('livewire.catalogs.activity-index', [
            'activities' => Activity::with(['component', 'subcomponent'])
                ->when($this->search, fn ($query) => $query->where('name', 'ilike', "%{$this->search}%"))
                ->orderBy('name')
                ->get(),
            'components' => Component::orderBy('name')->get(),
            'availableSubcomponents' => $this->component_id
                ? Subcomponent::where('component_id', $this->component_id)->orderBy('name')->get()
                : collect(),
        ]);
    }
}
