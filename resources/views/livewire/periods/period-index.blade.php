<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="page-title">Periodos académicos</h1>
        <button type="button" wire:click="openCreate" class="btn-primary">
            Nuevo periodo
        </button>
    </div>

    <div class="table-shell">
        <table class="min-w-full divide-y divide-border-subtle">
            <thead>
                <tr>
                    <th class="table-header-cell">Nombre</th>
                    <th class="table-header-cell">Fechas</th>
                    <th class="table-header-cell">Estado</th>
                    <th class="table-header-cell text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @forelse ($periods as $period)
                    <tr wire:key="period-{{ $period->id }}" class="table-row">
                        <td class="table-cell">{{ $period->name }}</td>
                        <td class="table-cell text-text-secondary">
                            {{ $period->start_date->toReadable() }} – {{ $period->end_date->toReadable() }}
                        </td>
                        <td class="table-cell">
                            <x-status-badge :status="$period->status" />
                        </td>
                        <td class="table-cell text-right whitespace-nowrap">
                            <button type="button" wire:click="openEdit({{ $period->id }})" class="btn-text">Editar</button>

                            @if ($period->status->value === 'planning')
                                <button
                                    type="button"
                                    class="btn-text ml-4 text-status-success"
                                    @click="$dispatch('confirm-modal', {
                                        title: 'Activar periodo académico',
                                        body: '¿Activar el periodo <strong>{{ e($period->name) }}</strong>? Los docentes podrán empezar a cargar evidencias para este periodo.',
                                        confirmLabel: 'Activar periodo',
                                        variant: 'success',
                                        action: () => $wire.activate({{ $period->id }}),
                                    })"
                                >Activar</button>
                            @elseif ($period->status->value === 'active')
                                <button
                                    type="button"
                                    class="btn-text ml-4 text-status-warning"
                                    @click="$dispatch('confirm-modal', {
                                        title: 'Cerrar periodo académico',
                                        body: '¿Cerrar el periodo <strong>{{ e($period->name) }}</strong>? Esta acción bloqueará las cargas y modificaciones ordinarias para este periodo.',
                                        confirmLabel: 'Cerrar periodo',
                                        variant: 'warning',
                                        action: () => $wire.close({{ $period->id }}),
                                    })"
                                >Cerrar</button>
                            @elseif ($period->status->value === 'closed')
                                <button
                                    type="button"
                                    class="btn-text ml-4 text-secondary"
                                    @click="$dispatch('confirm-modal', {
                                        title: 'Archivar periodo académico',
                                        body: '¿Archivar el periodo <strong>{{ e($period->name) }}</strong>? Pasará a un estado de solo lectura.',
                                        confirmLabel: 'Archivar periodo',
                                        variant: 'secondary',
                                        action: () => $wire.archive({{ $period->id }}),
                                    })"
                                >Archivar</button>
                                <button
                                    type="button"
                                    class="btn-text ml-4 text-text-secondary"
                                    @click="$dispatch('confirm-modal', {
                                        title: 'Reabrir periodo académico',
                                        body: '¿Reabrir el periodo <strong>{{ e($period->name) }}</strong>? Es una excepción que quedará registrada.',
                                        confirmLabel: 'Reabrir periodo',
                                        variant: 'warning',
                                        action: () => $wire.reopen({{ $period->id }}),
                                    })"
                                >Reabrir</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <x-empty-state icon="clock" title="No hay periodos registrados" description='Usa el botón "Nuevo periodo" para crear el primero.' />
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
            aria-labelledby="period-modal-title"
        >
            <div class="card w-full max-w-md p-6 shadow-lg" @click.outside="$wire.closeModal()">
                <h2 id="period-modal-title" class="mb-4 text-base font-semibold text-text-primary">
                    {{ $editing ? 'Editar periodo' : 'Nuevo periodo' }}
                </h2>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="field-label">Nombre</label>
                        <input type="text" wire:model="name" placeholder="Ej. 2026-1" class="field-input">
                        @error('name') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    @if (! $editing || $editing->status->value === 'planning')
                        <div>
                            <label class="field-label">Fecha de inicio</label>
                            <input type="date" wire:model="start_date" class="field-input">
                            @error('start_date') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="field-label">Fecha de fin</label>
                            <input type="date" wire:model="end_date" class="field-input">
                            @error('end_date') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <p class="field-help">
                            Las fechas solo se pueden modificar mientras el periodo está en planeación.
                        </p>
                    @endif

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
