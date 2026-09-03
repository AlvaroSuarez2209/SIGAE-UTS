<?php

namespace Database\Factories;

use App\Enums\EvidenceStatus;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evidence>
 */
class EvidenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'deliverable_id' => Deliverable::factory(),
            'user_id' => User::factory(),
            'status' => EvidenceStatus::Pending,
            'current_version_id' => null,
        ];
    }
}
