<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReportExport implements Export, WithMultipleSheets
{
    /**
     * @param  array{title: string, sections: array<int, array{title: string, headings: array, rows: array}>}  $report
     */
    public function __construct(private readonly array $report) {}

    public function sheets(): array
    {
        return array_map(
            fn (array $section) => new ReportSectionSheet($section['title'], $section['headings'], $section['rows']),
            $this->report['sections']
        );
    }
}
