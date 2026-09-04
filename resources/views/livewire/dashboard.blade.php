<div class="space-y-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-text-primary">Bienvenido, {{ auth()->user()->name }}</h1>
            <p class="text-sm text-text-secondary">
                Roles: {{ auth()->user()->roles->pluck('label')->join(', ') ?: 'Sin roles asignados' }}
            </p>
        </div>

        <select wire:model.live="periodFilter" class="field-input mt-0 w-auto">
            @foreach ($periods as $period)
                <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->status->label() }})</option>
            @endforeach
        </select>
    </div>

    @if ($teacherPanel)
        <section>
            <h2 class="mb-3 text-lg font-semibold text-text-primary">Panel docente</h2>

            <div class="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-7">
                @foreach (\App\Enums\EvidenceStatus::cases() as $status)
                    <x-kpi-card
                        :value="$teacherPanel['counts'][$status->value]"
                        :label="$status->label()"
                        :color="$status->color()"
                    />
                @endforeach
            </div>

            @if ($teacherPanel['compliance']['percentage'] !== null)
                <div class="card mb-4 p-4">
                    <p class="text-sm text-text-secondary">
                        Avance sobre entregables obligatorios:
                        <span class="font-semibold text-text-primary">{{ $teacherPanel['compliance']['percentage'] }}%</span>
                        ({{ $teacherPanel['compliance']['approved'] }} de {{ $teacherPanel['compliance']['total'] }})
                    </p>
                </div>
            @endif

            <div class="table-shell">
                <div class="border-b border-border-subtle px-4 py-2.5 text-sm font-semibold text-text-primary">Próximos vencimientos (14 días)</div>
                <table class="min-w-full divide-y divide-border-subtle">
                    <tbody class="divide-y divide-border-subtle">
                        @forelse ($teacherPanel['upcoming'] as $evidence)
                            <tr class="table-row">
                                <td class="table-cell">{{ $evidence->deliverable->name }}</td>
                                <td class="table-cell text-text-secondary">{{ $evidence->deliverable->due_at->format('d/m/Y H:i') }}</td>
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
            <h2 class="mb-3 text-lg font-semibold text-text-primary">Panel líder</h2>

            <div class="card mb-4 p-4">
                <p class="text-sm text-text-secondary">
                    <span class="font-semibold text-text-primary">{{ $leaderPanel['pendingReviewCount'] }}</span>
                    evidencia(s) pendiente(s) de revisión en tu ámbito.
                    <a href="{{ route('reviews.index') }}" class="btn-text">Ir a la bandeja</a>
                </p>
            </div>

            <div class="table-shell">
                <div class="border-b border-border-subtle px-4 py-2.5 text-sm font-semibold text-text-primary">Cumplimiento por actividad</div>
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
                                <td class="table-cell text-right text-text-secondary">
                                    @if ($row['compliance']['percentage'] !== null)
                                        {{ $row['compliance']['percentage'] }}% ({{ $row['compliance']['approved'] }}/{{ $row['compliance']['total'] }})
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
            <h2 class="mb-3 text-lg font-semibold text-text-primary">Panel de coordinación</h2>

            <div class="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-7">
                @foreach (\App\Enums\EvidenceStatus::cases() as $status)
                    <x-kpi-card
                        :value="$coordinationPanel['counts'][$status->value]"
                        :label="$status->label()"
                        :color="$status->color()"
                    />
                @endforeach
            </div>

            <div class="table-shell">
                <div class="border-b border-border-subtle px-4 py-2.5 text-sm font-semibold text-text-primary">Consolidado por docente</div>
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
                                <td class="table-cell text-right text-text-secondary">
                                    {{ $row['compliance']['percentage'] }}% ({{ $row['compliance']['approved'] }}/{{ $row['compliance']['total'] }})
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="table-cell text-text-secondary">No hay docentes con entregables en este periodo.</td></tr>
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
