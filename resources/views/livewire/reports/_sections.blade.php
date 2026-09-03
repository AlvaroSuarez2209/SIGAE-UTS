@if ($summary ?? null)
    <div class="mb-4 rounded-lg bg-white p-4 shadow">
        <p class="text-sm text-gray-600">
            {{ $summary['label'] }}: <span class="font-semibold text-gray-800">{{ $summary['value'] }}</span>
            <span class="text-gray-400">({{ $summary['detail'] }})</span>
        </p>
    </div>
@endif

@foreach ($sections as $section)
    <div class="mb-6">
        <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">{{ $section['title'] }}</h3>
        <div class="overflow-x-auto rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        @foreach ($section['headings'] as $heading)
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($section['rows'] as $row)
                        <tr>
                            @foreach ($row as $value)
                                <td class="px-4 py-2 text-sm text-gray-700">{{ $value }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($section['headings']) }}" class="px-4 py-3 text-sm text-gray-400">Sin datos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endforeach
