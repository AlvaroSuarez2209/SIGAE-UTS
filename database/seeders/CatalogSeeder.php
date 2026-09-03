<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Component;
use App\Models\ProgramUnit;
use App\Models\Subcomponent;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Facultad de Ciencias Naturales e Ingeniería',
            'Facultad de Ciencias Socioeconómicas y Empresariales',
            'Facultad de Ciencias de la Educación',
        ] as $name) {
            ProgramUnit::firstOrCreate(['name' => $name]);
        }

        $components = [];
        foreach (['Docencia', 'Investigación', 'Extensión', 'Otras actividades'] as $name) {
            $components[$name] = Component::firstOrCreate(['name' => $name]);
        }

        $subcomponents = [];
        foreach (['Procesos OACA', 'Procesos ODA', 'Comités', 'Otras'] as $name) {
            $subcomponents[$name] = Subcomponent::firstOrCreate([
                'component_id' => $components['Otras actividades']->id,
                'name' => $name,
            ]);
        }

        $activities = [
            'Docencia' => [
                'Clases teóricas',
                'Tutorías a estudiantes',
                'Dirección de trabajos de grado',
                'Comité de trabajo de grado',
            ],
            'Investigación' => [
                'Participación en grupo de investigación',
                'Publicación de artículo científico',
            ],
            'Extensión' => [
                'Proyecto de extensión',
                'Capacitación a la comunidad',
            ],
        ];

        foreach ($activities as $componentName => $names) {
            foreach ($names as $name) {
                Activity::firstOrCreate([
                    'component_id' => $components[$componentName]->id,
                    'name' => $name,
                ]);
            }
        }

        $otherActivities = [
            'Procesos OACA' => 'Autoevaluación de programa',
            'Procesos ODA' => 'Apoyo a procesos ODA',
            'Comités' => 'Comité curricular',
            'Otras' => 'Actividad administrativa',
        ];

        foreach ($otherActivities as $subcomponentName => $activityName) {
            Activity::firstOrCreate([
                'component_id' => $components['Otras actividades']->id,
                'subcomponent_id' => $subcomponents[$subcomponentName]->id,
                'name' => $activityName,
            ]);
        }
    }
}
