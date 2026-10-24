<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Component;
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
 * real (config('seeding.demo_cycle_teacher_email')), solo lo que cuelga
 * de la actividad "[DEMO] Ciclo de demostración" y el líder sintético
 * (DemoCycleSeeder::DEMO_LEADER_EMAIL). Seguro de correr aunque ya se
 * haya revertido antes (reporta 0 en cada paso, sin error).
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

        $activity = Activity::where('name', DemoCycleSeeder::PREFIX.'Ciclo de demostración')->first();

        if (! $activity) {
            $this->info('No hay datos de DemoCycleSeeder que revertir.');

            return self::SUCCESS;
        }

        $deliverables = Deliverable::where('activity_id', $activity->id)->get();

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
        Deliverable::where('activity_id', $activity->id)->each(fn (Deliverable $d) => $d->delete());

        $assignmentCount = TeacherAssignment::where('activity_id', $activity->id)->delete();
        $leadershipCount = Leadership::where('activity_id', $activity->id)->delete();

        // activity_id en deliverables es restrictOnDelete — solo se puede
        // borrar la actividad una vez que ya no quede ningún entregable
        // apuntándole, que es justo lo que se acaba de hacer arriba.
        $activity->delete();
        Component::where('name', DemoCycleSeeder::PREFIX.'Componente de prueba')->delete();
        ProgramUnit::where('name', DemoCycleSeeder::PREFIX.'Programa de prueba')->delete();

        // El líder sintético se borra al final: reviews.reviewer_id es
        // restrictOnDelete, y esas filas solo terminaron de desaparecer
        // cuando se borraron los Deliverable arriba (cascada hacia abajo).
        $leaderDeleted = User::where('email', DemoCycleSeeder::DEMO_LEADER_EMAIL)->delete();

        $this->info("Revertido: {$deliverableCount} entregable(s), {$fileCount} archivo(s) físico(s), "
            ."{$assignmentCount} asignación(es) docente, {$leadershipCount} liderazgo(s), "
            .($leaderDeleted ? 1 : 0)." líder sintético.");

        return self::SUCCESS;
    }
}
