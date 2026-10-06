<div class="space-y-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="page-title">Bienvenido, <span class="text-brand-primary">{{ auth()->user()->name }}</span></h1>
            <p class="text-sm text-text-secondary">
                Roles: {{ auth()->user()->roles->pluck('label')->join(', ') ?: 'Sin roles asignados' }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <select wire:model.live="periodFilter" wire:loading.attr="disabled" class="field-input mt-0 w-auto">
                @foreach ($periods as $period)
                    <option value="{{ $period->id }}">{{ $period->name }}</option>
                @endforeach
            </select>
            @php $selectedPeriod = $periods->firstWhere('id', $periodFilter); @endphp
            @if ($selectedPeriod)
                <x-status-badge :status="$selectedPeriod->status" />
            @endif
            <x-loading-indicator />
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
                        :url="route('my-deliverables.index', ['status' => $status->value])"
                    />
                @endforeach
            </div>

            @php
                // Mismo tono por estado que las tarjetas KPI de arriba
                // (EvidenceStatus::color()), traducido a un color CSS real:
                // un atributo SVG stroke/fill no puede resolver una clase
                // de utilidad de Tailwind, solo un valor de color.
                $statusToneColors = [
                    'neutral' => 'var(--color-text-secondary)',
                    'secondary' => 'var(--color-secondary)',
                    'primary' => 'var(--color-brand-primary)',
                    'warning' => 'var(--color-status-warning)',
                    'success' => 'var(--color-status-success)',
                    'error' => 'var(--color-status-error)',
                    'accent' => 'var(--color-accent)',
                ];
                $teacherDonutSegments = collect(\App\Enums\EvidenceStatus::cases())->map(fn ($status) => [
                    'label' => $status->label(),
                    'value' => $teacherPanel['counts'][$status->value],
                    'color' => $statusToneColors[$status->color()],
                    'url' => route('my-deliverables.index', ['status' => $status->value]),
                ])->all();
            @endphp

            <div class="card mb-6 p-5">
                <p class="mb-3 text-sm font-semibold text-text-primary">Distribución de evidencias por estado</p>
                <x-donut-chart :segments="$teacherDonutSegments" empty-label="Todavía no tienes evidencias en este periodo." />
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
                <div class="border-b border-border-subtle px-5 py-4 text-lg font-semibold text-text-primary">Próximos vencimientos</div>
                <table class="min-w-full divide-y divide-border-subtle">
                    <tbody class="divide-y divide-border-subtle">
                        @forelse ($teacherPanel['upcoming'] as $evidence)
                            @php $due = $this->dueLabel($evidence->deliverable->due_at); @endphp
                            <tr class="table-row">
                                <td class="table-cell">{{ $evidence->deliverable->name }}</td>
                                <td class="table-cell text-text-secondary">{{ $evidence->deliverable->due_at->toReadable() }}</td>
                                <td class="table-cell">
                                    <span @class([
                                        'badge',
                                        'bg-status-warning-subtle text-status-warning' => $due['warning'],
                                        'bg-surface-muted text-text-secondary' => ! $due['warning'],
                                    ])>
                                        <x-icon name="clock" class="h-3.5 w-3.5" />
                                        {{ $due['label'] }}
                                    </span>
                                </td>
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

            <div class="mb-6 grid grid-cols-2 gap-5 sm:grid-cols-4">
                <x-kpi-card :value="$leaderPanel['teacherCount']" label="Docentes en tu ámbito" color="primary" icon="users" />
                <x-kpi-card :value="$leaderPanel['pendingReviewCount']" label="Pendientes de revisión" color="warning" icon="clock" />
                <x-kpi-card
                    :value="$leaderPanel['averageCompliance'] !== null ? $leaderPanel['averageCompliance'].'%' : '—'"
                    label="% cumplimiento promedio"
                    :color="$leaderPanel['averageCompliance'] !== null ? 'success' : 'neutral'"
                    icon="check-circle"
                />
                {{-- Siempre visible, también en 0 — para que el Líder sepa
                     que esta pantalla existe aunque hoy no tenga ninguna
                     evidencia exenta en su ámbito. --}}
                <x-kpi-card
                    :value="$leaderPanel['exemptCount']"
                    label="Exentas"
                    color="accent"
                    icon="shield-check"
                    :url="route('reviews.exempt', ['period' => $periodFilter])"
                />
            </div>

            <div class="card mb-6 p-5">
                <p class="text-sm text-text-secondary">
                    <a href="{{ route('reviews.index') }}" class="btn-text">Ir a la bandeja de revisión</a>
                </p>
            </div>

            {{-- Sin enlace: a diferencia del panel de Coordinación,
                 /reports/* no admite el rol Líder (role:administrator,
                 coordination,auditor) — enlazar aquí a reports.activity
                 era un 403 esperando a que alguien hiciera clic.
                 <x-bar-chart> ya muestra la fila sin vínculo cuando no
                 recibe 'url'. --}}
            @php $leaderActivityBars = $leaderPanel['activityCompliance']->all(); @endphp
            <div class="card mb-6 p-5">
                <p class="mb-3 text-sm font-semibold text-text-primary">% de cumplimiento por actividad</p>
                <x-bar-chart :bars="$leaderActivityBars" empty-label="No hay actividades con entregables obligatorios en tu ámbito todavía." />
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

            <div class="mb-6 flex flex-wrap items-end gap-3">
                <div>
                    <label class="field-label">Programa</label>
                    <select wire:model.live="programFilter" wire:loading.attr="disabled" class="field-input mt-0 w-auto">
                        <option value="">Todos</option>
                        @foreach ($programUnits as $programUnit)
                            <option value="{{ $programUnit->id }}">{{ $programUnit->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Docente</label>
                    <select wire:model.live="teacherFilter" wire:loading.attr="disabled" class="field-input mt-0 w-auto">
                        <option value="">Todos</option>
                        @foreach ($filterableTeachers as $filterableTeacher)
                            <option value="{{ $filterableTeacher->id }}">{{ $filterableTeacher->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Actividad</label>
                    <select wire:model.live="activityFilter" wire:loading.attr="disabled" class="field-input mt-0 w-auto">
                        <option value="">Todas</option>
                        @foreach ($filterableActivities as $filterableActivity)
                            <option value="{{ $filterableActivity->id }}">{{ $filterableActivity->component->name }} — {{ $filterableActivity->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Líder</label>
                    <select wire:model.live="leaderFilter" wire:loading.attr="disabled" class="field-input mt-0 w-auto">
                        <option value="">Todos</option>
                        @foreach ($filterableLeaders as $filterableLeader)
                            <option value="{{ $filterableLeader->id }}">{{ $filterableLeader->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Estado</label>
                    <select wire:model.live="evidenceStatusFilter" wire:loading.attr="disabled" class="field-input mt-0 w-auto">
                        <option value="">Todos</option>
                        @foreach ($evidenceStatusOptions as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <x-loading-indicator />
            </div>

            <div class="mb-6 grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-8">
                <x-kpi-card
                    :value="$coordinationPanel['globalCompliance'] !== null ? $coordinationPanel['globalCompliance'].'%' : '—'"
                    label="% cumplimiento global"
                    :color="$coordinationPanel['globalCompliance'] !== null ? 'success' : 'neutral'"
                    icon="check-circle"
                    :url="route('reports.consolidated', ['period' => $periodFilter])"
                />
                @foreach (\App\Enums\EvidenceStatus::cases() as $status)
                    <x-kpi-card
                        :value="$coordinationPanel['counts'][$status->value]"
                        :label="$status->label()"
                        :color="$status->color()"
                        :icon="$status->icon()"
                        :url="route('reports.consolidated', ['period' => $periodFilter, 'status' => $status->value])"
                    />
                @endforeach
            </div>

            @php
                $programBars = $coordinationPanel['programCompliance']->map(fn ($bar) => [
                    ...$bar,
                    'url' => route('reports.consolidated', ['period' => $periodFilter, 'program' => $bar['programUnitId']]),
                ])->all();
            @endphp
            <div class="card mb-6 p-5">
                <p class="mb-3 text-sm font-semibold text-text-primary">% de cumplimiento por programa</p>
                <x-bar-chart :bars="$programBars" empty-label="No hay docentes con entregables obligatorios en este periodo todavía." />
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
                                <td class="table-cell">
                                    <a href="{{ route('reports.teacher', ['period' => $periodFilter, 'teacher' => $row['teacher']->id]) }}" class="text-brand-primary hover:underline">
                                        {{ $row['teacher']->name }}
                                    </a>
                                </td>
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
                <div class="border-b border-border-subtle px-5 py-4 text-lg font-semibold text-text-primary">Próximos vencimientos</div>
                <table class="min-w-full divide-y divide-border-subtle">
                    <thead>
                        <tr>
                            <th class="table-header-cell">Entregable</th>
                            <th class="table-header-cell">Ámbito</th>
                            <th class="table-header-cell">Fecha límite</th>
                            <th class="table-header-cell">Vence</th>
                            <th class="table-header-cell text-right">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @forelse ($coordinationPanel['upcoming'] as $row)
                            @php
                                $deliverable = $row['deliverable'];
                                $due = $this->dueLabel($deliverable->due_at);
                            @endphp
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
                                <td class="table-cell">
                                    <span @class([
                                        'badge',
                                        'bg-status-warning-subtle text-status-warning' => $due['warning'],
                                        'bg-surface-muted text-text-secondary' => ! $due['warning'],
                                    ])>
                                        <x-icon name="clock" class="h-3.5 w-3.5" />
                                        {{ $due['label'] }}
                                    </span>
                                </td>
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
                                <td colspan="5">
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
