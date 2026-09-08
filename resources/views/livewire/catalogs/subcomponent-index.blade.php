<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="page-title">Subcomponentes</h1>
        <button type="button" wire:click="openCreate" class="btn-primary">
            Nuevo
        </button>
    </div>

    <div class="table-shell">
        <table class="min-w-full divide-y divide-border-subtle">
            <thead>
                <tr>
                    <th class="table-header-cell">Nombre</th>
                    <th class="table-header-cell">Componente</th>
                    <th class="table-header-cell">Estado</th>
                    <th class="table-header-cell text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @forelse ($subcomponents as $subcomponent)
                    <tr wire:key="subcomponent-{{ $subcomponent->id }}" class="table-row">
                        <td class="table-cell">{{ $subcomponent->name }}</td>
                        <td class="table-cell text-text-secondary">{{ $subcomponent->component->name }}</td>
                        <td class="table-cell">
                            <x-active-badge :active="$subcomponent->is_active" />
                        </td>
                        <td class="table-cell text-right">
                            <button type="button" wire:click="openEdit({{ $subcomponent->id }})" class="btn-text">Editar</button>
                            <button type="button" wire:click="toggleActive({{ $subcomponent->id }})" class="btn-text ml-3 text-text-secondary">
                                {{ $subcomponent->is_active ? 'Desactivar' : 'Activar' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <x-empty-state icon="document" title="No hay subcomponentes registrados" description='Usa el botón "Nuevo" para crear el primero.' />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-10 flex items-center justify-center bg-text-primary/40 px-4">
            <div class="card w-full max-w-md p-6 shadow-lg">
                <h2 class="mb-4 text-base font-semibold text-text-primary">
                    {{ $editing ? 'Editar subcomponente' : 'Nuevo subcomponente' }}
                </h2>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="field-label">Componente</label>
                        <select wire:model="component_id" class="field-input">
                            <option value="">Selecciona un componente</option>
                            @foreach ($components as $component)
                                <option value="{{ $component->id }}">{{ $component->name }}</option>
                            @endforeach
                        </select>
                        @error('component_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="field-label">Nombre</label>
                        <input type="text" wire:model="name" class="field-input">
                        @error('name') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:model="is_active" id="is_active" class="field-checkbox">
                        <label for="is_active" class="text-sm text-text-secondary">Activo</label>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" class="btn-primary">
                            Guardar
                        </button>
                        <button type="button" wire:click="$set('showModal', false)" class="btn-text text-text-secondary">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
