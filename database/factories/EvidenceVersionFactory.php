<?php

namespace Database\Factories;

use App\Models\Evidence;
use App\Models\EvidenceVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvidenceVersion>
 */
class EvidenceVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'evidence_id' => Evidence::factory(),
            'version_number' => 1,
            'description' => fake()->paragraph(),
            'submitted_at' => null,
            'created_by' => User::factory(),
        ];
    }
}
