<?php

namespace App\Livewire\Catalogs;

use App\Models\Component;
use App\Models\Subcomponent;
use Illuminate\Validation\Rule;
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
            'subcomponents' => Subcomponent::with('component')
                ->when($this->search, fn ($query) => $query->where('name', 'ilike', "%{$this->search}%"))
                ->orderBy('name')
                ->get(),
            'components' => Component::orderBy('name')->get(),
        ]);
    }
}
