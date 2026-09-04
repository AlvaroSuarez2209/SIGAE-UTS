<?php

namespace App\Enums;

enum EvidenceStatus: string
{
    case Pending = 'pending';
    case Draft = 'draft';
    case Submitted = 'submitted';
    case NeedsAdjustment = 'needs_adjustment';
    case Approved = 'approved';
    case Expired = 'expired';
    case Exempt = 'exempt';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Draft => 'Borrador',
            self::Submitted => 'Enviado',
            self::NeedsAdjustment => 'Requiere ajustes',
            self::Approved => 'Aprobado',
            self::Expired => 'Vencido',
            self::Exempt => 'Exento',
        };
    }

    /**
     * Estados en los que el docente puede seguir editando la versión actual.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Pending, self::Draft, self::NeedsAdjustment], true);
    }

    /**
     * Tono semántico para <x-status-badge> — nunca es el único indicador del
     * estado (siempre va acompañado de icon() y label()), ver RNF-012.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'neutral',
            self::Draft => 'secondary',
            self::Submitted => 'primary',
            self::NeedsAdjustment => 'warning',
            self::Approved => 'success',
            self::Expired => 'error',
            self::Exempt => 'accent',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::Draft => 'pencil',
            self::Submitted => 'send',
            self::NeedsAdjustment => 'alert-triangle',
            self::Approved => 'check-circle',
            self::Expired => 'alert-circle',
            self::Exempt => 'shield-check',
        };
    }
}
