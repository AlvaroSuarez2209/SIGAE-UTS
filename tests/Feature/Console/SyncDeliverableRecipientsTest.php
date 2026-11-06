<?php

namespace Tests\Feature\Console;

use App\Enums\AcademicPeriodStatus;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Deliverable;
use App\Models\TeacherAssignment;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Punto 11 de la revisión de la directora: deliverables:sync-recipients
 * es de solo lectura por defecto; solo --apply escribe.
 */
class SyncDeliverableRecipientsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_dry_run_reports_missing_recipients_without_writing_anything(): void
    {
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'recipient_mode' => 'specific',
        ]);
        $assignment = TeacherAssignment::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
        ]);

        $this->artisan('deliverables:sync-recipients')->assertExitCode(0);

        $this->assertFalse($deliverable->recipients()->whereKey($assignment->user_id)->exists());
        $this->assertDatabaseMissing('evidences', ['deliverable_id' => $deliverable->id, 'user_id' => $assignment->user_id]);
        $this->assertEquals('specific', $deliverable->fresh()->recipient_mode);
    }

    public function test_apply_adds_the_missing_recipient_and_marks_all_when_there_is_no_leftover(): void
    {
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'recipient_mode' => 'specific',
        ]);
        $assignment = TeacherAssignment::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
        ]);

        $this->artisan('deliverables:sync-recipients --apply')->assertExitCode(0);

        $this->assertTrue($deliverable->recipients()->whereKey($assignment->user_id)->exists());
        $this->assertDatabaseHas('evidences', ['deliverable_id' => $deliverable->id, 'user_id' => $assignment->user_id]);
        $this->assertEquals('all', $deliverable->fresh()->recipient_mode);
    }

    /**
     * Si el entregable tiene un destinatario "sobrante" (ya no asignado a
     * la actividad), no hay forma confiable de saber si nació en modo
     * "todos" o "específico" — se agregan los faltantes, pero NO se marca
     * recipient_mode='all'.
     */
    public function test_apply_never_marks_all_when_there_is_a_leftover_recipient(): void
    {
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'recipient_mode' => 'specific',
        ]);
        $assignedTeacher = TeacherAssignment::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
        ])->user;
        $leftoverTeacher = \App\Models\User::factory()->create();
        $deliverable->recipients()->attach($leftoverTeacher->id);

        $this->artisan('deliverables:sync-recipients --apply')->assertExitCode(0);

        $this->assertTrue($deliverable->recipients()->whereKey($assignedTeacher->id)->exists());
        $this->assertTrue($deliverable->recipients()->whereKey($leftoverTeacher->id)->exists());
        $this->assertEquals('specific', $deliverable->fresh()->recipient_mode);
    }

    public function test_a_closed_period_is_never_touched_even_with_apply(): void
    {
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Closed]);
        $activity = Activity::factory()->create();
        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'recipient_mode' => 'specific',
        ]);
        $assignment = TeacherAssignment::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
        ]);

        $this->artisan('deliverables:sync-recipients --apply')->assertExitCode(0);

        $this->assertFalse($deliverable->recipients()->whereKey($assignment->user_id)->exists());
        $this->assertEquals('specific', $deliverable->fresh()->recipient_mode);
    }

    public function test_a_cross_cutting_deliverable_is_never_compared(): void
    {
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $deliverable = Deliverable::factory()->crossCutting()->create([
            'academic_period_id' => $period->id,
            'recipient_mode' => 'specific',
        ]);

        $this->artisan('deliverables:sync-recipients --apply')->assertExitCode(0);

        $this->assertEquals('specific', $deliverable->fresh()->recipient_mode);
    }
}
