<?php

namespace Tests\Unit;

use App\Enums\EvidenceStatus;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\User;
use App\Services\ComplianceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ComplianceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function evidenceFor(User $user, Deliverable $deliverable, EvidenceStatus $status): Evidence
    {
        return Evidence::factory()->create([
            'user_id' => $user->id,
            'deliverable_id' => $deliverable->id,
            'status' => $status,
        ]);
    }

    public function test_returns_null_percentage_when_there_are_no_mandatory_deliverables(): void
    {
        $user = User::factory()->create();
        $optional = Deliverable::factory()->create(['is_mandatory' => false]);

        $result = ComplianceCalculator::forUser($user, new Collection([$optional]));

        $this->assertNull($result['percentage']);
    }

    public function test_equal_weighting_when_no_deliverable_has_a_weight(): void
    {
        $user = User::factory()->create();
        $deliverables = Deliverable::factory()->count(4)->create([
            'is_mandatory' => true,
            'weight_percentage' => null,
        ]);

        // Aprueba 1 de 4 -> 25%.
        $this->evidenceFor($user, $deliverables[0], EvidenceStatus::Approved);
        $this->evidenceFor($user, $deliverables[1], EvidenceStatus::Submitted);
        $this->evidenceFor($user, $deliverables[2], EvidenceStatus::Pending);
        $this->evidenceFor($user, $deliverables[3], EvidenceStatus::NeedsAdjustment);

        $result = ComplianceCalculator::forUser($user, $deliverables);

        $this->assertEquals(25.0, $result['percentage']);
        $this->assertEquals(1, $result['approved']);
        $this->assertEquals(4, $result['total']);
    }

    public function test_only_submitted_not_yet_approved_does_not_count_toward_compliance(): void
    {
        $user = User::factory()->create();
        $deliverable = Deliverable::factory()->create(['is_mandatory' => true, 'weight_percentage' => null]);
        $this->evidenceFor($user, $deliverable, EvidenceStatus::Submitted);

        $result = ComplianceCalculator::forUser($user, new Collection([$deliverable]));

        $this->assertEquals(0.0, $result['percentage']);
    }

    public function test_optional_deliverables_are_excluded_from_the_calculation(): void
    {
        $user = User::factory()->create();
        $mandatory = Deliverable::factory()->create(['is_mandatory' => true, 'weight_percentage' => null]);
        $optional = Deliverable::factory()->create(['is_mandatory' => false, 'weight_percentage' => null]);

        $this->evidenceFor($user, $mandatory, EvidenceStatus::Approved);
        // El opcional queda sin ninguna evidencia aprobada; no debería bajar el %.

        $result = ComplianceCalculator::forUser($user, new Collection([$mandatory, $optional]));

        $this->assertEquals(100.0, $result['percentage']);
        $this->assertEquals(1, $result['total']);
    }

    public function test_weighted_average_when_all_mandatory_deliverables_have_a_weight(): void
    {
        $user = User::factory()->create();
        $heavy = Deliverable::factory()->create(['is_mandatory' => true, 'weight_percentage' => 70]);
        $light = Deliverable::factory()->create(['is_mandatory' => true, 'weight_percentage' => 30]);

        $this->evidenceFor($user, $heavy, EvidenceStatus::Approved);
        $this->evidenceFor($user, $light, EvidenceStatus::Pending);

        $result = ComplianceCalculator::forUser($user, new Collection([$heavy, $light]));

        $this->assertEquals(70.0, $result['percentage']);
    }

    public function test_mixed_weighted_and_unweighted_falls_back_to_equal_weighting(): void
    {
        $user = User::factory()->create();
        $weighted = Deliverable::factory()->create(['is_mandatory' => true, 'weight_percentage' => 90]);
        $unweighted = Deliverable::factory()->create(['is_mandatory' => true, 'weight_percentage' => null]);

        $this->evidenceFor($user, $weighted, EvidenceStatus::Approved);
        $this->evidenceFor($user, $unweighted, EvidenceStatus::Pending);

        $result = ComplianceCalculator::forUser($user, new Collection([$weighted, $unweighted]));

        // Sin un esquema de pesos completo, cae a conteo simple: 1 de 2 = 50%,
        // no 90% (el peso individual del entregable no se usa a medias).
        $this->assertEquals(50.0, $result['percentage']);
    }
}
