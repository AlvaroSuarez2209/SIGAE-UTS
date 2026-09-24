<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            // Catálogos/demo desactivados: la base debe quedar con solo el
            // usuario Administrador. Descomentar estas 7 líneas para
            // reactivar un seed completo de demostración.
            // CatalogSeeder::class,
            // AcademicPeriodSeeder::class,
            // TeacherAssignmentSeeder::class,
            // LeadershipSeeder::class,
            // CrossCuttingCommitmentSeeder::class,
            // DeliverableTemplateSeeder::class,
            // DeliverableSeeder::class,
            // EvidenceSeeder::class,
        ]);
    }
}
