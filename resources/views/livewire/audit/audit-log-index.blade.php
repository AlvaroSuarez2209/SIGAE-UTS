<div>
    <h1 class="mb-6 page-title">Bitácora de auditoría</h1>
    <p class="section-subtitle">
        Registro de solo lectura de accesos, cargas, envíos, revisiones, aprobaciones, devoluciones y cambios
        administrativos. Ningún usuario, incluido el Administrador, puede editar o borrar estos registros desde la
        aplicación.
    </p>

    <div class="mb-4 flex flex-wrap items-end gap-4">
        <div>
            <label class="field-label">Usuario</label>
            <select wire:model.live="userFilter" class="field-input">
                <option value="">Todos</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="field-label">Acción</label>
            <select wire:model.live="actionFilter" class="field-input">
                <option value="">Todas</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}">{{ $action }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="field-label">Desde</label>
            <input type="date" wire:model.live="fromFilter" class="field-input">
        </div>

        <div>
            <label class="field-label">Hasta</label>
            <input type="date" wire:model.live="toFilter" class="field-input">
        </div>
    </div>

    <div class="table-shell">
        <table class="min-w-full divide-y divide-border-subtle">
            <thead>
                <tr>
                    <th class="table-header-cell">Fecha</th>
                    <th class="table-header-cell">Usuario</th>
                    <th class="table-header-cell">Acción</th>
                    <th class="table-header-cell">Objeto</th>
                    <th class="table-header-cell">IP</th>
                    <th class="table-header-cell">Detalle</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @forelse ($logs as $log)
                    <tr wire:key="log-{{ $log->id }}" class="table-row">
                        <td class="table-cell text-text-secondary">{{ $log->created_at->toReadable() }}</td>
                        <td class="table-cell">{{ $log->user->name ?? 'Sistema' }}</td>
                        <td class="table-cell text-text-secondary">{{ $log->action }}</td>
                        <td class="table-cell text-text-secondary">{{ $log->auditableLabel() }}</td>
                        <td class="table-cell text-text-secondary">{{ $log->ip_address ?? '—' }}</td>
                        <td class="table-cell text-xs text-text-secondary">
                            @if ($log->metadata)
                                <code>{{ json_encode($log->metadata) }}</code>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-empty-state icon="inbox" title="No hay registros con estos filtros" description="Ajusta los filtros de usuario, acción o fecha." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
</div>
