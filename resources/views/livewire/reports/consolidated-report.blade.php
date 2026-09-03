<div>
    <h1 class="mb-6 text-lg font-semibold text-gray-800">Consolidado por periodo</h1>

    <div class="mb-6 flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Periodo</label>
            <select wire:model.live="periodFilter" class="mt-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @foreach ($periods as $period)
                    <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->status->label() }})</option>
                @endforeach
            </select>
        </div>

        @if ($report)
            <div class="flex gap-2">
                <a href="{{ route('reports.consolidated.pdf', ['period' => $periodFilter]) }}" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">
                    Descargar PDF
                </a>
                <a href="{{ route('reports.consolidated.excel', ['period' => $periodFilter]) }}" class="rounded-md bg-green-700 px-4 py-2 text-sm font-medium text-white hover:bg-green-800">
                    Descargar Excel
                </a>
            </div>
        @endif
    </div>

    @if ($report)
        @include('livewire.reports._sections', ['sections' => $report['sections'], 'summary' => $report['summary'] ?? null])
    @endif
</div>
