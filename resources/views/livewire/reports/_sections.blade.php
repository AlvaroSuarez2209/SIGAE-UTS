@if ($summary ?? null)
    @php
        $summaryPercent = is_numeric(rtrim($summary['value'], '%')) ? min(100, max(0, (float) rtrim($summary['value'], '%'))) : null;
    @endphp
    <div class="card mb-6 p-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-text-secondary">{{ $summary['label'] }}</p>
        <div class="mt-2 flex flex-wrap items-center gap-4">
            <span class="text-4xl font-bold text-brand-primary">{{ $summary['value'] }}</span>
            @if ($summaryPercent !== null)
                <x-progress-bar :percentage="$summaryPercent" class="h-2.5 w-full max-w-xs" />
            @endif
        </div>
        <p class="mt-2 text-sm text-text-secondary">{{ $summary['detail'] }}</p>
    </div>
@endif

@foreach ($sections as $section)
    @php
        // Algunas secciones traen una aclaración entre paréntesis pegada
        // al título (p. ej. "Distribución (horas informativas, no
        // determinan entregables)") — separarla del rótulo principal
        // evita meter una frase larga dentro del mismo tratamiento
        // mayúsculas/tracking-wide que en el resto de la app (sidebar,
        // "Mis entregables") se usa solo para nombres cortos.
        $sectionLabel = $section['title'];
        $sectionNote = null;

        if (preg_match('/^(.*)\s\((.+)\)$/', $section['title'], $titleMatch)) {
            $sectionLabel = $titleMatch[1];
            $sectionNote = $titleMatch[2];
        }

        $statusIndex = \App\Services\Reports\ReportTheme::statusColumnIndex($section['headings']);
    @endphp
    <div class="mb-6">
        <h3 class="mb-2 flex flex-wrap items-baseline gap-x-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-text-secondary">{{ $sectionLabel }}</span>
            @if ($sectionNote)
                <span class="text-xs text-text-secondary">({{ $sectionNote }})</span>
            @endif
        </h3>
        <div class="table-shell">
            <table class="min-w-full divide-y divide-border-subtle">
                <thead>
                    <tr>
                        @foreach ($section['headings'] as $heading)
                            <th class="table-header-cell{{ \App\Services\Reports\ReportTheme::isNumericHeading($heading) ? ' text-right' : '' }}">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-subtle">
                    @forelse ($section['rows'] as $row)
                        <tr class="table-row">
                            @foreach (array_values($row) as $i => $value)
                                @php $numeric = \App\Services\Reports\ReportTheme::isNumericHeading($section['headings'][$i] ?? ''); @endphp
                                <td class="table-cell{{ $numeric ? ' text-right' : '' }}">
                                    @if ($i === $statusIndex && ($status = \App\Enums\EvidenceStatus::fromLabel((string) $value)))
                                        <x-status-badge :status="$status" />
                                    @else
                                        {{ $value }}
                                    @endif
                                </td>
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
