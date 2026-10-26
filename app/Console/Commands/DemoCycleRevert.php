<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Component;
use App\Models\CrossCuttingCommitment;
use App\Models\Deliverable;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\DemoCycleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deshace exactamente lo que crea DemoCycleSeeder — nunca toca al docente
 * real (config('seeding.demo_cycle_teacher_email')), solo lo que cuelga de
 * cualquier actividad, programa o compromiso transversal con el prefijo
 * "[DEMO] " y los líderes sintéticos (DemoCycleSeeder::DEMO_LEADER_EMAIL y,
 * desde el punto 10, DEMO_LEADER_B_EMAIL/DEMO_LEADER_C_EMAIL). Seguro de
 * correr aunque ya se haya revertido antes (reporta 0 en cada paso, sin
 * error).
 */
class DemoCycleRevert extends Command
{
    protected $signature = 'demo:cycle-revert {--force : Permite correr en producción}';

    protected $description = 'Elimina los datos de demostración creados por DemoCycleSeeder';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('demo:cycle-revert no corre en producción sin --force.');

            return self::FAILURE;
        }

        $activities = Activity::where('name', 'like', DemoCycleSeeder::PREFIX.'%')->get();
        $commitment = CrossCuttingCommitment::where('name', DemoCycleSeeder::PREFIX.'Compromiso transversal')->first();

        if ($activities->isEmpty() && ! $commitment) {
            $this->info('No hay datos de DemoCycleSeeder que revertir.');

            return self::SUCCESS;
        }

        $activityIds = $activities->pluck('id');

        $deliverables = Deliverable::where(function ($query) use ($activityIds, $commitment) {
            $query->whereIn('activity_id', $activityIds);

            if ($commitment) {
                $query->orWhere('cross_cutting_commitment_id', $commitment->id);
            }
        })->get();

        // Los archivos físicos no se van solos con el CASCADE de la base
        // (ese solo borra filas) — hay que borrarlos del disco antes de
        // que la fila que guarda su disk_path desaparezca.
        $fileCount = 0;
        foreach ($deliverables as $deliverable) {
            foreach ($deliverable->evidences as $evidence) {
                foreach ($evidence->versions as $version) {
                    foreach ($version->files as $file) {
                        Storage::disk('local')->delete($file->disk_path);
                        $fileCount++;
                    }
                }
            }
        }

        // deliverable_recipients, evidences, evidence_versions,
        // evidence_files (fila), reviews y observations ya tienen
        // cascadeOnDelete hasta aquí (ver migraciones) — basta con borrar
        // el Deliverable.
        $deliverableCount = $deliverables->count();
        $deliverables->each(fn (Deliverable $d) => $d->delete());

        $assignmentCount = TeacherAssignment::whereIn('activity_id', $activityIds)->delete();
        $leadershipCount = Leadership::whereIn('activity_id', $activityIds)->delete();

        // activity_id en deliverables es restrictOnDelete — solo se puede
        // borrar cada actividad una vez que ya no quede ningún entregable
        // apuntándole, que es justo lo que se acaba de hacer arriba. Mismo
        // motivo para esperar a borrar los ProgramUnit hasta después de
        // limpiar TeacherAssignment/Leadership.
        $activities->each(fn (Activity $a) => $a->delete());
        Component::where('name', 'like', DemoCycleSeeder::PREFIX.'%')->delete();
        $programUnitCount = ProgramUnit::where('name', 'like', DemoCycleSeeder::PREFIX.'%')->delete();

        $commitment?->delete();

        // Los líderes sintéticos se borran al final: reviews.reviewer_id es
        // restrictOnDelete, y esas filas solo terminaron de desaparecer
        // cuando se borraron los Deliverable arriba (cascada hacia abajo).
        $leadersDeleted = User::whereIn('email', [
            DemoCycleSeeder::DEMO_LEADER_EMAIL,
            DemoCycleSeeder::DEMO_LEADER_B_EMAIL,
            DemoCycleSeeder::DEMO_LEADER_C_EMAIL,
        ])->delete();

        $this->info("Revertido: {$deliverableCount} entregable(s), {$fileCount} archivo(s) físico(s), "
            ."{$assignmentCount} asignación(es) docente, {$leadershipCount} liderazgo(s), "
            ."{$activities->count()} actividad(es), {$programUnitCount} programa(s), "
            .($commitment ? 1 : 0)." compromiso(s) transversal(es), "
            ."{$leadersDeleted} líder(es) sintético(s).");

        return self::SUCCESS;
    }
}
