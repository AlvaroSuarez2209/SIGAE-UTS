<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Component;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'component_id' => Component::factory(),
            'subcomponent_id' => null,
            'name' => fake()->unique()->sentence(3),
            'is_active' => true,
        ];
    }
}
