<div>
    <h1 class="mb-6 text-2xl font-semibold text-text-primary">Informe de compromisos transversales</h1>

    <div class="mb-6 flex flex-wrap items-end gap-4">
        <div>
            <label class="field-label">Periodo</label>
            <select wire:model.live="periodFilter" class="field-input">
                @foreach ($periods as $period)
                    <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->status->label() }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="field-label">Compromiso <span class="font-normal text-text-secondary">(opcional)</span></label>
            <select wire:model.live="commitmentFilter" class="field-input">
                <option value="">Todos</option>
                @foreach ($commitments as $commitment)
                    <option value="{{ $commitment->id }}">{{ $commitment->name }}</option>
                @endforeach
            </select>
        </div>

        @if ($report)
            <div class="flex gap-2">
                <a href="{{ route('reports.cross-cutting.pdf', ['period' => $periodFilter, 'commitment' => $commitmentFilter]) }}" class="btn-secondary">
                    <x-icon name="document" class="h-4 w-4" />
                    Descargar PDF
                </a>
                <a href="{{ route('reports.cross-cutting.excel', ['period' => $periodFilter, 'commitment' => $commitmentFilter]) }}" class="btn-secondary">
                    <x-icon name="document" class="h-4 w-4" />
                    Descargar Excel
                </a>
            </div>
        @endif
    </div>

    @if ($report)
        <h2 class="mb-4 text-base font-medium text-text-primary">{{ $report['title'] }}</h2>
        @include('livewire.reports._sections', ['sections' => $report['sections'], 'summary' => $report['summary'] ?? null])
    @endif
</div>
