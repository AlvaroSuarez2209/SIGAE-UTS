<?php

namespace App\Livewire\Admin\Users;

use App\Models\Role;
use App\Models\User;
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
            'password' => [$this->user ? 'nullable' : 'required', 'string', 'min:8'],
            'is_active' => ['boolean'],
            'selectedRoles' => ['required', 'array', 'min:1'],
            'selectedRoles.*' => ['exists:roles,name'],
        ]);

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

    public function render()
    {
        return view('livewire.admin.users.user-form', [
            'roles' => Role::orderBy('label')->get(),
        ]);
    }
}
