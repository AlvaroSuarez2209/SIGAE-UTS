<?php

namespace App\Services\Audit;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\EvidenceType;
use App\Enums\PeriodicityType;
use App\Enums\ReviewDecision;
use App\Models\AuditLog;
use Illuminate\Support\Collection;

/**
 * Único punto de traducción de la bitácora de auditoría a lenguaje claro
 * para Administración/Coordinación — nunca se traduce lo que se guarda en
 * `audit_logs` (columna `action`, `auditable_type`, claves de
 * `metadata->changes`), que sigue en inglés/snake_case a propósito (es
 * nomenclatura de código, y filtrar por ella no cambia). Solo se traduce
 * lo que ve el usuario en la tabla y en el filtro de "Acción"
 * (`audit-log-index.blade.php`).
 *
 * Los tres mapeos de abajo son conjuntos cerrados que reflejan el código
 * real de la aplicación al momento de escribir esto — no lo que hay hoy
 * en la base de datos de demostración (que puede estar vacía de algunos
 * de estos valores tras un `migrate:fresh`). Si se agrega una acción
 * nueva (`AuditLog::record('algo_nuevo', ...)`), un modelo nuevo con el
 * trait `Auditable`, o un campo nuevo que valga la pena explicar mejor
 * que el respaldo genérico, hay que sumarlo aquí — es el único lugar.
 */
class AuditLogPresenter
{
    /**
     * Todas las acciones que el código realmente registra
     * (`grep -rn "AuditLog::record(" app/`), no solo las de `updated`/
     * `created` automáticas del trait Auditable.
     */
    private const ACTION_LABELS = [
        'created' => 'Creación',
        'updated' => 'Modificación',
        'login' => 'Inicio de sesión',
        'logout' => 'Cierre de sesión',
        'login_failed' => 'Acceso fallido',
        'login_blocked_inactive' => 'Bloqueado (inactivo)',
        'password_reset_requested' => 'Solicitud de contraseña',
        'password_reset_completed' => 'Contraseña restablecida',
        'evidence_marked_exempt' => 'Marcada como exenta',
        'evidence_marked_overdue' => 'Vencimiento automático',
        'evidence_exemption_removed' => 'Exención removida',
    ];

    /**
     * Los 13 modelos que usan el trait `Auditable` hoy
     * (`grep -rl Auditable app/Models`), indexados por `class_basename()`
     * — es justo lo que guarda `auditable_type` truncado por Eloquent.
     */
    private const MODEL_LABELS = [
        'AcademicPeriod' => 'Periodo académico',
        'Activity' => 'Actividad',
        'Component' => 'Componente',
        'CrossCuttingCommitment' => 'Compromiso transversal',
        'Deliverable' => 'Entregable',
        'DeliverableTemplate' => 'Plantilla de entregable',
        'Evidence' => 'Evidencia',
        'Leadership' => 'Liderazgo',
        'ProgramUnit' => 'Programa',
        'Review' => 'Revisión',
        'Subcomponent' => 'Subcomponente',
        'TeacherAssignment' => 'Asignación docente',
        'User' => 'Usuario',
    ];

    /**
     * Nombres de columna que aparecen en `$fillable` de alguno de los 13
     * modelos de arriba y que vale la pena mostrar con un nombre propio
     * en vez del respaldo genérico (snake_case tal cual). No es
     * exhaustivo a propósito: un campo nuevo que no esté aquí simplemente
     * cae al respaldo genérico (ver `describeFieldChange()`), nunca se
     * rompe ni se oculta.
     */
    private const FIELD_LABELS = [
        'name' => 'Nombre',
        'status' => 'Estado',
        'email' => 'Correo electrónico',
        'document_number' => 'Documento de identidad',
        'description' => 'Descripción',
        'instructions' => 'Instrucciones',
        'completion_criteria' => 'Criterio de cumplimiento',
        'is_mandatory' => 'Obligatorio',
        'periodicity_type' => 'Periodicidad',
        'opens_at' => 'Fecha de apertura',
        'due_at' => 'Fecha límite',
        'closes_at' => 'Fecha de cierre',
        'max_files' => 'Máximo de archivos',
        'max_file_size_mb' => 'Tamaño máximo de archivo (MB)',
        'weight_percentage' => 'Peso porcentual',
        'allowed_evidence_types' => 'Tipos de evidencia permitidos',
        'allowed_file_types' => 'Tipos de archivo permitidos',
        'assigned_hours' => 'Horas asignadas',
        'notes' => 'Notas',
        'decision' => 'Decisión',
        'decided_at' => 'Fecha de decisión',
        'starts_at' => 'Vigente desde',
        'ends_at' => 'Vigente hasta',
        'start_date' => 'Fecha de inicio',
        'end_date' => 'Fecha de fin',
        'component_id' => 'Componente',
        'subcomponent_id' => 'Subcomponente',
        'activity_id' => 'Actividad',
        'program_unit_id' => 'Programa',
        'academic_period_id' => 'Periodo académico',
        'deliverable_id' => 'Entregable',
        'deliverable_template_id' => 'Plantilla de entregable',
        'cross_cutting_commitment_id' => 'Compromiso transversal',
        'user_id' => 'Usuario',
        'evidence_version_id' => 'Versión de evidencia',
        'reviewer_id' => 'Revisor',
        'current_version_id' => 'Versión actual',
    ];

    /**
     * Campos que nunca se muestran, ni siquiera con el respaldo genérico
     * — puramente técnicos, sin ningún significado legible para
     * Administración/Coordinación (a diferencia de `password`, que sí se
     * anuncia como "Contraseña actualizada" sin revelar el valor).
     */
    private const SUPPRESSED_FIELDS = [
        'remember_token',
    ];

    /**
     * `getChanges()` de Eloquent entrega el valor CRUDO tal como se
     * guarda en la columna (ej. `'pending'`, `'exempt'`), no la instancia
     * del enum — así que sin este mapeo, el respaldo genérico mostraba
     * literalmente el valor en inglés del enum ("Estado cambió a
     * pending") en vez de su `label()` ya traducido ("Estado cambió a
     * Pendiente"). `status` es ambiguo entre modelos (Evidence y
     * AcademicPeriod usan enums de estado distintos con el mismo nombre
     * de columna) y se resuelve por modelo en `MODEL_FIELD_ENUMS`; el
     * resto de campos con enum no se repite entre modelos.
     */
    private const FIELD_ENUMS = [
        'decision' => ReviewDecision::class,
        'periodicity_type' => PeriodicityType::class,
        'allowed_evidence_types' => EvidenceType::class,
    ];

    private const MODEL_FIELD_ENUMS = [
        'Evidence' => ['status' => EvidenceStatus::class],
        'AcademicPeriod' => ['status' => AcademicPeriodStatus::class],
    ];

    public static function actionLabel(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? $action;
    }

    /**
     * Para el <select> del filtro "Acción" — mismo valor crudo en el
     * atributo `value` (lo que de verdad se compara en el WHERE), solo
     * la etiqueta visible cambia.
     *
     * @param  Collection<int, string>  $actions  valores crudos ya presentes en la tabla
     * @return array<string, string> value => etiqueta
     */
    public static function actionOptions(iterable $actions): array
    {
        $options = [];

        foreach ($actions as $action) {
            $options[$action] = self::actionLabel($action);
        }

        return $options;
    }

    public static function auditableLabel(?string $auditableType): ?string
    {
        if (! $auditableType) {
            return null;
        }

        $basename = class_basename($auditableType);

        return self::MODEL_LABELS[$basename] ?? $basename;
    }

    /**
     * Texto legible de la columna "Detalle" a partir de `metadata`. Vacío
     * si no hay nada que describir (p. ej. `login`/`logout`), igual que
     * antes.
     */
    public static function describeChanges(AuditLog $log): string
    {
        $metadata = $log->metadata ?? [];
        $modelBasename = $log->auditable_type ? class_basename($log->auditable_type) : null;

        $parts = [];

        foreach ((array) ($metadata['changes'] ?? []) as $field => $value) {
            if (in_array($field, self::SUPPRESSED_FIELDS, true)) {
                continue;
            }

            $parts[] = self::describeFieldChange($modelBasename, $field, $value);
        }

        foreach ((array) ($metadata['redacted_fields'] ?? []) as $field) {
            if (in_array($field, self::SUPPRESSED_FIELDS, true)) {
                continue;
            }

            $parts[] = $field === 'password'
                ? 'Contraseña actualizada'
                : self::fieldLabel($field).' actualizado';
        }

        return implode('; ', $parts);
    }

    private static function describeFieldChange(?string $modelBasename, string $field, mixed $value): string
    {
        // is_active tiene una frase propia (y distinta si es un usuario -
        // "cuenta" - o un catálogo - sin esa palabra, para no hablar de
        // "cuenta" en un Componente/Actividad/etc.).
        if ($field === 'is_active') {
            $activated = filter_var($value, FILTER_VALIDATE_BOOLEAN);

            if ($modelBasename === 'User') {
                return $activated ? 'Cuenta activada' : 'Cuenta desactivada';
            }

            return $activated ? 'Activado' : 'Desactivado';
        }

        return self::fieldLabel($field).' cambió a '.self::formatValue($modelBasename, $field, $value);
    }

    private static function fieldLabel(string $field): string
    {
        return self::FIELD_LABELS[$field] ?? $field;
    }

    private static function formatValue(?string $modelBasename, string $field, mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }

        if ($value === null) {
            return '(vacío)';
        }

        $enumClass = self::MODEL_FIELD_ENUMS[$modelBasename][$field] ?? self::FIELD_ENUMS[$field] ?? null;

        if (is_array($value)) {
            return implode(', ', array_map(
                fn ($item) => self::formatScalarValue($enumClass, $item),
                $value
            ));
        }

        return self::formatScalarValue($enumClass, $value);
    }

    private static function formatScalarValue(?string $enumClass, mixed $value): string
    {
        if ($enumClass && is_string($value)) {
            $case = $enumClass::tryFrom($value);

            if ($case) {
                return $case->label();
            }
        }

        return (string) $value;
    }
}
