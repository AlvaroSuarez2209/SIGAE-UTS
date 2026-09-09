<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a2530; }
        h1 { font-size: 16px; margin-bottom: 4px; color: #123252; }
        h2 { font-size: 12px; margin-top: 18px; margin-bottom: 6px; text-transform: uppercase; color: #5b6b7a; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #d8dee4; padding: 4px 6px; text-align: left; }
        th { background-color: #eaf1f8; color: #123252; }
        .summary { background-color: #f4f6f8; padding: 8px; margin-bottom: 12px; border: 1px solid #d8dee4; }
        .footer { margin-top: 16px; font-size: 9px; color: #5b6b7a; border-top: 1px solid #d8dee4; padding-top: 6px; }
        .brand-rule { border: none; border-top: 3px solid #1d4e89; margin: 0 0 10px; }
    </style>
</head>
<body>
    <hr class="brand-rule">
    <h1>{{ $title }}</h1>
    <p style="font-size: 10px; color: #5b6b7a;">SIGAE-UTS — Sistema de Gestión de Actividades y Evidencias Docentes — generado el {{ now()->toReadable() }}</p>

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
