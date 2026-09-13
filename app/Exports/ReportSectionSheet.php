<?php

namespace App\Exports;

use App\Services\Reports\ReportTheme;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * WithStrictNullComparison es necesaria: sin ella, Maatwebsite escribe
 * cada celda con PhpSpreadsheet::fromArray() usando comparación "!=" en
 * vez de "!==" contra null, y en PHP "0 != null" es false —así que
 * cualquier valor entero 0 de un informe (p. ej. "Aprobados: 0") se
 * pierde silenciosamente y la celda queda vacía. Bug real y preexistente
 * de Maatwebsite/PhpSpreadsheet con esta configuración por defecto,
 * detectado al verificar el Excel de "Consolidado" (columna "Aprobados"
 * en 0 para varios docentes).
 */
class ReportSectionSheet implements Export, FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithStrictNullComparison, WithTitle
{
    /**
     * @param  array{label: string, value: string, detail: string}|null  $summary
     */
    public function __construct(
        private readonly string $reportTitle,
        private readonly string $title,
        private readonly array $headings,
        private readonly array $rows,
        private readonly ?array $summary = null,
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        // Los nombres de hoja de Excel no admiten : \ / ? * [ ] y tienen un
        // máximo de 31 caracteres.
        $safe = preg_replace('/[:\\\\\/\?\*\[\]]/', '-', $this->title);

        return mb_substr($safe, 0, 31);
    }

    public function registerEvents(): array
    {
        return [
            // El formato (encabezado institucional, colores de marca,
            // autofiltro, panes congelados, bordes, color de "Estado") vive
            // en un único lugar compartido — ver ReportTheme::styleExcelSheet().
            AfterSheet::class => function (AfterSheet $event): void {
                ReportTheme::styleExcelSheet(
                    $event->sheet->getDelegate(),
                    $this->reportTitle,
                    $this->title,
                    $this->headings,
                    $this->rows,
                    $this->summary,
                );
            },
        ];
    }
}
