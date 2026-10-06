<div>
    <h1 class="mb-6 page-title">Consolidado por periodo</h1>

    <div class="mb-6 flex flex-wrap items-end gap-4">
        <div>
            <label class="field-label">Periodo</label>
            <select wire:model.live="periodFilter" wire:loading.attr="disabled" class="field-input">
                @foreach ($periods as $period)
                    <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->status->label() }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="field-label">Programa <span class="font-normal text-text-secondary">(opcional)</span></label>
            <select wire:model.live="programFilter" wire:loading.attr="disabled" class="field-input">
                <option value="">Todos</option>
                @foreach ($programUnits as $programUnit)
                    <option value="{{ $programUnit->id }}">{{ $programUnit->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="w-56">
            <label class="field-label">Docente <span class="font-normal text-text-secondary">(opcional)</span></label>
            <x-searchable-select
                wire-model="teacherFilter"
                :options="$teachers->map(fn ($teacher) => ['value' => $teacher->id, 'label' => $teacher->name])->all()"
                :selected="$teacherFilter"
                placeholder="Busca por nombre..."
                empty-label="Todos"
            />
        </div>

        <div>
            <label class="field-label">Actividad <span class="font-normal text-text-secondary">(opcional)</span></label>
            <select wire:model.live="activityFilter" wire:loading.attr="disabled" class="field-input">
                <option value="">Todas</option>
                @foreach ($activities as $activity)
                    <option value="{{ $activity->id }}">{{ $activity->component->name }} — {{ $activity->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="field-label">Líder <span class="font-normal text-text-secondary">(opcional)</span></label>
            <select wire:model.live="leaderFilter" wire:loading.attr="disabled" class="field-input">
                <option value="">Todos</option>
                @foreach ($leaders as $leader)
                    <option value="{{ $leader->id }}">{{ $leader->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="field-label">Estado <span class="font-normal text-text-secondary">(opcional)</span></label>
            <select wire:model.live="statusFilter" wire:loading.attr="disabled" class="field-input">
                <option value="">Todos</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>

        <x-loading-indicator />

        @if ($report)
            @php
                $downloadParams = [
                    'period' => $periodFilter,
                    'program' => $programFilter ?: null,
                    'teacher' => $teacherFilter ?: null,
                    'activity' => $activityFilter ?: null,
                    'leader' => $leaderFilter ?: null,
                    'status' => $statusFilter ?: null,
                ];
            @endphp
            <div class="flex gap-2">
                <a href="{{ route('reports.consolidated.pdf', $downloadParams) }}" class="btn-secondary">
                    <x-icon name="document" class="h-4 w-4" />
                    Descargar PDF
                </a>
                <a href="{{ route('reports.consolidated.excel', $downloadParams) }}" class="btn-secondary">
                    <x-icon name="document" class="h-4 w-4" />
                    Descargar Excel
                </a>
            </div>
        @endif
    </div>

    @if ($report)
        @include('livewire.reports._sections', ['sections' => $report['sections'], 'summary' => $report['summary'] ?? null])
    @else
        <x-empty-state icon="document" title="No hay periodos académicos registrados" description="Crea un periodo académico para poder generar este informe." />
    @endif
</div>
