<?php

namespace Tests\Feature\Console;

use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Evidence\EvidenceOverdueNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', $role->value)->first());

        return $user;
    }

    public function test_notifies_the_teacher_and_coordination_when_an_evidence_becomes_overdue(): void
    {
        Notification::fake();

        $coordination = $this->userWithRole(RoleName::Coordination);
        $overdueDeliverable = Deliverable::factory()->create(['due_at' => now()->subDay()]);
        $evidence = Evidence::factory()->create([
            'deliverable_id' => $overdueDeliverable->id,
            'status' => EvidenceStatus::Pending,
        ]);

        $this->artisan('evidences:mark-overdue');

        Notification::assertSentTo($evidence->user, EvidenceOverdueNotification::class);
        Notification::assertSentTo($coordination, EvidenceOverdueNotification::class);
    }

    /**
     * La consulta del comando ya excluye las evidencias que una corrida
     * anterior dejó en Expired, así que ejecutarlo dos veces sobre la
     * misma evidencia no debe reenviar el aviso.
     */
    public function test_running_the_command_twice_does_not_send_duplicate_notifications(): void
    {
        $overdueDeliverable = Deliverable::factory()->create(['due_at' => now()->subDay()]);
        Evidence::factory()->create(['deliverable_id' => $overdueDeliverable->id, 'status' => EvidenceStatus::Pending]);

        $this->artisan('evidences:mark-overdue');

        Notification::fake();

        $this->artisan('evidences:mark-overdue');

        Notification::assertNothingSent();
    }
}
