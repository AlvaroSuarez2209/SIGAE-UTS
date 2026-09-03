<?php

namespace App\Enums;

enum RoleName: string
{
    case Administrator = 'administrator';
    case Coordination = 'coordination';
    case Leader = 'leader';
    case Teacher = 'teacher';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrador',
            self::Coordination => 'Coordinación',
            self::Leader => 'Líder',
            self::Teacher => 'Docente',
            self::Auditor => 'Auditor',
        };
    }
}
