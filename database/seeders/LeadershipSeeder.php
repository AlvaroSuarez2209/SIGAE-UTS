<?php

namespace Database\Seeders;

use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\User;
use Database\Seeders\Concerns\RefusesInProduction;
use Illuminate\Database\Seeder;

class LeadershipSeeder extends Seeder
{
    use RefusesInProduction;

    public function run(): void
    {
        if ($this->abortIfProduction()) {
            return;
        }

        // Desactivado: dependía de la cuenta de demostración
        // (lider1@sigae.local) que UserSeeder ya no crea — solo siembra un
        // Administrador. Vuelve a activarse (junto con
        // TeacherAssignmentSeeder y EvidenceSeeder, que dependen de las
        // mismas cuentas) si se necesita un seed completo de demostración.
        /*
        $period = AcademicPeriod::where('name', '2026-1')->first();
        $programUnit = ProgramUnit::orderBy('id')->first();
        $lider1 = User::where('email', 'lider1@sigae.local')->first();
        $thesisDirection = Activity::where('name', 'Dirección de trabajos de grado')->first();

        // Líder sobre todo el programa (ámbito amplio, sin actividad específica).
        Leadership::firstOrCreate([
            'user_id' => $lider1->id,
            'activity_id' => null,
            'program_unit_id' => $programUnit->id,
            'academic_period_id' => $period->id,
        ], [
            'starts_at' => $period->start_date,
            'ends_at' => null,
        ]);

        // Ejemplo de ámbito acotado: mismo líder, pero solo para una actividad
        // puntual (demuestra que el alcance puede restringirse).
        Leadership::firstOrCreate([
            'user_id' => $lider1->id,
            'activity_id' => $thesisDirection->id,
            'program_unit_id' => $programUnit->id,
            'academic_period_id' => $period->id,
        ], [
            'starts_at' => $period->start_date,
            'ends_at' => null,
        ]);
        */
    }
}
