<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="page-title">Usuarios</h1>
        <a href="{{ route('admin.users.create') }}" class="btn-primary">
            Nuevo usuario
        </a>
    </div>

    <div class="mb-4 flex flex-wrap gap-4">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Buscar por nombre o correo..."
            class="field-input mt-0 w-full max-w-xs"
        >

        <select wire:model.live="roleFilter" class="field-input mt-0 w-auto">
            <option value="">Todos los roles</option>
            @foreach ($roles as $role)
                <option value="{{ $role->name }}">{{ $role->label }}</option>
            @endforeach
        </select>
    </div>

    <div class="table-shell">
        <table class="min-w-full divide-y divide-border-subtle">
            <thead>
                <tr>
                    <th class="table-header-cell">Nombre</th>
                    <th class="table-header-cell">Correo</th>
                    <th class="table-header-cell">Roles</th>
                    <th class="table-header-cell">Estado</th>
                    <th class="table-header-cell text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @forelse ($users as $user)
                    <tr wire:key="user-{{ $user->id }}" class="table-row">
                        <td class="table-cell">{{ $user->name }}</td>
                        <td class="table-cell text-text-secondary">{{ $user->email }}</td>
                        <td class="table-cell text-text-secondary">
                            {{ $user->roles->pluck('label')->join(', ') ?: '—' }}
                        </td>
                        <td class="table-cell">
                            <x-active-badge :active="$user->is_active" />
                        </td>
                        <td class="table-cell text-right">
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn-text">Editar</a>

                            @if (! auth()->user()->is($user))
                                <button
                                    type="button"
                                    wire:click="toggleActive({{ $user->id }})"
                                    wire:confirm="¿Confirmas cambiar el estado de este usuario?"
                                    class="btn-text ml-3 text-text-secondary"
                                >
                                    {{ $user->is_active ? 'Desactivar' : 'Activar' }}
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-empty-state icon="document" title="No hay usuarios registrados" description='Usa el botón "Nuevo usuario" para crear el primero.' />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
</div>
