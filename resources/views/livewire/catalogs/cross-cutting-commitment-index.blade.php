<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="page-title">Compromisos transversales</h1>
        <button type="button" wire:click="openCreate" class="btn-primary">
            Nuevo
        </button>
    </div>

    <x-flash-message />

    <p class="section-subtitle">
        Clasificaciones disponibles para agrupar compromisos transversales. Aquí se administran las categorías, no los compromisos en sí.
    </p>

    <div class="mb-4 flex flex-wrap gap-4">
        <x-search-input
            wire:model.live.debounce.300ms="search"
            placeholder="Buscar compromiso..."
            class="w-full max-w-xs"
        />

        <select wire:model.live="statusFilter" class="field-input mt-0 w-auto">
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
                    <th class="table-header-cell text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @forelse ($commitments as $commitment)
                    <tr wire:key="commitment-{{ $commitment->id }}" class="table-row">
                        <td class="table-cell">{{ $commitment->name }}</td>
                        <td class="table-cell">
                            <x-active-badge :active="$commitment->is_active" />
                        </td>
                        <td class="table-cell text-right">
                            <button type="button" wire:click="openEdit({{ $commitment->id }})" class="btn-text">Editar</button>
                            @if ($commitment->is_active)
                                <button
                                    type="button"
                                    class="btn-text ml-4 text-text-secondary"
                                    @click="$dispatch('confirm-modal', {
                                        title: 'Desactivar compromiso',
                                        body: '¿Desactivar el compromiso <strong>{{ e($commitment->name) }}</strong>? Ya no estará disponible para crear nuevos entregables transversales a partir de él, pero los entregables ya creados no se ven afectados.',
                                        confirmLabel: 'Desactivar',
                                        variant: 'warning',
                                        action: () => $wire.toggleActive({{ $commitment->id }}),
                                    })"
                                >Desactivar</button>
                            @else
                                <button type="button" wire:click="toggleActive({{ $commitment->id }})" class="btn-text ml-4 text-text-secondary">
                                    Activar
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">
                            <x-empty-state icon="document" title="No hay compromisos transversales registrados" description='Usa el botón "Nuevo" para crear el primero.' />
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
            aria-labelledby="commitment-modal-title"
        >
            <div class="card w-full max-w-md p-6 shadow-lg">
                <h2 id="commitment-modal-title" class="mb-4 text-base font-semibold text-text-primary">
                    {{ $editing ? 'Editar compromiso' : 'Nuevo compromiso' }}
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
</div>
