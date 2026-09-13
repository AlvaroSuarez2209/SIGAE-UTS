<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="page-title">Actividades</h1>
        <button type="button" wire:click="openCreate" class="btn-primary">
            Nueva
        </button>
    </div>

    <div class="mb-4 flex flex-wrap gap-4">
        <x-search-input
            wire:model.live.debounce.300ms="search"
            placeholder="Buscar actividad..."
            class="w-full max-w-xs"
        />
    </div>

    <div class="table-shell">
        <table class="min-w-full divide-y divide-border-subtle">
            <thead>
                <tr>
                    <th class="table-header-cell">Nombre</th>
                    <th class="table-header-cell">Componente</th>
                    <th class="table-header-cell">Subcomponente</th>
                    <th class="table-header-cell">Estado</th>
                    <th class="table-header-cell text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @forelse ($activities as $activity)
                    <tr wire:key="activity-{{ $activity->id }}" class="table-row">
                        <td class="table-cell">{{ $activity->name }}</td>
                        <td class="table-cell text-text-secondary">{{ $activity->component->name }}</td>
                        <td class="table-cell text-text-secondary">{{ $activity->subcomponent?->name ?? '—' }}</td>
                        <td class="table-cell">
                            <x-active-badge :active="$activity->is_active" />
                        </td>
                        <td class="table-cell text-right">
                            <button type="button" wire:click="openEdit({{ $activity->id }})" class="btn-text">Editar</button>
                            @if ($activity->is_active)
                                <button
                                    type="button"
                                    class="btn-text ml-4 text-text-secondary"
                                    @click="$dispatch('confirm-modal', {
                                        title: 'Desactivar actividad',
                                        body: '¿Desactivar la actividad <strong>{{ e($activity->name) }}</strong>? Ya no estará disponible para nuevas asignaciones o entregables, pero las asignaciones y entregables ya creados no se ven afectados.',
                                        confirmLabel: 'Desactivar',
                                        variant: 'warning',
                                        action: () => $wire.toggleActive({{ $activity->id }}),
                                    })"
                                >Desactivar</button>
                            @else
                                <button type="button" wire:click="toggleActive({{ $activity->id }})" class="btn-text ml-4 text-text-secondary">
                                    Activar
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-empty-state icon="document" title="No hay actividades registradas" description='Usa el botón "Nueva" para crear la primera.' />
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
            aria-labelledby="activity-modal-title"
        >
            <div class="card w-full max-w-md p-6 shadow-lg" @click.outside="$wire.closeModal()">
                <h2 id="activity-modal-title" class="mb-4 text-base font-semibold text-text-primary">
                    {{ $editing ? 'Editar actividad' : 'Nueva actividad' }}
                </h2>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="field-label">Componente</label>
                        <select wire:model.live="component_id" class="field-input">
                            <option value="">Selecciona un componente</option>
                            @foreach ($components as $component)
                                <option value="{{ $component->id }}">{{ $component->name }}</option>
                            @endforeach
                        </select>
                        @error('component_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="field-label">
                            Subcomponente <span class="font-normal text-text-secondary">(opcional)</span>
                        </label>
                        <select wire:model="subcomponent_id" class="field-input" @if (! $component_id) disabled @endif>
                            <option value="">Sin subcomponente</option>
                            @foreach ($availableSubcomponents as $subcomponent)
                                <option value="{{ $subcomponent->id }}">{{ $subcomponent->name }}</option>
                            @endforeach
                        </select>
                        @error('subcomponent_id') <p class="field-error">{{ $message }}</p> @enderror
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
                        <button type="button" wire:click="closeModal" class="btn-text text-text-secondary">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
