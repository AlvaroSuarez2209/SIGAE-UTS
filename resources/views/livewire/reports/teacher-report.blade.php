<div>
    <h1 class="mb-6 page-title">Informe individual por docente</h1>

    <div class="mb-6 flex flex-wrap items-end gap-4">
        <div>
            <label class="field-label">Periodo</label>
            <select wire:model.live="periodFilter" class="field-input">
                @foreach ($periods as $period)
                    <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->status->label() }})</option>
                @endforeach
            </select>
        </div>

        <div class="w-64">
            <label class="field-label">Docente</label>
            <x-searchable-select
                wire-model="teacherFilter"
                :options="$teachers->map(fn ($teacher) => ['value' => $teacher->id, 'label' => $teacher->name])->all()"
                :selected="$teacherFilter"
                placeholder="Busca por nombre..."
                empty-label="Selecciona un docente"
            />
        </div>

        @if ($report)
            <div class="flex gap-2">
                <a href="{{ route('reports.teacher.pdf', ['teacher' => $teacherFilter, 'period' => $periodFilter]) }}" class="btn-secondary">
                    <x-icon name="document" class="h-4 w-4" />
                    Descargar PDF
                </a>
                <a href="{{ route('reports.teacher.excel', ['teacher' => $teacherFilter, 'period' => $periodFilter]) }}" class="btn-secondary">
                    <x-icon name="document" class="h-4 w-4" />
                    Descargar Excel
                </a>
            </div>
        @endif
    </div>

    @if ($report)
        <h2 class="mb-4 text-base font-medium text-text-primary">{{ $report['title'] }}</h2>
        @include('livewire.reports._sections', ['sections' => $report['sections'], 'summary' => $report['summary'] ?? null])
    @else
        <x-empty-state icon="document" title="Selecciona un periodo y un docente" description="El informe se genera automáticamente al elegir ambos filtros." />
    @endif
</div>
