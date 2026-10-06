<div>
    <h1 class="mb-6 page-title">Bitácora de auditoría</h1>
    <p class="section-subtitle">
        Registro de solo lectura de accesos, cargas, envíos, revisiones, aprobaciones, devoluciones y cambios
        administrativos. Ningún usuario, incluido el Administrador, puede editar o borrar estos registros desde la
        aplicación.
    </p>

    <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-wrap items-end gap-4">
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
                    @foreach (\App\Services\Audit\AuditLogPresenter::actionOptions($actions) as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
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

        <div class="flex gap-2">
            <a href="{{ route('admin.audit-logs.pdf', $exportFilters) }}" class="btn-secondary">
                <x-icon name="document" class="h-4 w-4" />
                Descargar PDF
            </a>
            <a href="{{ route('admin.audit-logs.excel', $exportFilters) }}" class="btn-secondary">
                <x-icon name="document" class="h-4 w-4" />
                Descargar Excel
            </a>
        </div>
    </div>

    <div class="table-shell">
        <table class="w-full table-fixed divide-y divide-border-subtle">
            <colgroup>
                <col class="w-[16%]">
                <col class="w-[14%]">
                <col class="w-[13%]">
                <col class="w-[14%]">
                <col class="w-[7%]">
                <col class="w-[24%]">
                <col class="w-[12%]">
            </colgroup>
            <thead>
                <tr>
                    <th class="table-header-cell">Fecha</th>
                    <th class="table-header-cell">Usuario</th>
                    <th class="table-header-cell">Acción</th>
                    <th class="table-header-cell">Objeto</th>
                    <th class="table-header-cell">IP</th>
                    <th class="table-header-cell">Detalle</th>
                    <th class="table-header-cell text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @forelse ($logs as $log)
                    @php $detail = \App\Services\Audit\AuditLogPresenter::describeChanges($log); @endphp
                    <tr wire:key="log-{{ $log->id }}" class="table-row">
                        <td class="table-cell text-text-secondary">{{ $log->created_at->toReadable() }}</td>
                        <td class="table-cell">{{ $log->user->name ?? 'Sistema' }}</td>
                        <td class="table-cell text-text-secondary">{{ \App\Services\Audit\AuditLogPresenter::actionLabel($log->action, $log) }}</td>
                        <td class="table-cell truncate text-text-secondary" title="{{ $log->auditableLabel() }}">{{ $log->auditableLabel() }}</td>
                        <td class="table-cell text-text-secondary">{{ $log->ip_address ?? '—' }}</td>
                        <td class="table-cell whitespace-normal text-text-secondary">{{ $detail }}</td>
                        <td class="table-cell text-right">
                            <button type="button" wire:click="showDetail({{ $log->id }})" class="btn-text">Ver detalle</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
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

    @if ($selectedLog)
        <div
            class="fixed inset-0 z-10 flex items-center justify-center bg-text-primary/40 px-4"
            x-on:keydown.escape.window="$wire.closeDetail()"
            role="dialog"
            aria-modal="true"
            aria-labelledby="audit-log-detail-title"
        >
            <div class="card w-full max-w-lg p-6 shadow-lg">
                <h2 id="audit-log-detail-title" class="mb-4 text-base font-semibold text-text-primary">
                    Detalle del registro
                </h2>

                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="field-label">Actor</dt>
                        <dd class="text-text-primary">{{ $selectedLog->user->name ?? 'Sistema' }}</dd>
                    </div>
                    <div>
                        <dt class="field-label">Fecha y hora</dt>
                        <dd class="text-text-primary">{{ $selectedLog->created_at->toReadable() }}</dd>
                    </div>
                    <div>
                        <dt class="field-label">Acción</dt>
                        <dd class="text-text-primary">{{ \App\Services\Audit\AuditLogPresenter::actionLabel($selectedLog->action, $selectedLog) }}</dd>
                    </div>
                    <div>
                        <dt class="field-label">Objeto</dt>
                        <dd class="text-text-primary">{{ $selectedLog->auditableLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="field-label">IP</dt>
                        <dd class="text-text-primary">{{ $selectedLog->ip_address ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="field-label">Descripción</dt>
                        <dd class="text-text-primary">{{ \App\Services\Audit\AuditLogPresenter::describeChanges($selectedLog) ?: '—' }}</dd>
                    </div>

                    @php $changes = \App\Services\Audit\AuditLogPresenter::changeEntries($selectedLog); @endphp
                    @if ($changes)
                        <div>
                            <dt class="field-label">Cambios por campo</dt>
                            <dd>
                                <table class="w-full text-left text-sm">
                                    <thead>
                                        <tr class="text-text-secondary">
                                            <th class="py-1 pr-2 font-medium">Campo</th>
                                            <th class="py-1 pr-2 font-medium">Antes</th>
                                            <th class="py-1 font-medium">Después</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border-subtle">
                                        @foreach ($changes as $change)
                                            <tr>
                                                <td class="py-1 pr-2 text-text-primary">{{ $change['label'] }}</td>
                                                <td class="py-1 pr-2 text-text-secondary">{{ $change['before'] ?? '—' }}</td>
                                                <td class="py-1 text-text-primary">{{ $change['after'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </dd>
                        </div>
                    @endif
                </dl>

                <div class="flex items-center gap-3 pt-5">
                    <button type="button" wire:click="closeDetail" class="btn-text text-text-secondary">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
