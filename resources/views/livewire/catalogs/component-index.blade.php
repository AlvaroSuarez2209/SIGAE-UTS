<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-gray-800">Componentes</h1>
        <button type="button" wire:click="openCreate" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            Nuevo
        </button>
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Nombre</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Estado</th>
                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($components as $component)
                    <tr wire:key="component-{{ $component->id }}">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ $component->name }}</td>
                        <td class="px-4 py-3 text-sm">
                            @if ($component->is_active)
                                <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">Activo</span>
                            @else
                                <span class="rounded-full bg-gray-200 px-2 py-1 text-xs font-medium text-gray-600">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <button type="button" wire:click="openEdit({{ $component->id }})" class="text-indigo-600 hover:underline">Editar</button>
                            <button type="button" wire:click="toggleActive({{ $component->id }})" class="ml-3 text-gray-600 hover:underline">
                                {{ $component->is_active ? 'Desactivar' : 'Activar' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500">No hay componentes registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-10 flex items-center justify-center bg-black/40">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h2 class="mb-4 text-base font-semibold text-gray-800">
                    {{ $editing ? 'Editar componente' : 'Nuevo componente' }}
                </h2>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombre</label>
                        <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:model="is_active" id="is_active" class="rounded border-gray-300">
                        <label for="is_active" class="text-sm text-gray-700">Activo</label>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Guardar
                        </button>
                        <button type="button" wire:click="$set('showModal', false)" class="text-sm text-gray-600 hover:underline">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
