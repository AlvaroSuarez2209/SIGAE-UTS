<?php

namespace App\Livewire\Catalogs;

use App\Models\ProgramUnit;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProgramUnitIndex extends Component
{
    public bool $showModal = false;

    public ?ProgramUnit $editing = null;

    public string $name = '';

    public bool $is_active = true;

    public function openCreate(): void
    {
        $this->reset(['editing', 'name', 'is_active']);
        $this->resetValidation();
        $this->is_active = true;
        $this->showModal = true;
    }

    public function openEdit(ProgramUnit $programUnit): void
    {
        $this->resetValidation();
        $this->editing = $programUnit;
        $this->name = $programUnit->name;
        $this->is_active = $programUnit->is_active;
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
            'name' => ['required', 'string', 'max:255', Rule::unique('program_units', 'name')->ignore($this->editing)],
            'is_active' => ['boolean'],
        ]);

        if ($this->editing) {
            $this->editing->update($data);
        } else {
            ProgramUnit::create($data);
        }

        $this->showModal = false;
    }

    public function toggleActive(ProgramUnit $programUnit): void
    {
        $programUnit->update(['is_active' => ! $programUnit->is_active]);
    }

    public function render()
    {
        return view('livewire.catalogs.program-unit-index', [
            'programUnits' => ProgramUnit::orderBy('name')->get(),
        ]);
    }
}
