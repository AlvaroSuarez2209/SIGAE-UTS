<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="page-title">Programas / Unidades académicas</h1>
        <button type="button" wire:click="openCreate" class="btn-primary">
            Nuevo
        </button>
    </div>

    <x-flash-message />

    <div class="mb-4 flex flex-wrap gap-4">
        <x-search-input
            wire:model.live.debounce.300ms="search"
            placeholder="Buscar programa..."
            class="w-full max-w-xs"
        />

        <select wire:model.live="statusFilter" class="field-input mt-0 w-full md:w-auto">
            <option value="">Todos los estados</option>
            <option value="active">Activo</option>
            <option value="inactive">Inactivo</option>
        </select>
    </div>

    <div class="table-shell">
        <table class="min-w-full divide-y divide-border-subtle">
            <thead>
                <tr>
                    <th class="table-header-cell">Nombre</th>
                    <th class="table-header-cell">Estado</th>
                    <th class="table-header-cell min-w-[240px] text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @forelse ($programUnits as $unit)
                    <tr wire:key="unit-{{ $unit->id }}" class="table-row">
                        <td class="table-cell">{{ $unit->name }}</td>
                        <td class="table-cell">
                            <x-active-badge :active="$unit->is_active" />
                        </td>
                        <td class="table-cell text-right">
                            <x-table-actions>
                                <button type="button" wire:click="openEdit({{ $unit->id }})" class="btn-text">Editar</button>
                                <x-table-toggle-slot>
                                    @if ($unit->is_active)
                                        <button
                                            type="button"
                                            class="btn-text text-text-secondary"
                                            @click="$dispatch('confirm-modal', {
                                                title: 'Desactivar programa',
                                                body: '¿Desactivar el programa <strong>{{ e($unit->name) }}</strong>? Ya no estará disponible para nuevas asignaciones, pero las asignaciones ya creadas no se ven afectadas.',
                                                confirmLabel: 'Desactivar',
                                                variant: 'warning',
                                                action: () => $wire.toggleActive({{ $unit->id }}),
                                            })"
                                        >Desactivar</button>
                                    @else
                                        <button type="button" wire:click="toggleActive({{ $unit->id }})" class="btn-text text-text-secondary">
                                            Activar
                                        </button>
                                    @endif
                                </x-table-toggle-slot>
                                @can('delete', $unit)
                                    <x-delete-button wire:click="openDeleteConfirmation({{ $unit->id }})">Eliminar</x-delete-button>
                                @endcan
                            </x-table-actions>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">
                            <x-empty-state icon="document" title="No hay programas registrados" description='Usa el botón "Nuevo" para crear el primero.' />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showModal)
        <div
            class="fixed inset-0 z-10 flex items-center justify-center bg-text-primary/40 px-4"
            x-on:keydown.escape.window="$wire.closeModal()"
            role="dialog"
            aria-modal="true"
            aria-labelledby="program-unit-modal-title"
        >
            <div class="card w-full max-w-md p-6 shadow-lg">
                <h2 id="program-unit-modal-title" class="mb-4 text-base font-semibold text-text-primary">
                    {{ $editing ? 'Editar programa' : 'Nuevo programa' }}
                </h2>

                <form wire:submit="save" class="space-y-4">
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
                        <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">Guardar</span>
                            <span wire:loading wire:target="save">Guardando...</span>
                        </button>
                        <button type="button" wire:click="closeModal" class="btn-text text-text-secondary">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <x-catalog-delete-modal
        :model="$deleting"
        type-label="programa"
        :blockers="$deletionBlockers"
        :suggestion="$deletionSuggestion"
        :error="$deletionError"
        delete-action="deleteProgramUnit"
        deactivate-action="toggleActive"
    />
</div>
