<?php

namespace Database\Seeders;

use App\Enums\EvidenceType;
use App\Enums\PeriodicityType;
use App\Models\DeliverableTemplate;
use Illuminate\Database\Seeder;

class DeliverableTemplateSeeder extends Seeder
{
    public function run(): void
    {
        DeliverableTemplate::firstOrCreate(
            ['name' => 'Informe de avance mensual'],
            [
                'description' => 'Reporte periódico de avance de la actividad.',
                'instructions' => 'Adjunta el informe en PDF describiendo los avances del mes.',
                'completion_criteria' => 'El informe debe cubrir todas las actividades ejecutadas en el periodo reportado.',
                'is_mandatory' => true,
                'periodicity_type' => PeriodicityType::Monthly,
                'allowed_evidence_types' => [EvidenceType::File->value, EvidenceType::Text->value],
                'allowed_file_types' => ['pdf', 'docx'],
                'max_files' => 1,
                'max_file_size_mb' => 10,
                'weight_percentage' => null,
                'is_active' => true,
            ]
        );
    }
}
