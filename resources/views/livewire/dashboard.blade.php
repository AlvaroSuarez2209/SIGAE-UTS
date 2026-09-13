<div class="space-y-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="page-title">Bienvenido, {{ auth()->user()->name }}</h1>
            <p class="text-sm text-text-secondary">
                Roles: {{ auth()->user()->roles->pluck('label')->join(', ') ?: 'Sin roles asignados' }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <select wire:model.live="periodFilter" class="field-input mt-0 w-auto">
                @foreach ($periods as $period)
                    <option value="{{ $period->id }}">{{ $period->name }}</option>
                @endforeach
            </select>
            @php $selectedPeriod = $periods->firstWhere('id', $periodFilter); @endphp
            @if ($selectedPeriod)
                <x-status-badge :status="$selectedPeriod->status" />
            @endif
        </div>
    </div>

    @if ($teacherPanel)
        <section>
            <h2 class="mb-4 text-xl font-semibold text-text-primary">Panel docente</h2>

            <div class="mb-6 grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-7">
                @foreach (\App\Enums\EvidenceStatus::cases() as $status)
                    <x-kpi-card
                        :value="$teacherPanel['counts'][$status->value]"
                        :label="$status->label()"
                        :color="$status->color()"
                        :icon="$status->icon()"
                    />
                @endforeach
            </div>

            @if ($teacherPanel['compliance']['percentage'] !== null)
                <div class="card mb-6 p-5">
                    <p class="text-sm text-text-secondary">
                        Avance sobre entregables obligatorios:
                        <span class="font-semibold text-text-primary">{{ $teacherPanel['compliance']['percentage'] }}%</span>
                        ({{ $teacherPanel['compliance']['approved'] }} de {{ $teacherPanel['compliance']['total'] }})
                    </p>
                </div>
            @endif

            <div class="table-shell">
                <div class="border-b border-border-subtle px-5 py-4 text-lg font-semibold text-text-primary">Próximos vencimientos (14 días)</div>
                <table class="min-w-full divide-y divide-border-subtle">
                    <tbody class="divide-y divide-border-subtle">
                        @forelse ($teacherPanel['upcoming'] as $evidence)
                            <tr class="table-row">
                                <td class="table-cell">{{ $evidence->deliverable->name }}</td>
                                <td class="table-cell text-text-secondary">{{ $evidence->deliverable->due_at->toReadable() }}</td>
                                <td class="table-cell text-right">
                                    <a href="{{ route('my-deliverables.show', $evidence) }}" class="btn-text">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="table-cell text-text-secondary">No tienes vencimientos próximos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($leaderPanel)
        <section>
            <h2 class="mb-4 text-xl font-semibold text-text-primary">Panel líder</h2>

            <div class="card mb-6 p-5">
                <p class="text-sm text-text-secondary">
                    <span class="font-semibold text-text-primary">{{ $leaderPanel['pendingReviewCount'] }}</span>
                    evidencia(s) pendiente(s) de revisión en tu ámbito.
                    <a href="{{ route('reviews.index') }}" class="btn-text">Ir a la bandeja</a>
                </p>
            </div>

            <div class="table-shell">
                <div class="border-b border-border-subtle px-5 py-4 text-lg font-semibold text-text-primary">Cumplimiento por actividad</div>
                <table class="min-w-full divide-y divide-border-subtle">
                    <thead>
                        <tr>
                            <th class="table-header-cell">Docente</th>
                            <th class="table-header-cell">Actividad</th>
                            <th class="table-header-cell text-right">Avance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @forelse ($leaderPanel['rows'] as $row)
                            <tr class="table-row">
                                <td class="table-cell">{{ $row['teacher']->name }}</td>
                                <td class="table-cell text-text-secondary">{{ $row['activity']->component->name }} — {{ $row['activity']->name }}</td>
                                <td class="table-cell text-right">
                                    @if ($row['compliance']['percentage'] !== null)
                                        <div class="flex items-center justify-end gap-2">
                                            <div class="h-1.5 w-20 shrink-0 overflow-hidden rounded-full bg-surface-muted">
                                                <div class="h-full rounded-full bg-brand-primary" style="width: {{ $row['compliance']['percentage'] }}%"></div>
                                            </div>
                                            <span class="text-text-secondary">{{ $row['compliance']['percentage'] }}% ({{ $row['compliance']['approved'] }}/{{ $row['compliance']['total'] }})</span>
                                        </div>
                                    @else
                                        <span class="text-text-secondary">Sin entregables obligatorios</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="table-cell text-text-secondary">No hay docentes en tu ámbito para este periodo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($coordinationPanel)
        <section>
            <h2 class="mb-4 text-xl font-semibold text-text-primary">Panel de coordinación</h2>

            <div class="mb-6 grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-7">
                @foreach (\App\Enums\EvidenceStatus::cases() as $status)
                    <x-kpi-card
                        :value="$coordinationPanel['counts'][$status->value]"
                        :label="$status->label()"
                        :color="$status->color()"
                        :icon="$status->icon()"
                    />
                @endforeach
            </div>

            <div class="table-shell">
                <div class="border-b border-border-subtle px-5 py-4 text-lg font-semibold text-text-primary">Consolidado por docente</div>
                <table class="min-w-full divide-y divide-border-subtle">
                    <thead>
                        <tr>
                            <th class="table-header-cell">Docente</th>
                            <th class="table-header-cell text-right">Avance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @forelse ($coordinationPanel['teacherRows'] as $row)
                            <tr class="table-row">
                                <td class="table-cell">{{ $row['teacher']->name }}</td>
                                <td class="table-cell text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <x-progress-bar :percentage="$row['compliance']['percentage']" class="h-1.5 w-20" />
                                        <span class="text-text-secondary">{{ $row['compliance']['percentage'] }}% ({{ $row['compliance']['approved'] }}/{{ $row['compliance']['total'] }})</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="table-cell text-text-secondary">No hay docentes con entregables en este periodo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="table-shell mt-6">
                <div class="border-b border-border-subtle px-5 py-4 text-lg font-semibold text-text-primary">Próximos vencimientos (14 días)</div>
                <table class="min-w-full divide-y divide-border-subtle">
                    <thead>
                        <tr>
                            <th class="table-header-cell">Entregable</th>
                            <th class="table-header-cell">Ámbito</th>
                            <th class="table-header-cell">Fecha límite</th>
                            <th class="table-header-cell text-right">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @forelse ($coordinationPanel['upcoming'] as $row)
                            @php $deliverable = $row['deliverable']; @endphp
                            <tr class="table-row">
                                <td class="table-cell">{{ $deliverable->name }}</td>
                                <td class="table-cell text-text-secondary">
                                    @if ($deliverable->isCrossCutting())
                                        <span class="badge bg-category-subtle text-category">
                                            <x-icon name="link" class="h-3.5 w-3.5" />
                                            Transversal
                                        </span>
                                    @else
                                        {{ $deliverable->activity->component->name }} — {{ $deliverable->activity->name }}
                                    @endif
                                </td>
                                <td class="table-cell text-text-secondary">{{ $deliverable->due_at->toReadable() }}</td>
                                <td class="table-cell text-right">
                                    @if ($row['pending'] === 0)
                                        <span class="badge bg-status-success-subtle text-status-success">
                                            <x-icon name="check-circle" class="h-3.5 w-3.5" />
                                            Todo enviado
                                        </span>
                                    @else
                                        <span class="badge bg-status-warning-subtle text-status-warning">
                                            <x-icon name="alert-triangle" class="h-3.5 w-3.5" />
                                            {{ $row['pending'] }} de {{ $row['total'] }} pendientes
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <x-empty-state icon="check-circle" title="No hay vencimientos en los próximos 14 días" description="Todo lo asignado para este periodo está fuera de esa ventana o ya se completó." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @unless ($teacherPanel || $leaderPanel || $coordinationPanel)
        <div class="card p-6 text-sm text-text-secondary">
            No hay información de seguimiento para mostrar en este periodo.
        </div>
    @endunless
</div>
