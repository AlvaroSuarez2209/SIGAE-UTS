<?php

namespace Database\Factories;

use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\ProgramUnit;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherAssignment>
 */
class TeacherAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'academic_period_id' => AcademicPeriod::factory(),
            'activity_id' => Activity::factory(),
            'program_unit_id' => ProgramUnit::factory(),
            'assigned_hours' => fake()->randomElement([2, 3, 4, 5, 6, 8, 10]),
            'notes' => null,
        ];
    }
}
