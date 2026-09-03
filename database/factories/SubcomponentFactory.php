<?php

namespace Database\Factories;

use App\Models\Component;
use App\Models\Subcomponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subcomponent>
 */
class SubcomponentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'component_id' => Component::factory(),
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
        ];
    }
}
