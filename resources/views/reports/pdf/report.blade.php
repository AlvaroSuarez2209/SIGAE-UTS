<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        /*
         * Helvetica en vez de la DejaVu Sans por defecto de DomPDF: Inter
         * (la tipografía real de la app) no se puede embeber sin sus
         * archivos .ttf locales, que este proyecto no distribuye (se carga
         * vía Google Fonts en la web, no como archivo). Helvetica es una
         * de las 14 fuentes base del estándar PDF —no requiere embeber
         * nada— y su trazo humanista sin gracias es visualmente más
         * cercano a Inter que DejaVu Sans. Ver docs/manual-diseno.md.
         */
        @page {
            margin: 125px 30px 55px 30px;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10.5px;
            color: #1a2530;
        }

        /* --- Encabezado y pie repetidos en cada página (posición fija) --- */
        header {
            position: fixed;
            top: -110px;
            left: 0;
            right: 0;
            height: 105px;
        }

        .letterhead-bar {
            background-color: #00447e;
            height: 5px;
            width: 100%;
        }

        .letterhead-layout {
            width: 100%;
            border: none;
            margin-top: 10px;
        }

        .letterhead-layout td {
            border: none;
            padding: 0;
        }

        .letterhead-logo-cell {
            width: 40px;
            vertical-align: middle;
        }

        .letterhead-text-cell {
            vertical-align: middle;
            padding-left: 10px;
        }

        .letterhead-brand {
            font-size: 14px;
            font-weight: bold;
            color: #00447e;
        }

        .letterhead-tagline {
            font-size: 8px;
            color: #5b6b7a;
            margin-top: 1px;
        }

        footer {
            position: fixed;
            bottom: -45px;
            left: 0;
            right: 0;
            height: 35px;
            border-top: 1px solid #d8dee4;
            padding-top: 6px;
            font-size: 8.5px;
            color: #5b6b7a;
        }

        /*
         * El número de página ("Página X de Y") NO se dibuja aquí con CSS:
         * DomPDF no implementa un contador "pages" especial ligado al
         * total de páginas —su motor de "content: counter(...)" es
         * genérico y solo conoce contadores que la propia hoja de estilos
         * declara con counter-reset/counter-increment—, así que
         * "counter(pages)" siempre resuelve a 0 (comprobado en
         * inspección visual). Se dibuja en su lugar con la API nativa de
         * canvas (Canvas::page_script), ver ReportTheme::stampPageNumbers(),
         * llamada desde ReportExportController — no depende de
         * `enable_php` (deshabilitado en este proyecto), porque no es
         * PHP embebido en el documento sino una llamada directa a dompdf.
         */

        /* --- Título del informe --- */
        h1 {
            font-size: 15px;
            font-weight: bold;
            margin: 0 0 2px;
            color: #1a2530;
        }

        .report-meta {
            font-size: 8.5px;
            color: #5b6b7a;
            margin: 0 0 12px;
        }

        /* --- Resumen de % de avance --- */
        .summary-box {
            margin-bottom: 14px;
            padding: 10px 14px;
            border: 1px solid #d8dee4;
            border-radius: 4px;
            background-color: #ebf0f5;
        }

        .summary-label {
            font-size: 8.5px;
            color: #5b6b7a;
            margin-bottom: 5px;
        }

        .summary-layout {
            width: 100%;
            border: none;
        }

        .summary-layout td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        .summary-value-cell {
            width: 64px;
            font-size: 22px;
            font-weight: bold;
            color: #00447e;
        }

        .summary-bar-track {
            background-color: #d8dee4;
            height: 8px;
            border-radius: 4px;
        }

        .summary-bar-fill {
            background-color: #00447e;
            height: 8px;
            border-radius: 4px;
        }

        .summary-detail {
            font-size: 8.5px;
            color: #5b6b7a;
            margin-top: 6px;
        }

        /* --- Subtítulo de sección --- */
        h2 {
            font-size: 10px;
            font-weight: bold;
            color: #ffffff;
            background-color: #4b5a6a;
            margin: 16px 0 6px;
            padding: 5px 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* --- Tabla de datos --- */
        table.report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        table.report-table th,
        table.report-table td {
            border: 1px solid #d8dee4;
            padding: 5px 7px;
            text-align: left;
        }

        table.report-table th {
            background-color: #00447e;
            color: #ffffff;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        table.report-table tbody tr:nth-child(even) {
            background-color: #f4f6f8;
        }

        table.report-table .numeric {
            text-align: right;
        }

        table.report-table .empty-row {
            color: #5b6b7a;
            font-style: italic;
            text-align: left;
        }

        .status-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 9px;
        }

        .note {
            font-size: 8.5px;
            color: #5b6b7a;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <header>
        <div class="letterhead-bar"></div>
        <table class="letterhead-layout">
            <tr>
                @if ($logo = \App\Services\Reports\ReportTheme::logoPath())
                    <td class="letterhead-logo-cell">
                        <img src="{{ $logo }}" style="width: 32px; height: 32px;">
                    </td>
                @endif
                <td class="letterhead-text-cell">
                    <div class="letterhead-brand">SIGAE-UTS</div>
                    <div class="letterhead-tagline">Sistema de Información para la Gestión de Actividades y Evidencias Docentes</div>
                </td>
            </tr>
        </table>
    </header>

    <footer>
        SIGAE-UTS · Institución Universitaria Tecnológica de Santander
    </footer>

    <h1>{{ $title }}</h1>
    <p class="report-meta">Generado el {{ now()->toReadable() }}</p>

    @if ($summary ?? null)
        @php
            $summaryNumeric = is_numeric(rtrim($summary['value'], '%'));
            $summaryPercent = $summaryNumeric ? min(100, max(0, (float) rtrim($summary['value'], '%'))) : 0;
        @endphp
        <div class="summary-box">
            <div class="summary-label">{{ $summary['label'] }}</div>
            <table class="summary-layout">
                <tr>
                    <td class="summary-value-cell">{{ $summary['value'] }}</td>
                    <td>
                        <div class="summary-bar-track">
                            @if ($summaryNumeric)
                                <div class="summary-bar-fill" style="width: {{ $summaryPercent }}%;"></div>
                            @endif
                        </div>
                    </td>
                </tr>
            </table>
            <div class="summary-detail">{{ $summary['detail'] }}</div>
        </div>
    @endif

    @foreach ($sections as $section)
        @php $statusIndex = \App\Services\Reports\ReportTheme::statusColumnIndex($section['headings']); @endphp
        <h2>{{ $section['title'] }}</h2>
        <table class="report-table">
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
                        @foreach (array_values($row) as $i => $value)
                            @php $numeric = \App\Services\Reports\ReportTheme::isNumericColumnValue($value); @endphp
                            <td class="{{ $numeric ? 'numeric' : '' }}">
                                @if ($i === $statusIndex && ($tone = \App\Services\Reports\ReportTheme::statusTone((string) $value)))
                                    <span class="status-pill" style="background-color: {{ $tone['bg'] }}; color: {{ $tone['text'] }};">{{ $value }}</span>
                                @else
                                    {{ $value }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td class="empty-row" colspan="{{ count($section['headings']) }}">Sin datos.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endforeach

    <p class="note">Las horas asignadas son solo información de dedicación; el % de avance nunca se calcula sobre ellas.</p>
</body>
</html>
