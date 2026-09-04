<div>
    <h1 class="mb-6 text-lg font-semibold text-gray-800">Bitácora de auditoría</h1>
    <p class="mb-4 text-sm text-gray-500">
        Registro de solo lectura de accesos, cargas, envíos, revisiones, aprobaciones, devoluciones y cambios
        administrativos. Ningún usuario, incluido el Administrador, puede editar o borrar estos registros desde la
        aplicación.
    </p>

    <div class="mb-4 flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Usuario</label>
            <select wire:model.live="userFilter" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todos</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Acción</label>
            <select wire:model.live="actionFilter" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Todas</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}">{{ $action }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Desde</label>
            <input type="date" wire:model.live="fromFilter" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Hasta</label>
            <input type="date" wire:model.live="toFilter" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white shadow">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Fecha</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Usuario</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Acción</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Objeto</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">IP</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Detalle</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($logs as $log)
                    <tr wire:key="log-{{ $log->id }}">
                        <td class="px-4 py-2 text-sm text-gray-600">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td class="px-4 py-2 text-sm text-gray-800">{{ $log->user->name ?? 'Sistema' }}</td>
                        <td class="px-4 py-2 text-sm text-gray-600">{{ $log->action }}</td>
                        <td class="px-4 py-2 text-sm text-gray-600">{{ $log->auditableLabel() }}</td>
                        <td class="px-4 py-2 text-sm text-gray-400">{{ $log->ip_address ?? '—' }}</td>
                        <td class="px-4 py-2 text-xs text-gray-400">
                            @if ($log->metadata)
                                <code>{{ json_encode($log->metadata) }}</code>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-400">No hay registros con estos filtros.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
</div>
