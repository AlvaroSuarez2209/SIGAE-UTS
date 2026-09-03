<?php

namespace App\Livewire\Catalogs;

use App\Models\CrossCuttingCommitment;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CrossCuttingCommitmentIndex extends Component
{
    public bool $showModal = false;

    public ?CrossCuttingCommitment $editing = null;

    public string $name = '';

    public bool $is_active = true;

    public function openCreate(): void
    {
        $this->reset(['editing', 'name', 'is_active']);
        $this->is_active = true;
        $this->showModal = true;
    }

    public function openEdit(CrossCuttingCommitment $commitment): void
    {
        $this->editing = $commitment;
        $this->name = $commitment->name;
        $this->is_active = $commitment->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('cross_cutting_commitments', 'name')->ignore($this->editing)],
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
            'commitments' => CrossCuttingCommitment::orderBy('name')->get(),
        ]);
    }
}
