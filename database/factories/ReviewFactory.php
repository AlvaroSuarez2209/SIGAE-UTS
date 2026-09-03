<?php

namespace Database\Factories;

use App\Enums\ReviewDecision;
use App\Models\EvidenceVersion;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'evidence_version_id' => EvidenceVersion::factory(),
            'reviewer_id' => User::factory(),
            'decision' => ReviewDecision::Approved,
            'decided_at' => now(),
        ];
    }
}
