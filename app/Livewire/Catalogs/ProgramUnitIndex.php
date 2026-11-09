<?php

namespace App\Livewire\Catalogs;

use App\Models\ProgramUnit;
use App\Rules\CaseAccentInsensitiveUnique;
use App\Services\CatalogDependencyChecker;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Programas')]
class ProgramUnitIndex extends Component
{
    public bool $showModal = false;

    public ?ProgramUnit $editing = null;

    public string $name = '';

    public bool $is_active = true;

    public string $search = '';

    public string $statusFilter = '';

    public string $deactivationError = '';

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
            'name' => ['required', 'string', 'max:255', (new CaseAccentInsensitiveUnique('program_units'))->ignore($this->editing)],
            'is_active' => ['boolean'],
        ]);

        if ($this->editing) {
            $this->editing->update($data);
            session()->flash('status', 'Programa actualizado correctamente.');
        } else {
            ProgramUnit::create($data);
            session()->flash('status', 'Programa creado correctamente.');
        }

        $this->showModal = false;
    }

    public function toggleActive(ProgramUnit $programUnit): void
    {
        $this->deactivationError = '';

        if ($programUnit->is_active && $this->blocksDeactivation($programUnit)) {
            $this->dispatch('confirm-modal', title: 'No se puede desactivar', body: $this->deactivationError, confirmLabel: 'Entendido', variant: 'danger');

            return;
        }

        $programUnit->update(['is_active' => ! $programUnit->is_active]);

        session()->flash('status', $programUnit->is_active ? 'Programa activado.' : 'Programa desactivado.');
    }

    /**
     * Prioridad 4: Asignación docente (activa = periodo en planeación o
     * activo, ver CatalogDependencyChecker) y Liderazgo (activo = vigente
     * por starts_at/ends_at, mismo criterio que
     * User::canLeadAssignment()).
     */
    private function blocksDeactivation(ProgramUnit $programUnit): bool
    {
        $assignmentCount = CatalogDependencyChecker::openTeacherAssignmentCountForProgramUnit($programUnit);
        $leadershipCount = CatalogDependencyChecker::activeLeadershipCountForProgramUnit($programUnit);

        if ($assignmentCount === 0 && $leadershipCount === 0) {
            return false;
        }

        $parts = [];

        if ($assignmentCount > 0) {
            $parts[] = $assignmentCount === 1 ? '1 asignación docente' : "{$assignmentCount} asignaciones docentes";
        }

        if ($leadershipCount > 0) {
            $parts[] = $leadershipCount === 1 ? '1 liderazgo vigente' : "{$leadershipCount} liderazgos vigentes";
        }

        $this->deactivationError = 'No se puede desactivar este programa: tiene '.implode(' y ', $parts).'. Resuélvelos primero.';

        return true;
    }

    public function render()
    {
        return view('livewire.catalogs.program-unit-index', [
            'programUnits' => ProgramUnit::newestFirst()
                ->when($this->search, fn ($query) => $query->whereAccentInsensitive('name', $this->search))
                ->when($this->statusFilter !== '', fn ($query) => $query->where('is_active', $this->statusFilter === 'active'))
                ->get(),
        ]);
    }
}
