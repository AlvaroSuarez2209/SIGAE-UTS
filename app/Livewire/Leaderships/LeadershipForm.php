<?php

namespace App\Livewire\Leaderships;

use App\Enums\RoleName;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class LeadershipForm extends Component
{
    public ?Leadership $leadership = null;

    public ?int $user_id = null;

    public ?int $academic_period_id = null;

    public ?int $program_unit_id = null;

    public ?int $activity_id = null;

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
            $this->starts_at = $this->leadership->starts_at->toDateString();
            $this->ends_at = $this->leadership->ends_at?->toDateString() ?? '';
            $this->periodLocked = in_array($this->leadership->academicPeriod->status->value, ['closed', 'archived']);
        } else {
            $this->starts_at = now()->toDateString();
        }
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
            Leadership::create($data);
        }

        session()->flash('status', 'Liderazgo guardado correctamente.');

        $this->redirect(route('leaderships.index'), navigate: false);
    }

    public function render()
    {
        return view('livewire.leaderships.leadership-form', [
            'leaders' => User::where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('name', RoleName::Leader->value))
                ->orderBy('name')
                ->get(),
            'periods' => AcademicPeriod::whereIn('status', ['planning', 'active'])
                ->orWhere('id', $this->academic_period_id)
                ->orderByDesc('start_date')
                ->get(),
            'programUnits' => ProgramUnit::where('is_active', true)
                ->orWhere('id', $this->program_unit_id)
                ->orderBy('name')
                ->get(),
            'activities' => Activity::with(['component', 'subcomponent'])
                ->where('is_active', true)
                ->orWhere('id', $this->activity_id)
                ->orderBy('name')
                ->get(),
        ])->title($this->leadership ? 'Editar liderazgo' : 'Nuevo liderazgo');
    }
}
