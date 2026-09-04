@if ($summary ?? null)
    <div class="card mb-4 p-4">
        <p class="text-sm text-text-secondary">
            {{ $summary['label'] }}: <span class="font-semibold text-text-primary">{{ $summary['value'] }}</span>
            <span class="text-text-secondary">({{ $summary['detail'] }})</span>
        </p>
    </div>
@endif

@foreach ($sections as $section)
    <div class="mb-6">
        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-text-secondary">{{ $section['title'] }}</h3>
        <div class="table-shell">
            <table class="min-w-full divide-y divide-border-subtle">
                <thead>
                    <tr>
                        @foreach ($section['headings'] as $heading)
                            <th class="table-header-cell">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-subtle">
                    @forelse ($section['rows'] as $row)
                        <tr class="table-row">
                            @foreach ($row as $value)
                                <td class="table-cell">{{ $value }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($section['headings']) }}" class="table-cell text-text-secondary">Sin datos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endforeach
