<?php

namespace App\Livewire\Catalogs;

use App\Models\Component;
use App\Models\Subcomponent;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component as LivewireComponent;

#[Layout('layouts.app')]
class SubcomponentIndex extends LivewireComponent
{
    public bool $showModal = false;

    public ?Subcomponent $editing = null;

    public string $name = '';

    public ?int $component_id = null;

    public bool $is_active = true;

    public function openCreate(): void
    {
        $this->reset(['editing', 'name', 'component_id', 'is_active']);
        $this->is_active = true;
        $this->showModal = true;
    }

    public function openEdit(Subcomponent $subcomponent): void
    {
        $this->editing = $subcomponent;
        $this->name = $subcomponent->name;
        $this->component_id = $subcomponent->component_id;
        $this->is_active = $subcomponent->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('subcomponents', 'name')->where('component_id', $this->component_id)->ignore($this->editing),
            ],
            'component_id' => ['required', 'exists:components,id'],
            'is_active' => ['boolean'],
        ]);

        if ($this->editing) {
            $this->editing->update($data);
        } else {
            Subcomponent::create($data);
        }

        $this->showModal = false;
    }

    public function toggleActive(Subcomponent $subcomponent): void
    {
        $subcomponent->update(['is_active' => ! $subcomponent->is_active]);
    }

    public function render()
    {
        return view('livewire.catalogs.subcomponent-index', [
            'subcomponents' => Subcomponent::with('component')->orderBy('name')->get(),
            'components' => Component::orderBy('name')->get(),
        ]);
    }
}
