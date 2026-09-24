<?php

namespace App\Livewire\Catalogs;

use App\Models\Component;
use Illuminate\Validation\Rule;
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
