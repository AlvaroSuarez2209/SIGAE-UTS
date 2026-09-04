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

    public function color(): string
    {
        return match ($this) {
            self::Planning => 'neutral',
            self::Active => 'success',
            self::Closed => 'warning',
            self::Archived => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Planning => 'pencil',
            self::Active => 'check-circle',
            self::Closed => 'alert-triangle',
            self::Archived => 'shield-check',
        };
    }
}
