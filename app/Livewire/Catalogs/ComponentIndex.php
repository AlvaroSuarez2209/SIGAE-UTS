<?php

namespace App\Livewire\Catalogs;

use App\Models\Component;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component as LivewireComponent;

#[Layout('layouts.app')]
class ComponentIndex extends LivewireComponent
{
    public bool $showModal = false;

    public ?Component $editing = null;

    public string $name = '';

    public bool $is_active = true;

    public function openCreate(): void
    {
        $this->reset(['editing', 'name', 'is_active']);
        $this->is_active = true;
        $this->showModal = true;
    }

    public function openEdit(Component $component): void
    {
        $this->editing = $component;
        $this->name = $component->name;
        $this->is_active = $component->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('components', 'name')->ignore($this->editing)],
            'is_active' => ['boolean'],
        ]);

        if ($this->editing) {
            $this->editing->update($data);
        } else {
            Component::create($data);
        }

        $this->showModal = false;
    }

    public function toggleActive(Component $component): void
    {
        $component->update(['is_active' => ! $component->is_active]);
    }

    public function render()
    {
        return view('livewire.catalogs.component-index', [
            'components' => Component::orderBy('name')->get(),
        ]);
    }
}
