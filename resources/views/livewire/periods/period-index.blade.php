<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-gray-800">Periodos académicos</h1>
        <button type="button" wire:click="openCreate" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            Nuevo periodo
        </button>
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Nombre</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Fechas</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Estado</th>
                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($periods as $period)
                    <tr wire:key="period-{{ $period->id }}">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ $period->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ $period->start_date->format('d/m/Y') }} – {{ $period->end_date->format('d/m/Y') }}
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span @class([
                                'rounded-full px-2 py-1 text-xs font-medium',
                                'bg-gray-100 text-gray-600' => $period->status->value === 'planning',
                                'bg-green-100 text-green-700' => $period->status->value === 'active',
                                'bg-amber-100 text-amber-700' => $period->status->value === 'closed',
                                'bg-slate-200 text-slate-600' => $period->status->value === 'archived',
                            ])>
                                {{ $period->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                            <button type="button" wire:click="openEdit({{ $period->id }})" class="text-indigo-600 hover:underline">Editar</button>

                            @if ($period->status->value === 'planning')
                                <button type="button" wire:click="activate({{ $period->id }})" wire:confirm="¿Activar este periodo?" class="ml-3 text-green-700 hover:underline">Activar</button>
                            @elseif ($period->status->value === 'active')
                                <button type="button" wire:click="close({{ $period->id }})" wire:confirm="¿Cerrar este periodo? Bloqueará cargas y modificaciones ordinarias." class="ml-3 text-amber-700 hover:underline">Cerrar</button>
                            @elseif ($period->status->value === 'closed')
                                <button type="button" wire:click="archive({{ $period->id }})" wire:confirm="¿Archivar este periodo?" class="ml-3 text-slate-600 hover:underline">Archivar</button>
                                <button type="button" wire:click="reopen({{ $period->id }})" wire:confirm="¿Reabrir este periodo? Es una excepción que quedará registrada." class="ml-3 text-gray-600 hover:underline">Reabrir</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">No hay periodos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-10 flex items-center justify-center bg-black/40">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h2 class="mb-4 text-base font-semibold text-gray-800">
                    {{ $editing ? 'Editar periodo' : 'Nuevo periodo' }}
                </h2>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nombre</label>
                        <input type="text" wire:model="name" placeholder="Ej. 2026-1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    @if (! $editing || $editing->status->value === 'planning')
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fecha de inicio</label>
                            <input type="date" wire:model="start_date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('start_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Fecha de fin</label>
                            <input type="date" wire:model="end_date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('end_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <p class="text-sm text-gray-500">
                            Las fechas solo se pueden modificar mientras el periodo está en planeación.
                        </p>
                    @endif

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
