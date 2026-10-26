<div>
    <h1 class="mb-6 page-title">Evidencias exentas</h1>

    <p class="section-subtitle">
        Evidencias marcadas como exentas — solo lectura, sin acciones aquí.
    </p>

    <div class="mb-4">
        <select wire:model.live="periodFilter" class="field-input mt-0 w-full md:w-auto">
            <option value="">Todos los periodos</option>
            @foreach ($periods as $period)
                <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->status->label() }})</option>
            @endforeach
        </select>
    </div>

    <div class="table-shell">
        <table class="w-full table-fixed max-md:table-auto divide-y divide-border-subtle">
            <colgroup>
                <col class="w-[16%] max-md:w-auto">
                <col class="w-[20%] max-md:w-auto">
                <col class="w-[18%] max-md:w-auto">
                <col class="w-[12%] max-md:w-auto">
                <col class="w-[12%] max-md:w-auto">
                <col class="w-[22%] max-md:w-auto">
            </colgroup>
            <thead>
                <tr>
                    <th class="table-header-cell">Docente</th>
                    <th class="table-header-cell">Entregable</th>
                    <th class="table-header-cell">Actividad</th>
                    <th class="table-header-cell">Fecha límite</th>
                    <th class="table-header-cell">Exención</th>
                    <th class="table-header-cell">Razón</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @forelse ($exempt as $evidence)
                    <tr wire:key="exempt-{{ $evidence->id }}" class="table-row">
                        <td class="table-cell">{{ $evidence->user->name }}</td>
                        <td class="table-cell text-text-secondary">{{ $evidence->deliverable->name }}</td>
                        <td class="table-cell text-text-secondary">
                            @if ($evidence->deliverable->isCrossCutting())
                                <span class="badge bg-category-subtle text-category">
                                    <x-icon name="link" class="h-3.5 w-3.5" />
                                    Transversal
                                </span>
                            @else
                                {{ $evidence->deliverable->activity->component->name }} — {{ $evidence->deliverable->activity->name }}
                            @endif
                        </td>
                        <td class="table-cell text-text-secondary">{{ $evidence->deliverable->due_at->toReadable() }}</td>
                        <td class="table-cell text-text-secondary">
                            @php
                                $history = $exemptionHistory->get($evidence->id, collect());
                                $currentMark = $history->where('action', 'evidence_marked_exempt')->last();
                            @endphp
                            @if ($currentMark)
                                <p>{{ $currentMark->user?->name ?? 'Usuario eliminado' }}</p>
                                <p class="text-sm">{{ $currentMark->created_at->toReadable() }}</p>
                            @else
                                {{ $evidence->updated_at->toReadable() }}
                            @endif

                            @if ($history->count() > 1)
                                <details class="mt-1">
                                    <summary class="cursor-pointer text-sm text-brand-primary">Ver histórico ({{ $history->count() }})</summary>
                                    <ul class="mt-1 space-y-1 text-sm">
                                        @foreach ($history as $entry)
                                            <li>
                                                {{ $entry->action === 'evidence_marked_exempt' ? 'Marcada' : 'Retirada' }}
                                                por {{ $entry->user?->name ?? 'Usuario eliminado' }}
                                                — {{ $entry->created_at->toReadable() }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        </td>
                        <td class="table-cell truncate text-text-secondary" title="{{ $evidence->exemption_reason }}">
                            {{ $evidence->exemption_reason }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-empty-state icon="shield-check" title="No hay evidencias exentas" description="Aquí aparecerán las evidencias que se marquen como exentas." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $exempt->links() }}
    </div>
</div>
