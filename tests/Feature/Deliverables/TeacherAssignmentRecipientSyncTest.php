<?php

namespace Tests\Feature\Deliverables;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Notifications\Evidence\EvidenceAssignedNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Punto 11 de la revisión de la directora: TeacherAssignmentObserver debe
 * mantener al día los destinatarios de un entregable publicado en modo
 * "todos los docentes de la actividad" (recipient_mode='all') cuando se
 * asigna un docente nuevo a esa actividad+periodo, sin que nadie tenga
 * que reabrir y volver a guardar el formulario del entregable.
 */
class TeacherAssignmentRecipientSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function teacher(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', RoleName::Teacher->value)->first());

        return $user;
    }

    private function assignTeacher(User $teacher, Activity $activity, AcademicPeriod $period): TeacherAssignment
    {
        return TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'activity_id' => $activity->id,
            'academic_period_id' => $period->id,
            'program_unit_id' => ProgramUnit::factory()->create()->id,
        ]);
    }

    public function test_assigning_a_new_teacher_adds_them_as_recipient_and_creates_a_pending_evidence(): void
    {
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'recipient_mode' => 'all',
        ]);
        $newTeacher = $this->teacher();

        Notification::fake();

        $this->assignTeacher($newTeacher, $activity, $period);

        $this->assertTrue($deliverable->recipients()->whereKey($newTeacher->id)->exists());
        $this->assertDatabaseHas('evidences', [
            'deliverable_id' => $deliverable->id,
            'user_id' => $newTeacher->id,
            'status' => EvidenceStatus::Pending->value,
        ]);

        Notification::assertSentTo($newTeacher, EvidenceAssignedNotification::class);
    }

    /**
     * Dos asignaciones para el mismo docente+actividad+periodo (distinto
     * program_unit_id, el único caso que el unique() de la tabla permite)
     * disparan el observer dos veces — no debe duplicar ni el destinatario
     * ni la evidencia, ni reenviar la notificación la segunda vez.
     */
    public function test_assigning_the_same_teacher_twice_does_not_duplicate_anything(): void
    {
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'recipient_mode' => 'all',
        ]);
        $teacher = $this->teacher();

        Notification::fake();

        $this->assignTeacher($teacher, $activity, $period);
        $this->assignTeacher($teacher, $activity, $period);

        $this->assertEquals(1, $deliverable->recipients()->whereKey($teacher->id)->count());
        $this->assertEquals(1, Evidence::where('deliverable_id', $deliverable->id)->where('user_id', $teacher->id)->count());

        Notification::assertSentToTimes($teacher, EvidenceAssignedNotification::class, 1);
    }

    public function test_a_closed_period_is_not_touched(): void
    {
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Closed]);
        $activity = Activity::factory()->create();
        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'recipient_mode' => 'all',
        ]);
        $teacher = $this->teacher();

        $this->assignTeacher($teacher, $activity, $period);

        $this->assertFalse($deliverable->recipients()->whereKey($teacher->id)->exists());
        $this->assertDatabaseMissing('evidences', ['deliverable_id' => $deliverable->id, 'user_id' => $teacher->id]);
    }

    public function test_an_archived_period_is_not_touched(): void
    {
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Archived]);
        $activity = Activity::factory()->create();
        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'recipient_mode' => 'all',
        ]);
        $teacher = $this->teacher();

        $this->assignTeacher($teacher, $activity, $period);

        $this->assertFalse($deliverable->recipients()->whereKey($teacher->id)->exists());
    }

    public function test_a_deliverable_in_specific_mode_is_not_touched(): void
    {
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'recipient_mode' => 'specific',
        ]);
        $teacher = $this->teacher();

        $this->assignTeacher($teacher, $activity, $period);

        $this->assertFalse($deliverable->recipients()->whereKey($teacher->id)->exists());
        $this->assertDatabaseMissing('evidences', ['deliverable_id' => $deliverable->id, 'user_id' => $teacher->id]);
    }

    public function test_a_draft_deliverable_is_not_touched(): void
    {
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $deliverable = Deliverable::factory()->draft()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'recipient_mode' => 'all',
        ]);
        $teacher = $this->teacher();

        $this->assignTeacher($teacher, $activity, $period);

        $this->assertFalse($deliverable->recipients()->whereKey($teacher->id)->exists());
        $this->assertDatabaseMissing('evidences', ['deliverable_id' => $deliverable->id, 'user_id' => $teacher->id]);
    }
}
