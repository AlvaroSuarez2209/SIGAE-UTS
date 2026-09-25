<?php

namespace App\Livewire\Admin\Users;

use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Models\Evidence;
use App\Models\Role;
use App\Models\User;
use App\Services\PasswordPolicy;
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
        }
    }

    public function save(): void
    {
        Gate::authorize($this->user ? 'update' : 'create', $this->user ?? User::class);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:50', Rule::unique('users', 'document_number')->ignore($this->user)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user)],
            'password' => [$this->user ? 'nullable' : 'required', PasswordPolicy::rules()],
            'is_active' => ['boolean'],
            'selectedRoles' => ['required', 'array', 'min:1'],
            'selectedRoles.*' => ['exists:roles,name'],
        ]);

        if ($this->user && $data['is_active'] && $this->blocksRoleRemoval($data['selectedRoles'])) {
            return;
        }

        $userData = [
            'name' => $data['name'],
            'document_number' => $data['document_number'] ?: null,
            'email' => $data['email'],
            'is_active' => $data['is_active'],
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
     * bloquea, no solo se advierte. Solo aplica al quitar un rol existente
     * (nunca al agregar uno) y se omite si este mismo guardado además
     * desactiva la cuenta (toggleActive(), en UserIndex, ya es el flujo
     * separado para desactivar por completo, y no toca roles).
     */
    private function blocksRoleRemoval(array $selectedRoleNames): bool
    {
        $removedRoleNames = array_diff($this->user->roles()->pluck('name')->all(), $selectedRoleNames);

        if (in_array(RoleName::Teacher->value, $removedRoleNames, true)) {
            $pendingCount = $this->user->evidences()
                ->whereNotIn('status', [EvidenceStatus::Approved, EvidenceStatus::Exempt])
                ->count();

            if ($pendingCount > 0) {
                $this->addError('selectedRoles', $pendingCount === 1
                    ? 'No se puede quitar el rol Docente: este usuario tiene 1 evidencia pendiente (ni aprobada ni exenta). Reasígnala o resuélvela antes de quitar el rol.'
                    : "No se puede quitar el rol Docente: este usuario tiene {$pendingCount} evidencias pendientes (ni aprobadas ni exentas). Reasígnalas o resuélvelas antes de quitar el rol.");

                return true;
            }
        }

        if (in_array(RoleName::Leader->value, $removedRoleNames, true)) {
            $pendingCount = $this->pendingReviewCountForLeader($this->user);

            if ($pendingCount > 0) {
                $this->addError('selectedRoles', $pendingCount === 1
                    ? 'No se puede quitar el rol Líder: este usuario tiene 1 revisión pendiente bajo su liderazgo. Reasígnala o resuélvela antes de quitar el rol.'
                    : "No se puede quitar el rol Líder: este usuario tiene {$pendingCount} revisiones pendientes bajo su liderazgo. Reasígnalas o resuélvelas antes de quitar el rol.");

                return true;
            }
        }

        return false;
    }

    /**
     * Mismo criterio de alcance que User::canLeadAssignment()/
     * Evidence::isReviewableBy() (período + programa + actividad-o-null,
     * dentro de starts_at/ends_at) — pero sin el atajo de
     * Administrador/Coordinación de isReviewableBy(), porque aquí interesa
     * específicamente lo que depende del rol Líder, no todo lo que este
     * usuario podría revisar por cualquier otro motivo.
     */
    private function pendingReviewCountForLeader(User $leader): int
    {
        return Evidence::where('status', EvidenceStatus::Submitted)
            ->get()
            ->filter(function (Evidence $evidence) use ($leader) {
                if ($evidence->user_id === $leader->id) {
                    return false;
                }

                $assignment = $evidence->matchingTeacherAssignment();

                return $assignment && $leader->canLeadAssignment($assignment);
            })
            ->count();
    }

    public function render()
    {
        return view('livewire.admin.users.user-form', [
            'roles' => Role::orderBy('label')->get(),
        ])->title($this->user ? 'Editar usuario' : 'Nuevo usuario');
    }
}
