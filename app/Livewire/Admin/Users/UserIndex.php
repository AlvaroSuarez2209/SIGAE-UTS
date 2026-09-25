<?php

namespace App\Livewire\Admin\Users;

use App\Enums\RoleName;
use App\Livewire\Concerns\HasStandardPagination;
use App\Models\Role;
use App\Models\User;
use App\Services\PendingWorkChecker;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Usuarios')]
class UserIndex extends Component
{
    use HasStandardPagination, WithPagination;

    public string $search = '';

    public string $roleFilter = '';

    public string $deactivationError = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function toggleActive(User $user): void
    {
        Gate::authorize('toggleActive', $user);

        $this->deactivationError = '';

        if ($user->is_active && $this->blocksDeactivation($user)) {
            return;
        }

        $user->update(['is_active' => ! $user->is_active]);
    }

    /**
     * Misma regla que UserForm::blocksRoleRemoval() (quitar un rol), pero
     * aplicada a desactivar la cuenta por completo: si el usuario tiene
     * evidencias pendientes como Docente o revisiones pendientes bajo su
     * liderazgo vigente como Líder, desactivarlo dejaría ese trabajo
     * huérfano sin nadie responsable — se bloquea, no solo se advierte.
     * Ambos flujos comparten el conteo vía PendingWorkChecker. Nunca
     * aplica al reactivar (is_active false -> true, ver toggleActive()).
     */
    private function blocksDeactivation(User $user): bool
    {
        if ($user->hasRole(RoleName::Teacher)) {
            $pendingCount = PendingWorkChecker::pendingEvidenceCountAsTeacher($user);

            if ($pendingCount > 0) {
                $this->deactivationError = $pendingCount === 1
                    ? 'No se puede desactivar a este usuario: tiene 1 evidencia pendiente (ni aprobada ni exenta). Reasígnala o resuélvela antes de desactivar la cuenta.'
                    : "No se puede desactivar a este usuario: tiene {$pendingCount} evidencias pendientes (ni aprobadas ni exentas). Reasígnalas o resuélvelas antes de desactivar la cuenta.";

                return true;
            }
        }

        if ($user->hasRole(RoleName::Leader)) {
            $pendingCount = PendingWorkChecker::pendingReviewCountAsLeader($user);

            if ($pendingCount > 0) {
                $this->deactivationError = $pendingCount === 1
                    ? 'No se puede desactivar a este usuario: tiene 1 revisión pendiente bajo su liderazgo. Reasígnala o resuélvela antes de desactivar la cuenta.'
                    : "No se puede desactivar a este usuario: tiene {$pendingCount} revisiones pendientes bajo su liderazgo. Reasígnalas o resuélvelas antes de desactivar la cuenta.";

                return true;
            }
        }

        return false;
    }

    public function render()
    {
        $users = User::query()
            ->with('roles')
            ->when($this->search, fn ($query) => $query
                ->where(fn ($q) => $q
                    ->whereAccentInsensitive('name', $this->search)
                    ->orWhere(fn ($q) => $q->whereAccentInsensitive('email', $this->search))
                )
            )
            ->when($this->roleFilter, fn ($query) => $query
                ->whereHas('roles', fn ($q) => $q->where('name', $this->roleFilter))
            )
            ->orderBy('name')
            ->paginate(self::PER_PAGE);

        return view('livewire.admin.users.user-index', [
            'users' => $users,
            'roles' => Role::orderBy('label')->get(),
        ]);
    }
}
