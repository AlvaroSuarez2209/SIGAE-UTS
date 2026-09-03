<?php

namespace App\Livewire\Catalogs;

use App\Models\Activity;
use App\Models\Component;
use App\Models\Subcomponent;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component as LivewireComponent;

#[Layout('layouts.app')]
class ActivityIndex extends LivewireComponent
{
    public bool $showModal = false;

    public ?Activity $editing = null;

    public string $name = '';

    public ?int $component_id = null;

    public ?int $subcomponent_id = null;

    public bool $is_active = true;

    public function updatedComponentId(): void
    {
        if (! $this->editing || $this->editing->component_id !== $this->component_id) {
            $this->subcomponent_id = null;
        }
    }

    public function openCreate(): void
    {
        $this->reset(['editing', 'name', 'component_id', 'subcomponent_id', 'is_active']);
        $this->is_active = true;
        $this->showModal = true;
    }

    public function openEdit(Activity $activity): void
    {
        $this->editing = $activity;
        $this->name = $activity->name;
        $this->component_id = $activity->component_id;
        $this->subcomponent_id = $activity->subcomponent_id;
        $this->is_active = $activity->is_active;
        $this->showModal = true;
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
            'activities' => Activity::with(['component', 'subcomponent'])->orderBy('name')->get(),
            'components' => Component::orderBy('name')->get(),
            'availableSubcomponents' => $this->component_id
                ? Subcomponent::where('component_id', $this->component_id)->orderBy('name')->get()
                : collect(),
        ]);
    }
}
