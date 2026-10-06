<div>
    <h1 class="mb-6 page-title">Informe de compromisos transversales</h1>

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
            <label class="field-label">Compromiso <span class="font-normal text-text-secondary">(opcional)</span></label>
            <select wire:model.live="commitmentFilter" wire:loading.attr="disabled" class="field-input">
                <option value="">Todos</option>
                @foreach ($commitments as $commitment)
                    <option value="{{ $commitment->id }}">{{ $commitment->name }}</option>
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
            <div class="flex gap-2">
                <a href="{{ route('reports.cross-cutting.pdf', ['period' => $periodFilter, 'commitment' => $commitmentFilter, 'status' => $statusFilter ?: null]) }}" class="btn-secondary">
                    <x-icon name="document" class="h-4 w-4" />
                    Descargar PDF
                </a>
                <a href="{{ route('reports.cross-cutting.excel', ['period' => $periodFilter, 'commitment' => $commitmentFilter, 'status' => $statusFilter ?: null]) }}" class="btn-secondary">
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
        <x-empty-state icon="document" title="No hay periodos académicos registrados" description="Crea un periodo académico para poder generar este informe." />
    @endif
</div>
