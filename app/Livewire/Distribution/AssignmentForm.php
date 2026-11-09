<?php

namespace App\Livewire\Distribution;

use App\Enums\EmploymentType;
use App\Enums\RoleName;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\EmploymentTypeHourLimit;
use App\Models\ProgramUnit;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class AssignmentForm extends Component
{
    public ?TeacherAssignment $assignment = null;

    /**
     * Mismo patrón que LeadershipForm: solo recibe estos filtros por la
     * URL (puestos ahí por los enlaces de AssignmentIndex) y los
     * reenvía en "Cancelar" y en el redirect de save().
     */
    #[Url]
    public string $search = '';

    #[Url]
    public ?int $periodFilter = null;

    public function distributionIndexUrl(): string
    {
        return route('distribution.index', ['search' => $this->search, 'periodFilter' => $this->periodFilter ?? '']);
    }

    public ?int $user_id = null;

    public ?int $academic_period_id = null;

    public ?int $activity_id = null;

    public ?int $program_unit_id = null;

    public string $assigned_hours = '';

    public string $notes = '';

    public bool $periodLocked = false;

    /**
     * Revisión de la directora: advertencia en vivo (nunca bloqueante,
     * ver save()) — "Docente sin tipo de vinculación" no es un error de
     * este formulario, es un dato que falta en Usuarios.
     */
    public function selectedTeacherHasNoEmploymentType(): bool
    {
        if (! $this->user_id) {
            return false;
        }

        $teacher = User::find($this->user_id);

        return $teacher !== null && $teacher->employment_type === null;
    }

    public function mount(?TeacherAssignment $teacherAssignment = null): void
    {
        $this->assignment = $teacherAssignment?->exists ? $teacherAssignment : null;

        if ($this->assignment) {
            $this->user_id = $this->assignment->user_id;
            $this->academic_period_id = $this->assignment->academic_period_id;
            $this->activity_id = $this->assignment->activity_id;
            $this->program_unit_id = $this->assignment->program_unit_id;
            $this->assigned_hours = rtrim(rtrim((string) $this->assignment->assigned_hours, '0'), '.');
            $this->notes = (string) $this->assignment->notes;
            $this->periodLocked = in_array($this->assignment->academicPeriod->status->value, ['closed', 'archived']);
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
            'activity_id' => ['required', 'exists:activities,id'],
            'program_unit_id' => ['required', 'exists:program_units,id'],
            'assigned_hours' => ['required', 'numeric', 'min:0.5', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'academic_period_id.exists' => 'El periodo seleccionado no admite nuevas asignaciones (está cerrado o archivado).',
        ]);

        $data['notes'] = $data['notes'] ?: null;

        $duplicateExists = TeacherAssignment::query()
            ->where('user_id', $data['user_id'])
            ->where('academic_period_id', $data['academic_period_id'])
            ->where('activity_id', $data['activity_id'])
            ->where('program_unit_id', $data['program_unit_id'])
            ->when($this->assignment, fn ($query) => $query->whereKeyNot($this->assignment->id))
            ->exists();

        if ($duplicateExists) {
            $this->addError('activity_id', 'Ya existe una asignación idéntica para este docente en este periodo.');

            return;
        }

        // Revisión de la directora: tope semanal de horas por tipo de
        // vinculación — suma TODAS las asignaciones del docente en el
        // periodo (no solo las de esta actividad), excluyendo la que se
        // está editando. Sin vinculación definida, nunca se bloquea (ver
        // selectedTeacherHasNoEmploymentType(), que muestra la advertencia
        // en la vista en vez de un error).
        $teacher = User::find($data['user_id']);

        if ($teacher->employment_type !== null) {
            $limit = EmploymentTypeHourLimit::where('employment_type', $teacher->employment_type)->value('weekly_hour_limit');

            if ($limit !== null) {
                $existingHours = (float) TeacherAssignment::query()
                    ->where('user_id', $data['user_id'])
                    ->where('academic_period_id', $data['academic_period_id'])
                    ->when($this->assignment, fn ($query) => $query->whereKeyNot($this->assignment->id))
                    ->sum('assigned_hours');

                $totalHours = $existingHours + (float) $data['assigned_hours'];

                if ($totalHours > $limit) {
                    $available = max(0, $limit - $existingHours);
                    $employmentTypeLabel = EmploymentType::from($teacher->employment_type)->label();

                    $this->addError('assigned_hours', 'Este docente ya tiene '.$this->formatHours($existingHours)." hora(s) asignada(s) en este periodo; su tope semanal como {$employmentTypeLabel} es de {$limit} hora(s) (disponibles: ".$this->formatHours($available).').');

                    return;
                }
            }
        }

        if ($this->assignment) {
            $this->assignment->update($data);
        } else {
            TeacherAssignment::create($data);
        }

        session()->flash('status', 'Asignación guardada correctamente.');

        $this->redirect($this->distributionIndexUrl(), navigate: false);
    }

    /**
     * Mismo criterio que mount() para $assigned_hours: nunca "40.0", solo
     * "40" (o "37.5" si de verdad tiene decimales).
     */
    private function formatHours(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');
    }

    public function render()
    {
        return view('livewire.distribution.assignment-form', [
            'teachers' => User::where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('name', RoleName::Teacher->value))
                ->orderByAccentInsensitive('name')
                ->get(),
            'periods' => AcademicPeriod::whereIn('status', ['planning', 'active'])
                ->orWhere('id', $this->academic_period_id)
                ->orderByDesc('start_date')
                ->get(),
            'activities' => Activity::with(['component', 'subcomponent'])
                ->where('is_active', true)
                ->orWhere('id', $this->activity_id)
                ->orderByAccentInsensitive('name')
                ->get(),
            'programUnits' => ProgramUnit::where('is_active', true)
                ->orWhere('id', $this->program_unit_id)
                ->orderByAccentInsensitive('name')
                ->get(),
        ])->title($this->assignment ? 'Editar asignación' : 'Nueva asignación');
    }
}
