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
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Usuarios')]
class UserIndex extends Component
{
    use HasStandardPagination, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
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
            $this->dispatch('confirm-modal', title: 'No se puede desactivar', body: $this->deactivationError, confirmLabel: 'Entendido', variant: 'danger');

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

    /**
     * Revisión de la directora, punto 8: Coordinación obtiene su propia
     * pantalla de Usuarios — nunca ve ni puede filtrar por una cuenta con
     * rol Administrador o Coordinación (ver UserPolicy::canManage()), ni
     * siquiera en modo de solo lectura. Administración, en cambio, sigue
     * viendo a todos (sin este scoping) para no romper su visibilidad
     * sobre cuentas de Docente/Líder/Auditor ya existentes (punto 7).
     */
    private function isCoordinationOnly(): bool
    {
        $actor = auth()->user();

        return $actor->hasRole(RoleName::Coordination) && ! $actor->hasRole(RoleName::Administrator);
    }

    public function render()
    {
        $coordinationOnly = $this->isCoordinationOnly();
        $managedRoleNames = [RoleName::Teacher->value, RoleName::Leader->value, RoleName::Auditor->value];

        $users = User::query()
            ->with('roles')
            ->when($coordinationOnly, fn ($query) => $query->where(fn ($q) => $q
                ->doesntHave('roles')
                ->orWhereHas('roles', fn ($rq) => $rq->whereIn('name', $managedRoleNames))
            ))
            ->when($this->search, fn ($query) => $query
                ->where(fn ($q) => $q
                    ->whereAccentInsensitive('name', $this->search)
                    ->orWhere(fn ($q) => $q->whereAccentInsensitive('email', $this->search))
                )
            )
            ->when($this->roleFilter, fn ($query) => $query
                ->whereHas('roles', fn ($q) => $q->where('name', $this->roleFilter))
            )
            ->orderByAccentInsensitive('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE);

        return view('livewire.admin.users.user-index', [
            'users' => $users,
            'roles' => $coordinationOnly
                ? Role::whereIn('name', $managedRoleNames)->orderByAccentInsensitive('label')->get()
                : Role::orderByAccentInsensitive('label')->get(),
        ]);
    }
}
