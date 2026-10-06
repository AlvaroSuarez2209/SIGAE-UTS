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

            // Solo el nombre (cuando el modelo tiene uno) — suficiente
            // para que AuditLogPresenter arme "Componente 'X' creado" en
            // vez de un Detalle vacío, sin guardar el resto de atributos
            // (ni falta hace, ni hay por qué ampliar lo que queda en la
            // bitácora más allá de lo necesario para describir el evento).
            $name = $model->getAttribute('name');

            AuditLog::record('created', $model, $name ? ['name' => $name] : []);
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
            $visibleChanges = $allChanges->except($sensitive);

            // $model->getOriginal() todavía no está sincronizado con los
            // valores nuevos en este punto exacto: Eloquent dispara el
            // evento 'updated' (performUpdate(), tras syncChanges()) ANTES
            // de syncOriginal() (que corre en finishSave(), ya de vuelta en
            // save()) — es la única ventana en la que el valor ANTERIOR
            // real sigue disponible. Los registros ya existentes (antes de
            // este cambio) simplemente no tendrán esta clave — ver
            // AuditLogPresenter::describeFieldChange().
            $previousValues = $visibleChanges->keys()
                ->mapWithKeys(fn ($field) => [$field => $model->getOriginal($field)])
                ->all();

            AuditLog::record('updated', $model, array_filter([
                'changes' => $visibleChanges->all() ?: null,
                'previous' => $previousValues ?: null,
                'redacted_fields' => $redactedFields ?: null,
            ]));
        });
    }

    private static function auditingDisabled(): bool
    {
        return app()->runningInConsole() && ! app()->runningUnitTests();
    }
}
