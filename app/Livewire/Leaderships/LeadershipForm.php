<?php

namespace App\Livewire\Leaderships;

use App\Enums\RoleName;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Component;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\Subcomponent;
use App\Models\User;
use App\Notifications\Leadership\LeadershipAssignedNotification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component as LivewireComponent;

#[Layout('layouts.app')]
class LeadershipForm extends LivewireComponent
{
    public ?Leadership $leadership = null;

    /**
     * Mismo patrón que ReviewShow/DeliverableForm: esta pantalla nunca
     * decide estos valores, solo los recibe por la URL (puestos ahí por
     * los enlaces de LeadershipIndex) y los reenvía en "Cancelar" y en
     * el redirect de save().
     */
    #[Url]
    public string $search = '';

    #[Url]
    public ?int $periodFilter = null;

    public function leadershipsIndexUrl(): string
    {
        return route('leaderships.index', ['search' => $this->search, 'periodFilter' => $this->periodFilter ?? '']);
    }

    public ?int $user_id = null;

    public ?int $academic_period_id = null;

    public ?int $program_unit_id = null;

    public ?int $activity_id = null;

    /**
     * Revisión de la directora, punto 13: filtros puramente de interfaz
     * para acotar qué actividad elegir — nunca se guardan, activity_id
     * sigue siendo el único campo real (ver save()).
     */
    public ?int $component_id = null;

    public ?int $subcomponent_id = null;

    public string $starts_at = '';

    public string $ends_at = '';

    public bool $periodLocked = false;

    public function mount(?Leadership $leadership = null): void
    {
        $this->leadership = $leadership?->exists ? $leadership : null;

        if ($this->leadership) {
            $this->user_id = $this->leadership->user_id;
            $this->academic_period_id = $this->leadership->academic_period_id;
            $this->program_unit_id = $this->leadership->program_unit_id;
            $this->activity_id = $this->leadership->activity_id;

            if ($this->activity_id) {
                $activity = Activity::find($this->activity_id);
                $this->component_id = $activity?->component_id;
                $this->subcomponent_id = $activity?->subcomponent_id;
            }

            $this->starts_at = $this->leadership->starts_at->toDateString();
            $this->ends_at = $this->leadership->ends_at?->toDateString() ?? '';
            $this->periodLocked = in_array($this->leadership->academicPeriod->status->value, ['closed', 'archived']);
        } else {
            $this->starts_at = now()->toDateString();
        }
    }

    public function updatedComponentId(): void
    {
        $this->subcomponent_id = null;
        $this->activity_id = null;
    }

    public function updatedSubcomponentId(): void
    {
        $this->activity_id = null;
    }

    public function save(): void
    {
        if ($this->periodLocked) {
            $this->addError('academic_period_id', 'Este periodo está cerrado y no admite modificaciones ordinarias.');

            return;
        }

        $data = $this->validate([
            'user_id' => ['required', 'exists:users,id'],
            'academic_period_id' => [
                'required',
                Rule::exists('academic_periods', 'id')->whereIn('status', ['planning', 'active']),
            ],
            'program_unit_id' => ['required', 'exists:program_units,id'],
            'activity_id' => ['nullable', 'exists:activities,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ], [
            'academic_period_id.exists' => 'El periodo seleccionado no admite nuevos liderazgos (está cerrado o archivado).',
        ]);

        $data['activity_id'] = $data['activity_id'] ?: null;
        $data['ends_at'] = $data['ends_at'] ?: null;

        if ($this->leadership) {
            $this->leadership->update($data);
        } else {
            $this->leadership = Leadership::create($data);
            $this->leadership->user->notify(new LeadershipAssignedNotification($this->leadership));
        }

        session()->flash('status', 'Liderazgo guardado correctamente.');

        $this->redirect($this->leadershipsIndexUrl(), navigate: false);
    }

    public function render()
    {
        return view('livewire.leaderships.leadership-form', [
            'leaders' => User::where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('name', RoleName::Leader->value))
                ->orderByAccentInsensitive('name')
                ->get(),
            'periods' => AcademicPeriod::whereIn('status', ['planning', 'active'])
                ->orWhere('id', $this->academic_period_id)
                ->orderByDesc('start_date')
                ->get(),
            'programUnits' => ProgramUnit::where('is_active', true)
                ->orWhere('id', $this->program_unit_id)
                ->orderByAccentInsensitive('name')
                ->get(),
            // Cascada Componente > Subcomponente > Actividad (punto 13):
            // cada nivel solo ofrece activos (punto 1) en orden A-Z
            // (punto 6), salvo el que ya tenga asignado el liderazgo que
            // se está editando, aunque se haya desactivado después.
            'components' => Component::where('is_active', true)
                ->orWhere('id', $this->component_id)
                ->orderByAccentInsensitive('name')
                ->get(),
            'availableSubcomponents' => $this->component_id
                ? Subcomponent::where('component_id', $this->component_id)
                    ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $this->subcomponent_id))
                    ->orderByAccentInsensitive('name')
                    ->get()
                : collect(),
            'activities' => $this->component_id
                ? Activity::where('component_id', $this->component_id)
                    ->when($this->subcomponent_id, fn ($query) => $query->where('subcomponent_id', $this->subcomponent_id))
                    ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $this->activity_id))
                    ->orderByAccentInsensitive('name')
                    ->get()
                : collect(),
        ])->title($this->leadership ? 'Editar liderazgo' : 'Nuevo liderazgo');
    }
}
