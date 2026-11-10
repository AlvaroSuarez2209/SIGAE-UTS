<?php

namespace App\Services\Catalog;

use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Component;
use App\Models\CrossCuttingCommitment;
use App\Models\DeliverableTemplate;
use App\Models\ProgramUnit;
use App\Models\Subcomponent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Punto 2: borrado real (físico, no is_active=false) de los 6 tipos de
 * catálogo. Cada método vuelve a comprobar las dependencias DENTRO de
 * la transacción, sobre la fila bloqueada con lockForUpdate() — la
 * comprobación que ya hizo la interfaz para decidir si mostrar el
 * botón habilitado pudo quedar desactualizada por una fila creada justo
 * después (condición de carrera); este segundo chequeo, con el bloqueo
 * puesto, es el que de verdad decide. Si aun así la base rechaza el
 * borrado por una llave foránea (SQLSTATE 23503 en PostgreSQL), se
 * captura y se traduce a un CatalogDeletionResult::blocked() en vez de
 * dejar pasar un 500.
 */
class CatalogDeletionService
{
    public static function deleteComponent(Component $component): CatalogDeletionResult
    {
        return DB::transaction(function () use ($component) {
            $locked = Component::whereKey($component->id)->lockForUpdate()->firstOrFail();
            $message = CatalogDeletionChecker::blockingMessageForComponent($locked);

            if ($message !== null) {
                return CatalogDeletionResult::blocked($message);
            }

            return self::performDelete($locked);
        });
    }

    public static function deleteSubcomponent(Subcomponent $subcomponent): CatalogDeletionResult
    {
        return DB::transaction(function () use ($subcomponent) {
            $locked = Subcomponent::whereKey($subcomponent->id)->lockForUpdate()->firstOrFail();
            $message = CatalogDeletionChecker::blockingMessageForSubcomponent($locked);

            if ($message !== null) {
                return CatalogDeletionResult::blocked($message);
            }

            return self::performDelete($locked);
        });
    }

    public static function deleteActivity(Activity $activity): CatalogDeletionResult
    {
        return DB::transaction(function () use ($activity) {
            $locked = Activity::whereKey($activity->id)->lockForUpdate()->firstOrFail();
            $message = CatalogDeletionChecker::blockingMessageForActivity($locked);

            if ($message !== null) {
                return CatalogDeletionResult::blocked($message);
            }

            return self::performDelete($locked);
        });
    }

    public static function deleteProgramUnit(ProgramUnit $programUnit): CatalogDeletionResult
    {
        return DB::transaction(function () use ($programUnit) {
            $locked = ProgramUnit::whereKey($programUnit->id)->lockForUpdate()->firstOrFail();
            $message = CatalogDeletionChecker::blockingMessageForProgramUnit($locked);

            if ($message !== null) {
                return CatalogDeletionResult::blocked($message);
            }

            return self::performDelete($locked);
        });
    }

    public static function deleteCrossCuttingCommitment(CrossCuttingCommitment $commitment): CatalogDeletionResult
    {
        return DB::transaction(function () use ($commitment) {
            $locked = CrossCuttingCommitment::whereKey($commitment->id)->lockForUpdate()->firstOrFail();
            $message = CatalogDeletionChecker::blockingMessageForCrossCuttingCommitment($locked);

            if ($message !== null) {
                return CatalogDeletionResult::blocked($message);
            }

            return self::performDelete($locked);
        });
    }

    /**
     * Nunca tiene dependencias que la bloqueen (ver
     * CatalogDeletionChecker::blockersForDeliverableTemplate()), pero sí
     * hay que limpiar el archivo físico de cada fila de referencia que
     * no comparta disk_path con otra — antes de borrar la plantilla:
     * deliverable_reference_files.deliverable_template_id es
     * cascadeOnDelete(), así que las FILAS se autoeliminan solas, pero
     * nadie borraría el archivo físico por nosotros.
     */
    public static function deleteDeliverableTemplate(DeliverableTemplate $template): CatalogDeletionResult
    {
        return DB::transaction(function () use ($template) {
            $locked = DeliverableTemplate::whereKey($template->id)->lockForUpdate()->firstOrFail();

            foreach ($locked->referenceFiles()->get() as $file) {
                if ($file->existsOnDisk() && ! $file->isDiskPathReferencedElsewhere()) {
                    $file->disk()->delete($file->disk_path);
                }
            }

            return self::performDelete($locked);
        });
    }

    /**
     * El código protegido (ver config/catalogs.php) se comprueba ANTES
     * de abrir la transacción — puro blindaje extra, para que un
     * periodo protegido ni siquiera llegue a lockForUpdate() — y otra
     * vez DENTRO, sobre la fila ya bloqueada, por la misma razón de
     * condición de carrera que el resto de este servicio: el código no
     * puede cambiar entre ambas comprobaciones (name es inmutable en la
     * práctica, pero el chequeo doble no cuesta nada y es el mismo
     * patrón que el resto de deleteX()).
     */
    public static function deleteAcademicPeriod(AcademicPeriod $period): CatalogDeletionResult
    {
        if (CatalogDeletionChecker::isProtectedAcademicPeriod($period)) {
            return CatalogDeletionResult::blocked(CatalogDeletionChecker::blockingMessageForAcademicPeriod($period));
        }

        return DB::transaction(function () use ($period) {
            $locked = AcademicPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
            $message = CatalogDeletionChecker::blockingMessageForAcademicPeriod($locked);

            if ($message !== null) {
                return CatalogDeletionResult::blocked($message);
            }

            return self::performDelete($locked);
        });
    }

    private static function performDelete(Model $model): CatalogDeletionResult
    {
        $snapshot = [
            'type' => class_basename($model),
            'id' => $model->getKey(),
            'name' => $model->getAttribute('name'),
        ];

        try {
            $model->delete();
        } catch (QueryException $e) {
            if ($e->getCode() !== '23503') {
                throw $e;
            }

            return CatalogDeletionResult::blocked('No se puede eliminar: todavía tiene registros relacionados.');
        }

        AuditLog::record('catalog_item_deleted', $model, $snapshot);

        return CatalogDeletionResult::success();
    }
}
