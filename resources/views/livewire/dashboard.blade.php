<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-lg font-semibold text-gray-800">Bienvenido, {{ auth()->user()->name }}</h1>
            <p class="text-sm text-gray-500">
                Roles: {{ auth()->user()->roles->pluck('label')->join(', ') ?: 'Sin roles asignados' }}
            </p>
        </div>

        <select wire:model.live="periodFilter" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach ($periods as $period)
                <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->status->label() }})</option>
            @endforeach
        </select>
    </div>

    @if ($teacherPanel)
        <section>
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Panel docente</h2>

            <div class="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                @foreach (\App\Enums\EvidenceStatus::cases() as $status)
                    <div class="rounded-lg bg-white p-4 text-center shadow">
                        <p class="text-2xl font-semibold text-gray-800">{{ $teacherPanel['counts'][$status->value] }}</p>
                        <p class="text-xs text-gray-500">{{ $status->label() }}</p>
                    </div>
                @endforeach
            </div>

            @if ($teacherPanel['compliance']['percentage'] !== null)
                <div class="mb-4 rounded-lg bg-white p-4 shadow">
                    <p class="text-sm text-gray-600">
                        Avance sobre entregables obligatorios:
                        <span class="font-semibold text-gray-800">{{ $teacherPanel['compliance']['percentage'] }}%</span>
                        ({{ $teacherPanel['compliance']['approved'] }} de {{ $teacherPanel['compliance']['total'] }})
                    </p>
                </div>
            @endif

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-4 py-2 text-sm font-medium text-gray-700">Próximos vencimientos (14 días)</div>
                <table class="min-w-full divide-y divide-gray-200">
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($teacherPanel['upcoming'] as $evidence)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-800">{{ $evidence->deliverable->name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-500">{{ $evidence->deliverable->due_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-2 text-right text-sm">
                                    <a href="{{ route('my-deliverables.show', $evidence) }}" class="text-indigo-600 hover:underline">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-3 text-sm text-gray-400">No tienes vencimientos próximos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($leaderPanel)
        <section>
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Panel líder</h2>

            <div class="mb-4 rounded-lg bg-white p-4 shadow">
                <p class="text-sm text-gray-600">
                    <span class="font-semibold text-gray-800">{{ $leaderPanel['pendingReviewCount'] }}</span>
                    evidencia(s) pendiente(s) de revisión en tu ámbito.
                    <a href="{{ route('reviews.index') }}" class="text-indigo-600 hover:underline">Ir a la bandeja</a>
                </p>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-4 py-2 text-sm font-medium text-gray-700">Cumplimiento por actividad</div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Docente</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Actividad</th>
                            <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Avance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($leaderPanel['rows'] as $row)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-800">{{ $row['teacher']->name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ $row['activity']->component->name }} — {{ $row['activity']->name }}</td>
                                <td class="px-4 py-2 text-right text-sm text-gray-600">
                                    @if ($row['compliance']['percentage'] !== null)
                                        {{ $row['compliance']['percentage'] }}% ({{ $row['compliance']['approved'] }}/{{ $row['compliance']['total'] }})
                                    @else
                                        <span class="text-gray-400">Sin entregables obligatorios</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-3 text-sm text-gray-400">No hay docentes en tu ámbito para este periodo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($coordinationPanel)
        <section>
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Panel de coordinación</h2>

            <div class="mb-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                @foreach (\App\Enums\EvidenceStatus::cases() as $status)
                    <div class="rounded-lg bg-white p-4 text-center shadow">
                        <p class="text-2xl font-semibold text-gray-800">{{ $coordinationPanel['counts'][$status->value] }}</p>
                        <p class="text-xs text-gray-500">{{ $status->label() }}</p>
                    </div>
                @endforeach
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-4 py-2 text-sm font-medium text-gray-700">Consolidado por docente</div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Docente</th>
                            <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Avance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($coordinationPanel['teacherRows'] as $row)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-800">{{ $row['teacher']->name }}</td>
                                <td class="px-4 py-2 text-right text-sm text-gray-600">
                                    {{ $row['compliance']['percentage'] }}% ({{ $row['compliance']['approved'] }}/{{ $row['compliance']['total'] }})
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="px-4 py-3 text-sm text-gray-400">No hay docentes con entregables en este periodo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @unless ($teacherPanel || $leaderPanel || $coordinationPanel)
        <div class="rounded-lg bg-white p-6 text-sm text-gray-500 shadow">
            No hay información de seguimiento para mostrar en este periodo.
        </div>
    @endunless
</div>
