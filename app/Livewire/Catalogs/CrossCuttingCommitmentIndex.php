<?php

namespace App\Livewire\Catalogs;

use App\Models\CrossCuttingCommitment;
use App\Rules\CaseAccentInsensitiveUnique;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Compromisos transversales')]
class CrossCuttingCommitmentIndex extends Component
{
    public bool $showModal = false;

    public ?CrossCuttingCommitment $editing = null;

    public string $name = '';

    public bool $is_active = true;

    public function openCreate(): void
    {
        $this->reset(['editing', 'name', 'is_active']);
        $this->resetValidation();
        $this->is_active = true;
        $this->showModal = true;
    }

    public function openEdit(CrossCuttingCommitment $commitment): void
    {
        $this->resetValidation();
        $this->editing = $commitment;
        $this->name = $commitment->name;
        $this->is_active = $commitment->is_active;
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
            'name' => ['required', 'string', 'max:255', (new CaseAccentInsensitiveUnique('cross_cutting_commitments'))->ignore($this->editing)],
            'is_active' => ['boolean'],
        ]);

        if ($this->editing) {
            $this->editing->update($data);
        } else {
            CrossCuttingCommitment::create($data);
        }

        $this->showModal = false;
    }

    public function toggleActive(CrossCuttingCommitment $commitment): void
    {
        $commitment->update(['is_active' => ! $commitment->is_active]);
    }

    public function render()
    {
        return view('livewire.catalogs.cross-cutting-commitment-index', [
            'commitments' => CrossCuttingCommitment::newestFirst()->get(),
        ]);
    }
}
