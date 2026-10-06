<div>
    <h1 class="mb-6 page-title">Evidencias exentas</h1>

    <p class="section-subtitle">
        Evidencias marcadas como exentas — solo lectura, sin acciones aquí.
    </p>

    <div class="mb-4">
        <select wire:model.live="periodFilter" class="field-input mt-0 w-auto">
            <option value="">Todos los periodos</option>
            @foreach ($periods as $period)
                <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->status->label() }})</option>
            @endforeach
        </select>
    </div>

    <div class="table-shell">
        <table class="w-full table-fixed divide-y divide-border-subtle">
            <colgroup>
                <col class="w-[16%]">
                <col class="w-[20%]">
                <col class="w-[18%]">
                <col class="w-[12%]">
                <col class="w-[12%]">
                <col class="w-[22%]">
            </colgroup>
            <thead>
                <tr>
                    <th class="table-header-cell">Docente</th>
                    <th class="table-header-cell">Entregable</th>
                    <th class="table-header-cell">Actividad</th>
                    <th class="table-header-cell">Fecha límite</th>
                    <th class="table-header-cell">Exenta desde</th>
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
                        <td class="table-cell text-text-secondary">{{ $evidence->updated_at->toReadable() }}</td>
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
