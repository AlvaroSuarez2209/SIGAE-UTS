<?php

namespace Database\Factories;

use App\Enums\AcademicPeriodStatus;
use App\Models\AcademicPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicPeriod>
 */
class AcademicPeriodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->bothify('20##-#'),
            'status' => AcademicPeriodStatus::Planning,
            'start_date' => now()->startOfYear(),
            'end_date' => now()->startOfYear()->addMonths(5),
        ];
    }
}
