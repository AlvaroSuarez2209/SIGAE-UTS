<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Registra automáticamente en la bitácora de auditoría cada creación y
 * cambio relevante de los modelos que usan este trait — sin tener que
 * llamar a AuditLog::record() manualmente en cada Livewire component.
 *
 * Se omiten los datos sensibles (contraseñas) y el ruido de
 * updated_at, y no se registra nada durante `db:seed` (para no llenar la
 * bitácora de datos de demostración) — pero sí durante las pruebas
 * automatizadas, para poder verificar el comportamiento.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            if (self::auditingDisabled()) {
                return;
            }

            AuditLog::record('created', $model);
        });

        static::updated(function ($model) {
            if (self::auditingDisabled()) {
                return;
            }

            $sensitive = ['password', 'remember_token'];
            $allChanges = collect($model->getChanges())->except('updated_at');

            if ($allChanges->isEmpty()) {
                return;
            }

            // Un cambio de contraseña sigue siendo una acción auditable —
            // solo se omite el valor en sí, nunca el hecho de que cambió.
            $redactedFields = $allChanges->keys()->intersect($sensitive)->values()->all();
            $visibleChanges = $allChanges->except($sensitive)->all();

            AuditLog::record('updated', $model, array_filter([
                'changes' => $visibleChanges ?: null,
                'redacted_fields' => $redactedFields ?: null,
            ]));
        });
    }

    private static function auditingDisabled(): bool
    {
        return app()->runningInConsole() && ! app()->runningUnitTests();
    }
}
