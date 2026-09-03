<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        h2 { font-size: 12px; margin-top: 18px; margin-bottom: 6px; text-transform: uppercase; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; }
        th { background-color: #f3f4f6; }
        .summary { background-color: #f9fafb; padding: 8px; margin-bottom: 12px; border: 1px solid #e5e7eb; }
        .footer { margin-top: 16px; font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p style="font-size: 10px; color: #6b7280;">SIGAE-UTS — generado el {{ now()->format('d/m/Y H:i') }}</p>

    @if ($summary ?? null)
        <div class="summary">
            {{ $summary['label'] }}: <strong>{{ $summary['value'] }}</strong> ({{ $summary['detail'] }})
        </div>
    @endif

    @foreach ($sections as $section)
        <h2>{{ $section['title'] }}</h2>
        <table>
            <thead>
                <tr>
                    @foreach ($section['headings'] as $heading)
                        <th>{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($section['rows'] as $row)
                    <tr>
                        @foreach ($row as $value)
                            <td>{{ $value }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($section['headings']) }}">Sin datos.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endforeach

    <p class="footer">Las horas asignadas son solo información de dedicación; el % de avance nunca se calcula sobre ellas.</p>
</body>
</html>
