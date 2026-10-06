<?php

namespace App\Livewire\Admin\Users;

use App\Enums\AcademicPeriodStatus;
use App\Enums\DocumentType;
use App\Enums\RoleName;
use App\Models\AcademicPeriod;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\PasswordPolicy;
use App\Services\PendingWorkChecker;
use App\Services\TeacherImport\TeacherImportService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class UserForm extends Component
{
    public ?User $user = null;

    public string $name = '';

    public string $document_number = '';

    public string $email = '';

    public string $password = '';

    public bool $is_active = true;

    public array $selectedRoles = [];

    public string $document_type = '';

    public ?int $program_unit_id = null;

    public function mount(?User $user = null): void
    {
        // Livewire/the container can hand mount() a freshly instantiated,
        // non-persisted User for the nullable type-hint instead of null
        // (e.g. on the create route, which has no {user} segment) — only
        // treat it as "editing" when it's an actual persisted record.
        $this->user = $user?->exists ? $user : null;

        Gate::authorize($this->user ? 'update' : 'create', $this->user ?? User::class);

        if ($this->user) {
            $this->name = $this->user->name;
            $this->document_number = (string) $this->user->document_number;
            $this->email = $this->user->email;
            $this->is_active = $this->user->is_active;
            $this->selectedRoles = $this->user->roles->pluck('name')->all();
            $this->document_type = (string) $this->user->document_type;
            $this->program_unit_id = $this->user->program_unit_id;
        }
    }

    /**
     * Mismo criterio que la importación masiva (Docente y/o Líder son los
     * únicos roles para los que "tipo de documento"/"programa de
     * adscripción" tienen sentido real) — Administrador/Coordinación/
     * Auditor sin ninguno de esos dos roles no necesitan llenarlos.
     */
    public function requiresProgramInfo(): bool
    {
        return array_intersect($this->selectedRoles, [RoleName::Teacher->value, RoleName::Leader->value]) !== [];
    }

    /**
     * Aviso informativo (nunca bloqueante, ver diagnóstico): cambiar
     * `users.program_unit_id` no toca la distribución ni los liderazgos
     * reales de nadie (son tablas independientes), pero un Administrador
     * podría asumir lo contrario si esta persona ya tiene asignaciones o
     * liderazgos vigentes en el periodo activo.
     */
    public function hasVigenteAssignmentsOrLeaderships(): bool
    {
        if (! $this->user) {
            return false;
        }

        $activePeriodId = AcademicPeriod::where('status', AcademicPeriodStatus::Active)->value('id');

        if (! $activePeriodId) {
            return false;
        }

        return $this->user->teacherAssignments()->where('academic_period_id', $activePeriodId)->exists()
            || $this->user->leaderships()->where('academic_period_id', $activePeriodId)->exists();
    }

    public function save(): void
    {
        Gate::authorize($this->user ? 'update' : 'create', $this->user ?? User::class);

        $requiresProgramInfo = $this->requiresProgramInfo();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:50', Rule::unique('users', 'document_number')->ignore($this->user)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user)],
            'password' => [$this->user ? 'nullable' : 'required', PasswordPolicy::rules()],
            'is_active' => ['boolean'],
            'selectedRoles' => ['required', 'array', 'min:1'],
            'selectedRoles.*' => ['exists:roles,name'],
            'document_type' => [$requiresProgramInfo ? 'required' : 'nullable', Rule::in(TeacherImportService::DOCUMENT_TYPES)],
            'program_unit_id' => [$requiresProgramInfo ? 'required' : 'nullable', 'exists:program_units,id'],
        ]);

        if ($this->user && $data['is_active'] && $this->blocksRoleRemoval($data['selectedRoles'])) {
            return;
        }

        $userData = [
            'name' => $data['name'],
            'document_number' => $data['document_number'] ?: null,
            'email' => $data['email'],
            'is_active' => $data['is_active'],
            'document_type' => $data['document_type'] ?: null,
            'program_unit_id' => $data['program_unit_id'] ?: null,
        ];

        if (! empty($data['password'])) {
            $userData['password'] = Hash::make($data['password']);
        }

        if ($this->user) {
            $this->user->update($userData);
        } else {
            $this->user = User::create($userData);
        }

        $roleIds = Role::whereIn('name', $data['selectedRoles'])->pluck('id');
        $this->user->roles()->sync($roleIds);

        session()->flash('status', 'Usuario guardado correctamente.');

        $this->redirect(route('admin.users.index'), navigate: false);
    }

    /**
     * Quitar Docente o Líder a alguien con trabajo pendiente bajo ese rol
     * dejaría evidencias/revisiones huérfanas sin nadie responsable — se
     * bloquea, no solo se advierte (mismo criterio que
     * UserIndex::toggleActive(), que aplica esta misma regla al
     * desactivar la cuenta por completo — ambas comparten el conteo vía
     * PendingWorkChecker). Solo aplica al quitar un rol existente (nunca
     * al agregar uno) y se omite si este mismo guardado además desactiva
     * la cuenta (no toca roles, así que toggleActive() ya cubre ese caso
     * por su cuenta).
     */
    private function blocksRoleRemoval(array $selectedRoleNames): bool
    {
        $removedRoleNames = array_diff($this->user->roles()->pluck('name')->all(), $selectedRoleNames);

        if (in_array(RoleName::Teacher->value, $removedRoleNames, true)) {
            $pendingCount = PendingWorkChecker::pendingEvidenceCountAsTeacher($this->user);

            if ($pendingCount > 0) {
                $this->addError('selectedRoles', $pendingCount === 1
                    ? 'No se puede quitar el rol Docente: este usuario tiene 1 evidencia pendiente (ni aprobada ni exenta). Reasígnala o resuélvela antes de quitar el rol.'
                    : "No se puede quitar el rol Docente: este usuario tiene {$pendingCount} evidencias pendientes (ni aprobadas ni exentas). Reasígnalas o resuélvelas antes de quitar el rol.");

                return true;
            }
        }

        if (in_array(RoleName::Leader->value, $removedRoleNames, true)) {
            $pendingCount = PendingWorkChecker::pendingReviewCountAsLeader($this->user);

            if ($pendingCount > 0) {
                $this->addError('selectedRoles', $pendingCount === 1
                    ? 'No se puede quitar el rol Líder: este usuario tiene 1 revisión pendiente bajo su liderazgo. Reasígnala o resuélvela antes de quitar el rol.'
                    : "No se puede quitar el rol Líder: este usuario tiene {$pendingCount} revisiones pendientes bajo su liderazgo. Reasígnalas o resuélvelas antes de quitar el rol.");

                return true;
            }
        }

        return false;
    }

    public function render()
    {
        return view('livewire.admin.users.user-form', [
            'roles' => Role::orderBy('label')->get(),
            'programUnits' => ProgramUnit::where('is_active', true)
                ->orWhere('id', $this->program_unit_id)
                ->orderBy('name')
                ->get(),
            'documentTypeOptions' => collect(TeacherImportService::DOCUMENT_TYPES)
                ->mapWithKeys(fn (string $code) => [$code => DocumentType::from($code)->label()]),
        ])->title($this->user ? 'Editar usuario' : 'Nuevo usuario');
    }
}
