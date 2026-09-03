<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportSectionSheet implements Export, FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private readonly string $title,
        private readonly array $headings,
        private readonly array $rows,
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
}
