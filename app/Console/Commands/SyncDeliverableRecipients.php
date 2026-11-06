<?php

namespace App\Console\Commands;

use App\Enums\AcademicPeriodStatus;
use App\Enums\DeliverableStatus;
use App\Models\Deliverable;
use App\Models\TeacherAssignment;
use Illuminate\Console\Command;

/**
 * Punto 11 de la revisión de la directora: los entregables publicados
 * ANTES de que existiera recipient_mode no tienen su ámbito guardado
 * (todos nacieron en 'specific' por el default de la migración), así que
 * TeacherAssignmentObserver nunca actúa sobre ellos. Este comando compara,
 * para cada entregable publicado de un periodo abierto y ligado a una
 * actividad (los compromisos transversales no se comparan contra
 * asignaciones — no tienen activity_id), sus destinatarios actuales
 * contra los docentes HOY asignados a esa actividad+periodo.
 *
 * Por defecto es de solo lectura (--dry-run implícito); --apply agrega
 * los destinatarios faltantes (nunca quita ninguno — la desactivación o
 * el borrado de asignaciones queda fuera de este lote) y marca
 * recipient_mode='all' únicamente cuando el entregable no tiene ningún
 * destinatario "sobrante" (alguien que ya no está asignado) — si lo
 * tiene, no hay forma confiable de saber si nació en modo "todos" o
 * "específico" a partir solo del estado actual, así que se deja como
 * está en vez de adivinar.
 */
class SyncDeliverableRecipients extends Command
{
    protected $signature = 'deliverables:sync-recipients {--apply : Aplica los cambios; sin esta opción solo se muestran las diferencias}';

    protected $description = 'Compara los destinatarios de entregables publicados contra las asignaciones docentes actuales y, con --apply, corrige las diferencias';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $deliverables = Deliverable::query()
            ->where('status', DeliverableStatus::Published)
            ->whereNotNull('activity_id')
            ->whereHas('academicPeriod', fn ($query) => $query->whereIn('status', [
                AcademicPeriodStatus::Planning->value,
                AcademicPeriodStatus::Active->value,
            ]))
            ->with(['activity', 'academicPeriod', 'recipients'])
            ->get();

        $rows = [];
        $touched = 0;

        foreach ($deliverables as $deliverable) {
            $assignedUserIds = TeacherAssignment::query()
                ->where('activity_id', $deliverable->activity_id)
                ->where('academic_period_id', $deliverable->academic_period_id)
                ->pluck('user_id')
                ->unique();

            $recipientUserIds = $deliverable->recipients->pluck('id');

            $missing = $assignedUserIds->diff($recipientUserIds)->values();
            $extra = $recipientUserIds->diff($assignedUserIds)->values();

            $wouldMarkAll = $extra->isEmpty() && $deliverable->recipient_mode !== 'all';

            if ($missing->isEmpty() && ! $wouldMarkAll) {
                continue;
            }

            $rows[] = [
                $deliverable->id,
                $deliverable->name,
                $deliverable->activity->name,
                $deliverable->academicPeriod->name,
                $missing->count(),
                $extra->count(),
                $wouldMarkAll ? 'sí' : 'no',
            ];

            if ($apply) {
                if ($missing->isNotEmpty()) {
                    $deliverable->recipients()->syncWithoutDetaching($missing->all());
                    $deliverable->ensureEvidencesForRecipients($missing->all());
                }

                if ($wouldMarkAll) {
                    $deliverable->update(['recipient_mode' => 'all']);
                }

                $touched++;
            }
        }

        if (empty($rows)) {
            $this->info('No se encontraron diferencias: todos los entregables publicados de periodos abiertos ya coinciden con las asignaciones actuales.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Entregable', 'Actividad', 'Periodo', 'Faltantes', 'Sobrantes', '¿Marcaría "all"?'],
            $rows
        );

        $this->info($apply
            ? "Aplicado: {$touched} entregable(s) actualizado(s)."
            : count($rows).' entregable(s) con diferencias. Ejecuta con --apply para corregirlas (por defecto solo se muestran, sin escribir nada).');

        return self::SUCCESS;
    }
}
