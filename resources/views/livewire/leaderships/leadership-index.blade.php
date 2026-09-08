<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="page-title">Líderes</h1>
        <a href="{{ route('leaderships.create') }}" class="btn-primary">
            Nuevo liderazgo
        </a>
    </div>

    <div class="mb-4">
        <select wire:model.live="periodFilter" class="field-input mt-0 w-auto">
            <option value="">Todos los periodos</option>
            @foreach ($periods as $period)
                <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->status->label() }})</option>
            @endforeach
        </select>
    </div>

    <div class="table-shell">
        <table class="min-w-full divide-y divide-border-subtle">
            <thead>
                <tr>
                    <th class="table-header-cell">Líder</th>
                    <th class="table-header-cell">Periodo</th>
                    <th class="table-header-cell">Ámbito</th>
                    <th class="table-header-cell">Vigencia</th>
                    <th class="table-header-cell text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @forelse ($leaderships as $leadership)
                    <tr wire:key="leadership-{{ $leadership->id }}" class="table-row">
                        <td class="table-cell">{{ $leadership->user->name }}</td>
                        <td class="table-cell text-text-secondary">{{ $leadership->academicPeriod->name }}</td>
                        <td class="table-cell text-text-secondary">
                            {{ $leadership->programUnit->name }}
                            @if ($leadership->activity)
                                <span class="block text-xs text-text-secondary">Solo: {{ $leadership->activity->name }}</span>
                            @else
                                <span class="block text-xs text-text-secondary">Todo el programa</span>
                            @endif
                        </td>
                        <td class="table-cell text-text-secondary">
                            {{ $leadership->starts_at->format('d/m/Y') }}
                            –
                            {{ $leadership->ends_at?->format('d/m/Y') ?? 'vigente' }}
                        </td>
                        <td class="table-cell text-right whitespace-nowrap">
                            <a href="{{ route('leaderships.edit', $leadership) }}" class="btn-text">Editar</a>
                            @unless ($leadership->ends_at)
                                <button type="button" wire:click="endNow({{ $leadership->id }})" wire:confirm="¿Finalizar este liderazgo hoy?" class="btn-text ml-3 text-text-secondary">
                                    Finalizar
                                </button>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-empty-state icon="shield-check" title="No hay liderazgos registrados" description='Usa el botón "Nuevo liderazgo" para asignar un líder.' />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $leaderships->links() }}
    </div>
</div>
