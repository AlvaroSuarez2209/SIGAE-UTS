<?php

namespace App\Services\Catalog;

use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Component;
use App\Models\CrossCuttingCommitment;
use App\Models\Deliverable;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\Subcomponent;
use App\Models\TeacherAssignment;

/**
 * Punto 2 (borrado real de catálogo): a diferencia de
 * CatalogDependencyChecker (que cuenta solo dependientes "activos"/
 * "abiertos", para decidir si se puede DESACTIVAR), esto cuenta
 * CUALQUIER fila que referencie al elemento — activa o inactiva,
 * publicada o borrador, vigente o no — porque borrar es irreversible:
 * ningún registro histórico puede quedar apuntando a una fila que ya
 * no existe. Nunca se reutilizan los scopes active()/openXxx() de
 * CatalogDependencyChecker aquí a propósito.
 */
class CatalogDeletionChecker
{
    /**
     * @return list<string>
     */
    public static function blockersForComponent(Component $component): array
    {
        return array_values(array_filter([
            self::countLabel($component->subcomponents()->count(), 'subcomponente', 'subcomponentes'),
            self::countLabel($component->activities()->count(), 'actividad', 'actividades'),
        ]));
    }

    /**
     * @return list<string>
     */
    public static function blockersForSubcomponent(Subcomponent $subcomponent): array
    {
        return array_values(array_filter([
            self::countLabel($subcomponent->activities()->count(), 'actividad', 'actividades'),
        ]));
    }

    /**
     * @return list<string>
     */
    public static function blockersForActivity(Activity $activity): array
    {
        return array_values(array_filter([
            self::countLabel($activity->deliverables()->count(), 'entregable', 'entregables'),
            self::countLabel($activity->teacherAssignments()->count(), 'asignación docente', 'asignaciones docentes'),
            self::countLabel(Leadership::where('activity_id', $activity->id)->count(), 'liderazgo', 'liderazgos'),
        ]));
    }

    /**
     * @return list<string>
     */
    public static function blockersForProgramUnit(ProgramUnit $programUnit): array
    {
        return array_values(array_filter([
            self::countLabel($programUnit->teacherAssignments()->count(), 'asignación docente', 'asignaciones docentes'),
            self::countLabel($programUnit->leaderships()->count(), 'liderazgo', 'liderazgos'),
            self::countLabel($programUnit->users()->count(), 'docente/líder asignado directamente', 'docentes/líderes asignados directamente'),
        ]));
    }

    /**
     * @return list<string>
     */
    public static function blockersForCrossCuttingCommitment(CrossCuttingCommitment $commitment): array
    {
        return array_values(array_filter([
            self::countLabel($commitment->deliverables()->count(), 'entregable', 'entregables'),
        ]));
    }

    /**
     * La plantilla de entregable nunca tiene bloqueadores: un entregable
     * ya no depende de su plantilla tras copiarse
     * (deliverables.deliverable_template_id es nullOnDelete) — ver
     * CatalogDeletionService::deleteDeliverableTemplate(), que limpia
     * sus archivos de referencia por su cuenta.
     *
     * @return list<string>
     */
    public static function blockersForDeliverableTemplate(): array
    {
        return [];
    }

    /**
     * Las evidencias quedan cubiertas de forma transitiva: dependen de
     * `deliverables`, nunca directo del periodo — si el periodo tiene
     * algún entregable, ya está bloqueado por esa cuenta, sin importar
     * cuántas evidencias tenga ese entregable.
     *
     * @return list<string>
     */
    public static function blockersForAcademicPeriod(AcademicPeriod $period): array
    {
        return array_values(array_filter([
            self::countLabel(TeacherAssignment::where('academic_period_id', $period->id)->count(), 'asignación docente', 'asignaciones docentes'),
            self::countLabel(Leadership::where('academic_period_id', $period->id)->count(), 'liderazgo', 'liderazgos'),
            self::countLabel(Deliverable::where('academic_period_id', $period->id)->count(), 'entregable', 'entregables'),
        ]));
    }

    /**
     * Punto 2: el periodo de pruebas de la directora (ver config/catalogs.php)
     * nunca se puede borrar, ni siquiera sin ningún dependiente — a
     * diferencia de los otros 6 tipos, esto no es una regla de
     * dependencias, es una lista de códigos protegidos.
     */
    public static function isProtectedAcademicPeriod(AcademicPeriod $period): bool
    {
        return in_array($period->name, config('catalogs.protected_academic_period_codes', []), true);
    }

    /**
     * Único punto que arma el mensaje combinado — usado solo por
     * CatalogDeletionService como mensaje de respaldo si la
     * comprobación final dentro de la transacción también bloquea (la
     * UI, desde el ajuste al punto 2, ya no pinta esta frase completa:
     * usa blockersForX()/deactivationSuggestionForX() por separado, ver
     * <x-catalog-delete-modal>). $pronoun es 'lo' o 'la' según el
     * género del tipo ("desactivarlo" un componente, "desactivarla" una
     * actividad).
     *
     * @param  list<string>  $blockers
     */
    public static function blockingMessage(array $blockers, string $pronoun = 'lo'): ?string
    {
        if ($blockers === []) {
            return null;
        }

        return 'No se puede eliminar: tiene '.implode(' y ', $blockers).'. '.self::deactivationSuggestion($pronoun);
    }

    /**
     * Frase de salida que ofrece el modal de borrado cuando hay
     * dependientes — nunca se repite este texto por separado en cada
     * Index, para que la UI y el mensaje de respaldo del servicio
     * (blockingMessage()) digan siempre lo mismo.
     */
    public static function deactivationSuggestion(string $pronoun = 'lo'): string
    {
        return "Puedes desactivar{$pronoun} para que deje de aparecer en el sistema.";
    }

    public static function deactivationSuggestionForComponent(): string
    {
        return self::deactivationSuggestion('lo');
    }

    public static function deactivationSuggestionForSubcomponent(): string
    {
        return self::deactivationSuggestion('lo');
    }

    public static function deactivationSuggestionForActivity(): string
    {
        return self::deactivationSuggestion('la');
    }

    public static function deactivationSuggestionForProgramUnit(): string
    {
        return self::deactivationSuggestion('lo');
    }

    public static function deactivationSuggestionForCrossCuttingCommitment(): string
    {
        return self::deactivationSuggestion('lo');
    }

    public static function blockingMessageForComponent(Component $component): ?string
    {
        return self::blockingMessage(self::blockersForComponent($component), 'lo');
    }

    public static function blockingMessageForSubcomponent(Subcomponent $subcomponent): ?string
    {
        return self::blockingMessage(self::blockersForSubcomponent($subcomponent), 'lo');
    }

    public static function blockingMessageForActivity(Activity $activity): ?string
    {
        return self::blockingMessage(self::blockersForActivity($activity), 'la');
    }

    public static function blockingMessageForProgramUnit(ProgramUnit $programUnit): ?string
    {
        return self::blockingMessage(self::blockersForProgramUnit($programUnit), 'lo');
    }

    public static function blockingMessageForCrossCuttingCommitment(CrossCuttingCommitment $commitment): ?string
    {
        return self::blockingMessage(self::blockersForCrossCuttingCommitment($commitment), 'lo');
    }

    /**
     * Los periodos académicos no tienen un interruptor de
     * activo/inactivo que ofrecer como alternativa — a diferencia de
     * los otros 6 tipos, el mensaje no invita a "desactivarlo". La
     * protección por código (isProtectedAcademicPeriod()) se comprueba
     * primero y es definitiva: un periodo protegido nunca se describe
     * como "sin dependientes", aunque de hecho no tenga ninguno.
     */
    public static function blockingMessageForAcademicPeriod(AcademicPeriod $period): ?string
    {
        if (self::isProtectedAcademicPeriod($period)) {
            return 'Este periodo está protegido y no puede eliminarse.';
        }

        $blockers = self::blockersForAcademicPeriod($period);

        if ($blockers === []) {
            return null;
        }

        return 'No se puede eliminar: tiene '.implode(' y ', $blockers).'.';
    }

    private static function countLabel(int $count, string $singular, string $plural): ?string
    {
        if ($count === 0) {
            return null;
        }

        return $count === 1 ? "1 {$singular}" : "{$count} {$plural}";
    }
}
