<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReportExport implements Export, WithMultipleSheets
{
    /**
     * @param  array{title: string, sections: array<int, array{title: string, headings: array, rows: array}>, summary?: array{label: string, value: string, detail: string}}  $report
     * @param  array<string, string>|null  $filtersSummary  Mismo formato que recibe el PDF (ExportsTabularReport::pdf()): ['Periodo' => 'X', 'Docente' => 'Y', ...], ya filtrado a solo los filtros activos.
     */
    public function __construct(
        private readonly array $report,
        private readonly ?array $filtersSummary = null,
    ) {}

    public function sheets(): array
    {
        return array_map(
            fn (array $section) => new ReportSectionSheet(
                $this->report['title'],
                $section['title'],
                $section['headings'],
                $section['rows'],
                $this->report['summary'] ?? null,
                $this->filtersSummary,
            ),
            $this->report['sections']
        );
    }
}
