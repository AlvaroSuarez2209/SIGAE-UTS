<?php

namespace App\Exports;

use App\Services\AdminImport\AdminImportService;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class AdminImportInstructionsSheet implements Export, FromArray, WithTitle
{
    public function array(): array
    {
        return [
            ['Instrucciones para la importación masiva de administradores y coordinadores'],
            [''],
            ['No cambies los nombres de las columnas de la hoja "Plantilla".'],
            ['nombre_completo: nombre y apellidos completos.'],
            ['correo_institucional: correo con el que la persona iniciará sesión.'],
            ['roles: Administrador, Coordinación, o ambos separados por coma (ej. "Administrador, Coordinación"). No se permite ningún otro rol desde esta plantilla — usa "Importar docentes" para Docente, Líder o Auditor.'],
            ['estado: Activo o Inactivo.'],
            [''],
            ['Las personas creadas reciben un correo para establecer su propia contraseña — no se incluye ninguna contraseña en este archivo.'],
            [''],
            ['Máximo '.AdminImportService::MAX_ROWS.' filas por archivo.'],
        ];
    }

    public function title(): string
    {
        return 'Instrucciones';
    }
}
