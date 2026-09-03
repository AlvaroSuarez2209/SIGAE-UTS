<?php

namespace Database\Factories;

use App\Models\ProgramUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramUnit>
 */
class ProgramUnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'is_active' => true,
        ];
    }
}
