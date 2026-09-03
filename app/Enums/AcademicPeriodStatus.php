<?php

namespace App\Enums;

enum AcademicPeriodStatus: string
{
    case Planning = 'planning';
    case Active = 'active';
    case Closed = 'closed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Planning => 'En planeación',
            self::Active => 'Activo',
            self::Closed => 'Cerrado',
            self::Archived => 'Archivado',
        };
    }
}
