<?php

namespace App\Enums;

/**
 * Tipo de vinculación de un Docente/Líder — `users.employment_type` sigue
 * siendo una columna de texto plano, nunca cast a este enum, mismo
 * criterio que App\Enums\DocumentType (ver su docblock): el resto del
 * código (AssignmentForm, la importación) compara el valor crudo tal cual.
 */
enum EmploymentType: string
{
    case Planta = 'planta';
    case TiempoCompleto = 'tiempo_completo';
    case MedioTiempo1M = 'medio_tiempo_1m';
    case MedioTiempo4M = 'medio_tiempo_4m';

    public function label(): string
    {
        return match ($this) {
            self::Planta => 'Planta',
            self::TiempoCompleto => 'Tiempo completo',
            self::MedioTiempo1M => 'Medio tiempo 1M',
            self::MedioTiempo4M => 'Medio tiempo 4M',
        };
    }
}
