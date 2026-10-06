<?php

namespace App\Exports;

use App\Services\TeacherImport\TeacherImportService;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class TeacherImportInstructionsSheet implements Export, FromArray, WithTitle
{
    public function array(): array
    {
        return [
            ['Instrucciones para la importación masiva de docentes'],
            [''],
            ['No cambies los nombres de las columnas de la hoja "Plantilla".'],
            ['tipo_documento: uno de '.implode(', ', TeacherImportService::DOCUMENT_TYPES).'.'],
            ['numero_documento: sin puntos ni espacios.'],
            ['nombre_completo: nombre y apellidos completos del docente.'],
            ['correo_institucional: correo con el que el docente iniciará sesión.'],
            ['codigo_programa: debe coincidir con el nombre exacto de un programa académico ya registrado en Catálogos > Programas académicos (ej. "Ingeniería de Sistemas").'],
            ['roles: Docente, Líder, o ambos separados por coma (ej. "Docente, Líder"). No se permite ningún otro rol desde esta plantilla.'],
            ['estado: Activo o Inactivo.'],
            [''],
            ['Los docentes creados reciben un correo para establecer su propia contraseña — no se incluye ninguna contraseña en este archivo.'],
            [''],
            ['Fila de ejemplo correctamente diligenciada: ver la hoja "Plantilla".'],
            ['Máximo '.TeacherImportService::MAX_ROWS.' filas por archivo.'],
        ];
    }

    public function title(): string
    {
        return 'Instrucciones';
    }
}
