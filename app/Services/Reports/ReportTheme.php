<?php

namespace App\Services\Reports;

use App\Models\InstitutionSettings;
use Barryvdh\DomPDF\PDF;
use Dompdf\Css\Color;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Único punto de identidad visual para los informes exportables (PDF y
 * Excel) — los mismos tokens de marca que ya existen en
 * resources/css/app.css (@theme), copiados aquí porque ni DomPDF ni
 * PhpSpreadsheet pueden leer variables CSS de Tailwind. Si la paleta de
 * la app cambia, hay que actualizar también esta clase — ver la sección
 * de paleta en docs/manual-diseno.md.
 *
 * Usada por resources/views/reports/pdf/report.blade.php (PDF),
 * app/Exports/ReportSectionSheet.php (Excel) y, para la detección
 * genérica de columnas (statusColumnIndex/isNumericColumnValue),
 * también por resources/views/livewire/reports/_sections.blade.php (la
 * vista en pantalla) — así los cuatro informes (individual, por
 * actividad, transversales, consolidado) comparten un único lugar de
 * formato en los tres canales, en vez de repetirlo cada uno.
 */
class ReportTheme
{
    public const BRAND_PRIMARY = '#00447e';

    public const BRAND_PRIMARY_DARK = '#002a4e';

    public const BRAND_PRIMARY_SUBTLE = '#ebf0f5';

    public const BRAND_SECONDARY = '#0a7a45';

    public const SECONDARY = '#4b5a6a';

    public const TEXT_PRIMARY = '#1a2530';

    public const TEXT_SECONDARY = '#5b6b7a';

    public const SURFACE = '#ffffff';

    public const SURFACE_MUTED = '#f4f6f8';

    public const BORDER_SUBTLE = '#d8dee4';

    /**
     * Mismo mapeo tono -> color que <x-status-badge>
     * (resources/views/components/status-badge.blade.php), pero
     * indexado por la etiqueta en español en vez del enum: ReportBuilder
     * ya entrega el "Estado" de cada fila como texto plano, y así se
     * evita tener que rehacer la estructura de $rows para transportar el
     * enum solo para esto (ver "Qué NO cambiar" del encargo original).
     */
    private const STATUS_TONES = [
        'Pendiente' => ['bg' => '#f4f6f8', 'text' => '#5b6b7a'],
        'Borrador' => ['bg' => '#f4f6f8', 'text' => '#4b5a6a'],
        'Enviado' => ['bg' => '#ebf0f5', 'text' => '#002a4e'],
        'Requiere ajustes' => ['bg' => '#fbf1e4', 'text' => '#9a5b12'],
        'Aprobado' => ['bg' => '#e8f5ee', 'text' => '#1e7a4c'],
        'Vencido' => ['bg' => '#fbeceb', 'text' => '#b3261e'],
        'Exento' => ['bg' => '#f6efdf', 'text' => '#8a6516'],
    ];

    /**
     * @return array{bg: string, text: string}|null
     */
    public static function statusTone(string $label): ?array
    {
        return self::STATUS_TONES[$label] ?? null;
    }

    /**
     * Índice (0-based) de la columna "Estado" dentro de $headings, o null
     * si esa sección no tiene una — no todas las secciones de los 4
     * informes muestran estado por fila.
     */
    public static function statusColumnIndex(array $headings): ?int
    {
        $index = array_search('Estado', $headings, true);

        return $index === false ? null : $index;
    }

    /**
     * Encabezados de columna que ReportBuilder ya entrega con valores
     * numéricos (conteos, horas, porcentajes) en cualquiera de los 4
     * informes — conjunto cerrado, igual que 'Estado' arriba, porque
     * ReportBuilder solo produce estas etiquetas exactas hoy. Un informe
     * futuro con una columna numérica nueva se suma aquí una sola vez.
     */
    private const NUMERIC_HEADINGS = [
        'Horas asignadas',
        'Obligatorios',
        'Aprobados',
        '% Avance',
        'Destinatarios',
        'Enviados',
        'Cantidad',
    ];

    /**
     * Determina la alineación de una columna completa (encabezado y cada
     * celda) por el nombre de su encabezado — la misma regla ya aplicada
     * a mano en Distribución docente ("Horas") y Entregables
     * ("Destinatarios"), unificada aquí para que la apliquen por igual
     * los cuatro informes (individual, por actividad, transversales,
     * consolidado) sin repetirla en cada vista.
     */
    public static function isNumericHeading(string $heading): bool
    {
        return in_array($heading, self::NUMERIC_HEADINGS, true);
    }

    /**
     * Heurística genérica (no atada a nombres de columna concretos) para
     * decidir si un valor de celda debe alinearse a la derecha — cubre
     * enteros, decimales y porcentajes ("75%") ya formateados como texto
     * por ReportBuilder, y sigue funcionando si un informe futuro agrega
     * una columna numérica nueva sin tocar esta clase.
     */
    public static function isNumericColumnValue(mixed $value): bool
    {
        if (is_int($value) || is_float($value)) {
            return true;
        }

        return is_string($value) && $value !== '' && is_numeric(rtrim($value, '%'));
    }

    /**
     * Isotipo pequeño ya usado en el sidebar — null si todavía no se ha
     * colocado (entornos nuevos sin el logo real), para que la plantilla
     * PDF no rompa por un <img> con ruta inexistente. El logo configurado
     * en Administración > Identidad institucional (Prioridad 3) tiene
     * prioridad; se usa su ruta real en disco (no una URL: dompdf incrusta
     * por archivo, no por HTTP). Nota: si el logo subido es .svg, el
     * soporte de dompdf para SVG es limitado — un logo PNG/JPG es más
     * seguro para esta superficie específica, aunque la validación de la
     * pantalla de administración acepta los 3 formatos por igual.
     */
    public static function logoPath(): ?string
    {
        if ($customPath = InstitutionSettings::current()->markLogoDiskPath()) {
            return $customPath;
        }

        $path = public_path('images/logo/logo-mark-icon.png');

        return file_exists($path) ? $path : null;
    }

    /**
     * Único punto de formato de una hoja de Excel: encabezado
     * institucional (nombre configurable + logo si el formato lo permite,
     * título del informe, título de la sección y resumen de % de avance si
     * aplica), colores de marca en el encabezado de columnas, autofiltro,
     * panes congelados, bordes sutiles, alineación numérica a la derecha y
     * color de "Estado" coherente con la web y el PDF. Cualquier informe
     * futuro que use ReportSectionSheet hereda este formato
     * automáticamente, sin repetir la lógica de estilo.
     *
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array{label: string, value: string, detail: string}|null  $summary
     */
    public static function styleExcelSheet(
        Worksheet $sheet,
        string $reportTitle,
        string $sectionTitle,
        array $headings,
        array $rows,
        ?array $summary,
    ): void {
        $columnCount = max(count($headings), 1);
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);

        // --- Encabezado institucional: nombre de la institución (y su
        // logo si hay uno con un formato que PhpSpreadsheet pueda
        // incrustar), título del informe, título de la sección y, si
        // aplica, el resumen de % de avance — todo antes de la tabla, para
        // que el archivo nunca empiece directo en datos crudos sin
        // contexto. Mismo criterio que el PDF (ReportTheme::logoPath()),
        // para que ambos canales de exportación muestren la misma
        // identidad configurable en Administración > Identidad
        // institucional.
        $extraRows = 4 + ($summary ? 1 : 0);
        $sheet->insertNewRowBefore(1, $extraRows);

        $logoPath = self::logoPath();
        $hasEmbeddableLogo = $logoPath && in_array(strtolower(pathinfo($logoPath, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg'], true);

        $sheet->setCellValue('A1', 'SIGAE-UTS — '.InstitutionSettings::current()->name);
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->getRowDimension(1)->setRowHeight($hasEmbeddableLogo ? 28 : 18);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::rgb(self::SECONDARY)]],
            'fill' => self::solidFill(self::SURFACE_MUTED),
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => $hasEmbeddableLogo ? 4 : 1],
        ]);

        // PhpSpreadsheet incrusta imágenes vía GD (Drawing), que no
        // entiende SVG — si el logo configurado es .svg (formato que la
        // pantalla de Identidad institucional sí acepta), se omite la
        // imagen y el encabezado de texto queda igual de completo.
        if ($hasEmbeddableLogo) {
            $drawing = new Drawing;
            $drawing->setPath($logoPath);
            $drawing->setHeight(22);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(4);
            $drawing->setOffsetY(3);
            $drawing->setWorksheet($sheet);
        }

        $sheet->setCellValue('A2', $reportTitle);
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->getRowDimension(2)->setRowHeight(24);
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => self::solidFill(self::BRAND_PRIMARY),
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);

        // Mismo formato ("Generado el ...") que ya usa el PDF
        // (resources/views/reports/pdf/report.blade.php) — antes, el Excel
        // no dejaba ningún rastro de cuándo se generó.
        $sheet->setCellValue('A3', 'Generado el '.now()->toReadable());
        $sheet->mergeCells("A3:{$lastColumn}3");
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => self::rgb(self::TEXT_SECONDARY)]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);

        $sheet->setCellValue('A4', $sectionTitle);
        $sheet->mergeCells("A4:{$lastColumn}4");
        $sheet->getStyle('A4')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::rgb(self::TEXT_SECONDARY)]],
            'fill' => self::solidFill(self::SURFACE_MUTED),
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);

        $headerRow = 4;

        if ($summary) {
            $headerRow = 5;
            $sheet->setCellValue('A5', "{$summary['label']}: {$summary['value']} ({$summary['detail']})");
            $sheet->mergeCells("A5:{$lastColumn}5");
            $sheet->getStyle('A5')->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::rgb(self::BRAND_PRIMARY_DARK)]],
                'fill' => self::solidFill(self::BRAND_PRIMARY_SUBTLE),
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            ]);
        }

        // A partir de aquí, $headerRow pasa a apuntar a la fila real de
        // encabezados de columna (la que insertNewRowBefore desplazó).
        $headerRow += 1;
        $firstDataRow = $headerRow + 1;
        $lastDataRow = $headerRow + count($rows);
        $headerRange = "A{$headerRow}:{$lastColumn}{$headerRow}";

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => self::solidFill(self::BRAND_PRIMARY),
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        if ($lastDataRow >= $firstDataRow) {
            $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}{$lastDataRow}");
        }

        $sheet->freezePane('A'.$firstDataRow);

        $tableRange = "A{$headerRow}:{$lastColumn}".max($lastDataRow, $headerRow);
        $sheet->getStyle($tableRange)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::rgb(self::BORDER_SUBTLE)]],
            ],
        ]);

        $statusColumnIndex = self::statusColumnIndex($headings);

        foreach ($rows as $rowOffset => $row) {
            $excelRow = $firstDataRow + $rowOffset;

            if ($rowOffset % 2 === 1) {
                $sheet->getStyle("A{$excelRow}:{$lastColumn}{$excelRow}")->applyFromArray([
                    'fill' => self::solidFill(self::SURFACE_MUTED),
                ]);
            }

            foreach (array_values($row) as $columnOffset => $value) {
                $columnLetter = Coordinate::stringFromColumnIndex($columnOffset + 1);
                $cell = "{$columnLetter}{$excelRow}";

                if (self::isNumericColumnValue($value)) {
                    $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                if ($statusColumnIndex !== null && $columnOffset === $statusColumnIndex) {
                    $tone = self::statusTone((string) $value);

                    if ($tone) {
                        $sheet->getStyle($cell)->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => self::rgb($tone['text'])]],
                            'fill' => self::solidFill($tone['bg']),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Numera cada página del PDF ("Página X de Y") con la API nativa de
     * canvas de DomPDF (Canvas::page_script), no con CSS: el motor de
     * "content: counter(...)" de DomPDF es genérico y solo conoce
     * contadores que la propia hoja de estilos declara con
     * counter-reset/counter-increment, así que "counter(pages)" (el
     * total de páginas) siempre resuelve a 0 —comprobado por inspección
     * visual del PDF generado. La alternativa clásica de DomPDF
     * (`<script type="text/php">` con $PAGE_COUNT) tampoco aplica: esta
     * app tiene `enable_php` deshabilitado a propósito (ver
     * vendor/barryvdh/laravel-dompdf/config/dompdf.php) y no se activa
     * solo para esto. `page_script` no depende de esa opción: es una
     * llamada directa a la API del canvas, no PHP embebido en el
     * documento HTML.
     *
     * Debe llamarse DESPUÉS de renderizar la vista y ANTES de pedir el
     * output/descarga, porque `page_script` recorre de inmediato las
     * páginas ya generadas en el momento en que se invoca.
     */
    public static function stampPageNumbers(PDF $pdf): void
    {
        $pdf->render();

        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('Helvetica');
        $size = 6.5; // ≈ 8.5px CSS del resto del footer, convertido a puntos (×0.75)
        $color = Color::parse(self::TEXT_SECONDARY);

        // @page { margin: ... 30px; } y el <footer> del template: mismo
        // margen derecho, y misma banda inferior donde vive el texto
        // institucional del pie.
        $rightMarginPt = 30 * 0.75;
        $textTopPt = 29.25;

        $canvas->page_script(function (int $pageNumber, int $pageCount, $canvasArg, $fontMetrics) use ($font, $size, $color, $rightMarginPt, $textTopPt): void {
            $text = "Página {$pageNumber} de {$pageCount}";
            $width = $fontMetrics->getTextWidth($text, $font, $size);
            $x = $canvasArg->get_width() - $rightMarginPt - $width;
            $y = $canvasArg->get_height() - $textTopPt;
            $canvasArg->text($x, $y, $text, $font, $size, $color);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function solidFill(string $hex): array
    {
        return ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::rgb($hex)]];
    }

    private static function rgb(string $hex): string
    {
        return ltrim($hex, '#');
    }
}
