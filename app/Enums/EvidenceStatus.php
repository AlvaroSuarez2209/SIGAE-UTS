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

    /**
     * Mensaje corto para la franja de estado del docente (rediseño visual
     * de evidence-workspace.blade.php) — el caso base por estado, sin el
     * contexto real del periodo/fechas que sí aplica
     * EvidenceWorkspace::statusMessage() antes de usar este valor.
     */
    public function teacherStatusMessage(): string
    {
        return match ($this) {
            self::Pending, self::Draft => 'Aún no la envías. Complétala antes de la fecha límite.',
            self::Submitted => 'Enviada, a la espera de revisión.',
            self::NeedsAdjustment => 'Devuelta para ajustes. Corrígela y vuelve a enviarla.',
            self::Approved => 'Aprobada. No es necesario hacer nada más.',
            self::Expired => 'El plazo venció sin envío.',
            self::Exempt => 'Marcada como exenta. No necesitas enviarla.',
        };
    }

    /**
     * Reverso de label(): los informes (ReportBuilder) entregan cada fila
     * ya como texto plano en español, no como el enum — necesario para
     * poder mostrar <x-status-badge> (que espera el enum) en la vista en
     * pantalla de esos informes sin cambiar esa estructura de datos.
     */
    public static function fromLabel(string $label): ?self
    {
        foreach (self::cases() as $status) {
            if ($status->label() === $label) {
                return $status;
            }
        }

        return null;
    }
}
