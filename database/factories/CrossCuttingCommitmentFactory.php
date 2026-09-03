<?php

namespace Database\Factories;

use App\Models\CrossCuttingCommitment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrossCuttingCommitment>
 */
class CrossCuttingCommitmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'is_active' => true,
        ];
    }
}
