<?php

namespace App\Livewire\Admin\Users;

use App\Livewire\Concerns\HasStandardPagination;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class UserIndex extends Component
{
    use HasStandardPagination, WithPagination;

    public string $search = '';

    public string $roleFilter = '';

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

        $user->update(['is_active' => ! $user->is_active]);
    }

    public function render()
    {
        $users = User::query()
            ->with('roles')
            ->when($this->search, fn ($query) => $query
                ->where(fn ($q) => $q
                    ->where('name', 'ilike', "%{$this->search}%")
                    ->orWhere('email', 'ilike', "%{$this->search}%")
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
