<?php

namespace Database\Factories;

use App\Models\AcademicPeriod;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Leadership>
 */
class LeadershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'activity_id' => null,
            'program_unit_id' => ProgramUnit::factory(),
            'academic_period_id' => AcademicPeriod::factory(),
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ];
    }
}
