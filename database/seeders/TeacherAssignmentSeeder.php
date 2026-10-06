<?php

namespace Database\Seeders;

use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\ProgramUnit;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\Concerns\RefusesInProduction;
use Illuminate\Database\Seeder;

class TeacherAssignmentSeeder extends Seeder
{
    use RefusesInProduction;

    public function run(): void
    {
        if ($this->abortIfProduction()) {
            return;
        }

        // Desactivado: dependía de las cuentas de demostración
        // (docente1@sigae.local, docente2@sigae.local, lider1@sigae.local)
        // que UserSeeder ya no crea — solo siembra un Administrador. Vuelve
        // a activarse (junto con LeadershipSeeder y EvidenceSeeder, que
        // dependen de las mismas cuentas) si se necesita un seed completo
        // de demostración.
        /*
        $period = AcademicPeriod::where('name', '2026-1')->first();
        $programUnit = ProgramUnit::orderBy('id')->first();

        $docente1 = User::where('email', 'docente1@sigae.local')->first();
        $docente2 = User::where('email', 'docente2@sigae.local')->first();
        $lider1 = User::where('email', 'lider1@sigae.local')->first();

        $classes = Activity::where('name', 'Clases teóricas')->first();
        $tutoring = Activity::where('name', 'Tutorías a estudiantes')->first();
        $thesisDirection = Activity::where('name', 'Dirección de trabajos de grado')->first();
        $research = Activity::where('name', 'Participación en grupo de investigación')->first();

        // Escenario obligatorio de demostración: actividad con 5 horas, cuya
        // cantidad de entregables (definida en el módulo 5) será distinta de 5.
        TeacherAssignment::firstOrCreate([
            'user_id' => $docente1->id,
            'academic_period_id' => $period->id,
            'activity_id' => $thesisDirection->id,
            'program_unit_id' => $programUnit->id,
        ], [
            'assigned_hours' => 5,
        ]);

        TeacherAssignment::firstOrCreate([
            'user_id' => $docente1->id,
            'academic_period_id' => $period->id,
            'activity_id' => $classes->id,
            'program_unit_id' => $programUnit->id,
        ], [
            'assigned_hours' => 16,
        ]);

        TeacherAssignment::firstOrCreate([
            'user_id' => $docente2->id,
            'academic_period_id' => $period->id,
            'activity_id' => $tutoring->id,
            'program_unit_id' => $programUnit->id,
        ], [
            'assigned_hours' => 4,
        ]);

        TeacherAssignment::firstOrCreate([
            'user_id' => $docente2->id,
            'academic_period_id' => $period->id,
            'activity_id' => $research->id,
            'program_unit_id' => $programUnit->id,
        ], [
            'assigned_hours' => 8,
        ]);

        TeacherAssignment::firstOrCreate([
            'user_id' => $lider1->id,
            'academic_period_id' => $period->id,
            'activity_id' => $classes->id,
            'program_unit_id' => $programUnit->id,
        ], [
            'assigned_hours' => 12,
        ]);
        */
    }
}
