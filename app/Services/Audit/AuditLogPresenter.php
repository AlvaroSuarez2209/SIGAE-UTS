<?php

namespace App\Services\Audit;

use App\Enums\AcademicPeriodStatus;
use App\Enums\DeliverableStatus;
use App\Enums\DocumentType;
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
     * Los 14 modelos que usan el trait `Auditable` hoy
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
        'InstitutionSettings' => 'Identidad institucional',
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
        'document_type' => 'Tipo de documento',
        'logo_path' => 'Logo',
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
        'exemption_reason' => 'Motivo de exención',
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
        'document_type' => DocumentType::class,
    ];

    private const MODEL_FIELD_ENUMS = [
        'Evidence' => ['status' => EvidenceStatus::class],
        'AcademicPeriod' => ['status' => AcademicPeriodStatus::class],
        'Deliverable' => ['status' => DeliverableStatus::class],
    ];

    /**
     * Para la concordancia de género de "creado"/"creada" en
     * describeCreation() — conjunto cerrado igual que MODEL_LABELS, nunca
     * se infiere automáticamente (sería frágil con nombres compuestos como
     * "Compromiso transversal", gramaticalmente masculino pese a terminar
     * distinto a los demás masculinos de la lista).
     */
    private const FEMININE_MODELS = [
        'Activity', 'DeliverableTemplate', 'Evidence', 'InstitutionSettings', 'Review', 'TeacherAssignment',
    ];

    /**
     * `actionLabel()` sin $log (p. ej. el <select> del filtro, que solo
     * tiene el valor crudo de `action` sin un registro real detrás) sigue
     * devolviendo el genérico de ACTION_LABELS — filtrar por "Modificación"
     * sigue acotando por la columna real `action = 'updated'`, la
     * especificidad es solo de presentación por fila (ver
     * specificUpdateLabel()), nunca de qué se puede filtrar.
     */
    public static function actionLabel(string $action, ?AuditLog $log = null): string
    {
        if ($action === 'updated' && $log) {
            return self::specificUpdateLabel($log);
        }

        return self::ACTION_LABELS[$action] ?? $action;
    }

    /**
     * Revisión de la directora, Prioridad 7: "Modificación" no decía nada
     * sobre qué cambió realmente. Deriva una etiqueta más específica a
     * partir del contenido real de `metadata->changes` — nunca cambia lo
     * que se guarda en la columna `action` (sigue siendo `updated`), es
     * puramente de presentación, igual que describeChanges(). Prioridad:
     * is_active (el caso binario más claro) antes que status (cualquier
     * otro cambio de estado), antes que el resto ("Edición").
     */
    private static function specificUpdateLabel(AuditLog $log): string
    {
        $changes = (array) ($log->metadata['changes'] ?? []);

        if (array_key_exists('is_active', $changes)) {
            return filter_var($changes['is_active'], FILTER_VALIDATE_BOOLEAN) ? 'Activación' : 'Desactivación';
        }

        if (array_key_exists('status', $changes)) {
            return 'Cambio de estado';
        }

        return 'Edición';
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
     * antes. `created` tiene su propia rama (ver describeCreation()): no
     * tiene `changes` que recorrer, tiene como mucho un `name`.
     */
    public static function describeChanges(AuditLog $log): string
    {
        if ($log->action === 'created') {
            return self::describeCreation($log);
        }

        if ($log->action === 'evidence_marked_exempt') {
            return self::describeExemption($log);
        }

        $metadata = $log->metadata ?? [];
        $modelBasename = $log->auditable_type ? class_basename($log->auditable_type) : null;
        $previous = (array) ($metadata['previous'] ?? []);

        $parts = [];

        foreach ((array) ($metadata['changes'] ?? []) as $field => $value) {
            if (in_array($field, self::SUPPRESSED_FIELDS, true)) {
                continue;
            }

            $hasPrevious = array_key_exists($field, $previous);

            $parts[] = self::describeFieldChange($modelBasename, $field, $value, $hasPrevious ? $previous[$field] : null, $hasPrevious);
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

    /**
     * Revisión de la directora, Prioridad 7: antes, una creación no
     * guardaba ningún metadata — el Detalle quedaba vacío. Auditable ahora
     * guarda el `name` del modelo cuando lo tiene (ver bootAuditable());
     * sin nombre (Evidencia, Liderazgo, Asignación docente — identificados
     * por sus relaciones, no por un nombre propio), cae al genérico.
     */
    private static function describeCreation(AuditLog $log): string
    {
        if (! $log->auditable_type) {
            return '';
        }

        $modelBasename = class_basename($log->auditable_type);
        $label = self::MODEL_LABELS[$modelBasename] ?? $modelBasename;
        $participle = in_array($modelBasename, self::FEMININE_MODELS, true) ? 'creada' : 'creado';
        $name = $log->metadata['name'] ?? null;

        return $name ? "{$label} '{$name}' {$participle}" : "{$label} {$participle}";
    }

    /**
     * `evidence_marked_exempt` guarda `justification`, no `changes` — el
     * genérico de arriba (que solo recorre `metadata->changes`/
     * `redacted_fields`) dejaba la columna "Detalle" vacía para esta
     * acción, aunque la razón sí se guardaba en la fila de auditoría.
     * Revisión del estado Exento.
     */
    private static function describeExemption(AuditLog $log): string
    {
        $justification = $log->metadata['justification'] ?? null;

        return $justification ? "Motivo: {$justification}" : '';
    }

    /**
     * Prioridad 7, parte 2: desglose campo por campo para el modal de
     * detalle (describeChanges() sigue siendo la frase compacta de la
     * columna "Detalle" de la tabla) — a diferencia de describeFieldChange(),
     * aquí is_active NO tiene frase especial: el modal muestra el valor
     * crudo formateado ("Sí"/"No") en sus propias columnas antes/después,
     * en vez de una oración, así que no hace falta la distinción
     * "Cuenta activada" vs "Activado". `evidence_marked_exempt` tiene su
     * propia rama por el mismo motivo que describeChanges(): su metadata
     * no tiene `changes` que recorrer.
     *
     * @return array<int, array{label: string, after: string, before: ?string}>
     */
    public static function changeEntries(AuditLog $log): array
    {
        if ($log->action === 'evidence_marked_exempt') {
            $justification = $log->metadata['justification'] ?? null;

            return $justification ? [['label' => 'Motivo', 'after' => $justification, 'before' => null]] : [];
        }

        $metadata = $log->metadata ?? [];
        $modelBasename = $log->auditable_type ? class_basename($log->auditable_type) : null;
        $previous = (array) ($metadata['previous'] ?? []);
        $entries = [];

        foreach ((array) ($metadata['changes'] ?? []) as $field => $value) {
            if (in_array($field, self::SUPPRESSED_FIELDS, true)) {
                continue;
            }

            $hasPrevious = array_key_exists($field, $previous);

            $entries[] = [
                'label' => self::fieldLabel($field),
                'after' => self::formatValue($modelBasename, $field, $value),
                'before' => $hasPrevious ? self::formatValue($modelBasename, $field, $previous[$field]) : null,
            ];
        }

        foreach ((array) ($metadata['redacted_fields'] ?? []) as $field) {
            if (in_array($field, self::SUPPRESSED_FIELDS, true)) {
                continue;
            }

            $entries[] = [
                'label' => $field === 'password' ? 'Contraseña' : self::fieldLabel($field),
                'after' => 'Actualizado (valor no mostrado por seguridad)',
                'before' => null,
            ];
        }

        return $entries;
    }

    private static function describeFieldChange(?string $modelBasename, string $field, mixed $value, mixed $previousValue, bool $hasPrevious): string
    {
        // is_active tiene una frase propia (y distinta si es un usuario -
        // "cuenta" - o un catálogo - sin esa palabra, para no hablar de
        // "cuenta" en un Componente/Actividad/etc.) — no necesita
        // antes/después, "activada"/"desactivada" ya dice la dirección.
        if ($field === 'is_active') {
            $activated = filter_var($value, FILTER_VALIDATE_BOOLEAN);

            if ($modelBasename === 'User') {
                return $activated ? 'Cuenta activada' : 'Cuenta desactivada';
            }

            return $activated ? 'Activado' : 'Desactivado';
        }

        $newFormatted = self::formatValue($modelBasename, $field, $value);

        // Sin valor anterior disponible (registros de antes de este
        // cambio, o el campo quedó redactado): mismo texto que siempre.
        if (! $hasPrevious) {
            return self::fieldLabel($field).' cambió a '.$newFormatted;
        }

        $oldFormatted = self::formatValue($modelBasename, $field, $previousValue);

        return self::fieldLabel($field)." cambió de {$oldFormatted} a {$newFormatted}";
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
