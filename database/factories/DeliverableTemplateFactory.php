<?php

namespace Database\Factories;

use App\Enums\EvidenceType;
use App\Enums\PeriodicityType;
use App\Models\DeliverableTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliverableTemplate>
 */
class DeliverableTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->sentence(3),
            'description' => fake()->sentence(),
            'instructions' => fake()->paragraph(),
            'completion_criteria' => fake()->sentence(),
            'is_mandatory' => true,
            'periodicity_type' => PeriodicityType::Single,
            'allowed_evidence_types' => [EvidenceType::File->value],
            'allowed_file_types' => ['pdf'],
            'max_files' => 1,
            'max_file_size_mb' => 10,
            'weight_percentage' => null,
            'is_active' => true,
        ];
    }
}
