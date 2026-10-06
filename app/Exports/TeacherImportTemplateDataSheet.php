<?php

namespace App\Exports;

use App\Services\Reports\ReportTheme;
use App\Services\TeacherImport\TeacherImportService;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Hoja de datos de la plantilla de importación de docentes — encabezados +
 * una fila de ejemplo correctamente diligenciada. Formato (ver
 * registerEvents()): encabezado en negrita con el mismo azul de marca que
 * usan los informes exportables (App\Services\Reports\ReportTheme), ancho
 * de columna automático (ShouldAutoSize), encabezado congelado, y listas
 * desplegables en "roles" y "estado" para prevenir errores de tipeo antes
 * de subir el archivo.
 *
 * Límite real de Excel documentado aquí, no escondido: la lista desplegable
 * de "roles" solo ofrece "Docente" o "Líder" por separado — el formato de
 * lista de validación de datos de Excel usa la coma como separador entre
 * opciones, así que no puede ofrecer "Docente, Líder" como una tercera
 * opción de la misma lista sin ambigüedad. Quien necesite ambos roles debe
 * escribirlo a mano ("Docente, Líder") — sigue siendo válido para
 * TeacherImportService, que no depende de cómo se llenó la celda.
 */
class TeacherImportTemplateDataSheet implements Export, FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    public function headings(): array
    {
        return TeacherImportService::REQUIRED_HEADERS;
    }

    public function array(): array
    {
        return [
            ['CC', '1234567890', 'Ana María Pérez Gómez', 'ana.perez@uts.edu.co', 'Ingeniería de Sistemas', 'Docente', 'Activo'],
        ];
    }

    public function title(): string
    {
        return 'Plantilla';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = Coordinate::stringFromColumnIndex(count($this->headings()));

                $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => ltrim(ReportTheme::BRAND_PRIMARY, '#')],
                    ],
                ]);

                $sheet->freezePane('A2');

                $lastRow = TeacherImportService::MAX_ROWS + 1;

                $rolesValidation = $this->listValidation('Docente,Líder');
                $estadoValidation = $this->listValidation('Activo,Inactivo');

                for ($row = 2; $row <= $lastRow; $row++) {
                    $sheet->getCell("F{$row}")->setDataValidation(clone $rolesValidation);
                    $sheet->getCell("G{$row}")->setDataValidation(clone $estadoValidation);
                }
            },
        ];
    }

    private function listValidation(string $commaSeparatedOptions): DataValidation
    {
        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('Valor no válido');
        $validation->setError('Selecciona un valor de la lista.');
        $validation->setPromptTitle('Selecciona un valor');
        $validation->setPrompt('Elige una opción de la lista desplegable.');
        $validation->setFormula1('"'.$commaSeparatedOptions.'"');

        return $validation;
    }
}
