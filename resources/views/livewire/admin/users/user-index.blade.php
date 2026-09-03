<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-gray-800">Usuarios</h1>
        <a href="{{ route('admin.users.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            Nuevo usuario
        </a>
    </div>

    <div class="mb-4 flex gap-4">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Buscar por nombre o correo..."
            class="w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
        >

        <select wire:model.live="roleFilter" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Todos los roles</option>
            @foreach ($roles as $role)
                <option value="{{ $role->name }}">{{ $role->label }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Nombre</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Correo</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Roles</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Estado</th>
                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $user->email }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ $user->roles->pluck('label')->join(', ') ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if ($user->is_active)
                                <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">Activo</span>
                            @else
                                <span class="rounded-full bg-gray-200 px-2 py-1 text-xs font-medium text-gray-600">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <a href="{{ route('admin.users.edit', $user) }}" class="text-indigo-600 hover:underline">Editar</a>

                            @if (! auth()->user()->is($user))
                                <button
                                    type="button"
                                    wire:click="toggleActive({{ $user->id }})"
                                    wire:confirm="¿Confirmas cambiar el estado de este usuario?"
                                    class="ml-3 text-gray-600 hover:underline"
                                >
                                    {{ $user->is_active ? 'Desactivar' : 'Activar' }}
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No hay usuarios registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
</div>
