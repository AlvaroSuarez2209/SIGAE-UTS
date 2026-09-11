<?php

namespace Tests\Feature\Console;

use App\Enums\EvidenceStatus;
use App\Models\AuditLog;
use App\Models\Deliverable;
use App\Models\Evidence;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkOverdueEvidencesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_marks_pending_and_draft_evidences_past_due_as_expired(): void
    {
        $overdueDeliverable = Deliverable::factory()->create(['due_at' => now()->subDay()]);
        $pending = Evidence::factory()->create(['deliverable_id' => $overdueDeliverable->id, 'status' => EvidenceStatus::Pending]);
        $draft = Evidence::factory()->create(['deliverable_id' => $overdueDeliverable->id, 'status' => EvidenceStatus::Draft]);

        $this->artisan('evidences:mark-overdue')->assertExitCode(0);

        $this->assertEquals(EvidenceStatus::Expired, $pending->fresh()->status);
        $this->assertEquals(EvidenceStatus::Expired, $draft->fresh()->status);
    }

    public function test_does_not_touch_evidences_not_yet_due(): void
    {
        $futureDeliverable = Deliverable::factory()->create(['due_at' => now()->addWeek()]);
        $pending = Evidence::factory()->create(['deliverable_id' => $futureDeliverable->id, 'status' => EvidenceStatus::Pending]);

        $this->artisan('evidences:mark-overdue');

        $this->assertEquals(EvidenceStatus::Pending, $pending->fresh()->status);
    }

    public function test_does_not_touch_evidences_already_submitted_approved_or_exempt(): void
    {
        $overdueDeliverable = Deliverable::factory()->create(['due_at' => now()->subDay()]);
        $submitted = Evidence::factory()->create(['deliverable_id' => $overdueDeliverable->id, 'status' => EvidenceStatus::Submitted]);
        $approved = Evidence::factory()->create(['deliverable_id' => $overdueDeliverable->id, 'status' => EvidenceStatus::Approved]);
        $exempt = Evidence::factory()->create(['deliverable_id' => $overdueDeliverable->id, 'status' => EvidenceStatus::Exempt]);
        $needsAdjustment = Evidence::factory()->create(['deliverable_id' => $overdueDeliverable->id, 'status' => EvidenceStatus::NeedsAdjustment]);

        $this->artisan('evidences:mark-overdue');

        $this->assertEquals(EvidenceStatus::Submitted, $submitted->fresh()->status);
        $this->assertEquals(EvidenceStatus::Approved, $approved->fresh()->status);
        $this->assertEquals(EvidenceStatus::Exempt, $exempt->fresh()->status);
        $this->assertEquals(EvidenceStatus::NeedsAdjustment, $needsAdjustment->fresh()->status);
    }

    public function test_records_a_system_audit_log_entry_with_no_user(): void
    {
        $overdueDeliverable = Deliverable::factory()->create(['due_at' => now()->subDay()]);
        $evidence = Evidence::factory()->create(['deliverable_id' => $overdueDeliverable->id, 'status' => EvidenceStatus::Pending]);

        $this->artisan('evidences:mark-overdue');

        $log = AuditLog::where('action', 'evidence_marked_overdue')->where('auditable_id', $evidence->id)->first();

        $this->assertNotNull($log);
        $this->assertNull($log->user_id);
        $this->assertEquals('pending', $log->metadata['previous_status']);
    }
}
