<?php

namespace App\Enums;

enum PeriodicityType: string
{
    case Single = 'single';
    case ByTerm = 'by_term';
    case Monthly = 'monthly';
    case Biweekly = 'biweekly';
    case Weekly = 'weekly';
    case ByMilestone = 'by_milestone';
    case Extraordinary = 'extraordinary';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Único',
            self::ByTerm => 'Por corte',
            self::Monthly => 'Mensual',
            self::Biweekly => 'Quincenal',
            self::Weekly => 'Semanal',
            self::ByMilestone => 'Por hito',
            self::Extraordinary => 'Extraordinario',
        };
    }
}
