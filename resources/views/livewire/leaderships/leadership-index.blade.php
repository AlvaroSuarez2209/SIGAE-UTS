<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-gray-800">Líderes</h1>
        <a href="{{ route('leaderships.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            Nuevo liderazgo
        </a>
    </div>

    <div class="mb-4">
        <select wire:model.live="periodFilter" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Todos los periodos</option>
            @foreach ($periods as $period)
                <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->status->label() }})</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white shadow">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Líder</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Periodo</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Ámbito</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Vigencia</th>
                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($leaderships as $leadership)
                    <tr wire:key="leadership-{{ $leadership->id }}">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ $leadership->user->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $leadership->academicPeriod->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ $leadership->programUnit->name }}
                            @if ($leadership->activity)
                                <span class="block text-xs text-gray-400">Solo: {{ $leadership->activity->name }}</span>
                            @else
                                <span class="block text-xs text-gray-400">Todo el programa</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ $leadership->starts_at->format('d/m/Y') }}
                            –
                            {{ $leadership->ends_at?->format('d/m/Y') ?? 'vigente' }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm whitespace-nowrap">
                            <a href="{{ route('leaderships.edit', $leadership) }}" class="text-indigo-600 hover:underline">Editar</a>
                            @unless ($leadership->ends_at)
                                <button type="button" wire:click="endNow({{ $leadership->id }})" wire:confirm="¿Finalizar este liderazgo hoy?" class="ml-3 text-gray-600 hover:underline">
                                    Finalizar
                                </button>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No hay liderazgos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $leaderships->links() }}
    </div>
</div>
