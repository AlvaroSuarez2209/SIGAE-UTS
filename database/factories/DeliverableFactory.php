<?php

namespace Database\Factories;

use App\Enums\EvidenceType;
use App\Enums\PeriodicityType;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\CrossCuttingCommitment;
use App\Models\Deliverable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deliverable>
 */
class DeliverableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'academic_period_id' => AcademicPeriod::factory(),
            'activity_id' => Activity::factory(),
            'cross_cutting_commitment_id' => null,
            'name' => fake()->unique()->sentence(3),
            'description' => fake()->sentence(),
            'instructions' => fake()->paragraph(),
            'completion_criteria' => fake()->sentence(),
            'is_mandatory' => true,
            'periodicity_type' => PeriodicityType::Single,
            'opens_at' => now(),
            'due_at' => now()->addWeeks(2),
            'closes_at' => null,
            'allowed_evidence_types' => [EvidenceType::File->value],
            'allowed_file_types' => ['pdf'],
            'max_files' => 1,
            'max_file_size_mb' => 10,
            'weight_percentage' => null,
        ];
    }

    public function crossCutting(): static
    {
        return $this->state(fn () => [
            'activity_id' => null,
            'cross_cutting_commitment_id' => CrossCuttingCommitment::factory(),
        ]);
    }
}
