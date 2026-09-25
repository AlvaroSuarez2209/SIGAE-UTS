<?php

namespace Tests\Feature\Evidence;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\EvidenceType;
use App\Enums\RoleName;
use App\Livewire\Evidence\EvidenceWorkspace;
use App\Livewire\Reviews\ReviewShow;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\CrossCuttingCommitment;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Notifications\Evidence\EvidenceApprovalConfirmedNotification;
use App\Notifications\Evidence\EvidenceApprovedNotification;
use App\Notifications\Evidence\EvidenceExemptedNotification;
use App\Notifications\Evidence\EvidenceExemptionConfirmedNotification;
use App\Notifications\Evidence\EvidenceNeedsAdjustmentNotification;
use App\Notifications\Evidence\EvidencePendingReviewNotification;
use App\Notifications\Evidence\EvidenceReturnConfirmedNotification;
use App\Notifications\Evidence\EvidenceSubmissionConfirmedNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Verifica que las notificaciones por correo se disparen exactamente en
 * los puntos donde ya ocurre cada transición de estado (nunca desde un
 * observer genérico), y solo en los 5 escenarios pedidos — en particular,
 * que reopen() (que también deja el estado en NeedsAdjustment) y
 * removeExemption() (fuera del alcance pedido) no disparen nada.
 */
class EvidenceNotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', $role->value)->first());

        return $user;
    }

    /**
     * Docente con una asignación en una actividad/programa/periodo, y
     * (opcionalmente) un líder cuyo liderazgo cubre ese mismo ámbito.
     */
    private function teacherWithAssignment(bool $withLeaderInScope = true): array
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $programUnit = ProgramUnit::factory()->create();

        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $programUnit->id,
        ]);

        $leader = null;

        if ($withLeaderInScope) {
            $leader = $this->userWithRole(RoleName::Leader);

            Leadership::factory()->create([
                'user_id' => $leader->id,
                'academic_period_id' => $period->id,
                'activity_id' => null,
                'program_unit_id' => $programUnit->id,
                'starts_at' => now()->subDay(),
                'ends_at' => null,
            ]);
        }

        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'allowed_evidence_types' => [EvidenceType::Text->value],
        ]);

        $evidence = Evidence::factory()->create([
            'user_id' => $teacher->id,
            'deliverable_id' => $deliverable->id,
            'status' => EvidenceStatus::Pending,
        ]);

        return [$evidence, $teacher, $leader];
    }

    public function test_submitting_evidence_notifies_the_teacher_and_the_leader_in_scope(): void
    {
        Notification::fake();

        [$evidence, $teacher, $leader] = $this->teacherWithAssignment();

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('description', 'Contenido de la evidencia')
            ->call('submit')
            ->assertHasNoErrors();

        Notification::assertSentTo($teacher, EvidenceSubmissionConfirmedNotification::class);
        Notification::assertSentTo($leader, EvidencePendingReviewNotification::class);
    }

    public function test_submitting_evidence_with_no_leader_in_scope_falls_back_to_coordination(): void
    {
        Notification::fake();

        [$evidence, $teacher] = $this->teacherWithAssignment(withLeaderInScope: false);
        $coordination = $this->userWithRole(RoleName::Coordination);

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('description', 'Contenido de la evidencia')
            ->call('submit')
            ->assertHasNoErrors();

        Notification::assertSentTo($coordination, EvidencePendingReviewNotification::class);
    }

    public function test_submitting_cross_cutting_evidence_notifies_coordination_directly(): void
    {
        Notification::fake();

        $teacher = $this->userWithRole(RoleName::Teacher);
        $coordination = $this->userWithRole(RoleName::Coordination);
        $commitment = CrossCuttingCommitment::factory()->create();
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        $deliverable = Deliverable::factory()->crossCutting()->create([
            'academic_period_id' => $period->id,
            'cross_cutting_commitment_id' => $commitment->id,
            'allowed_evidence_types' => [EvidenceType::Text->value],
        ]);

        $evidence = Evidence::factory()->create([
            'user_id' => $teacher->id,
            'deliverable_id' => $deliverable->id,
            'status' => EvidenceStatus::Pending,
        ]);

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('description', 'Contenido de la evidencia')
            ->call('submit')
            ->assertHasNoErrors();

        Notification::assertSentTo($coordination, EvidencePendingReviewNotification::class);
    }

    private function submittedEvidence(): array
    {
        [$evidence, $teacher, $leader] = $this->teacherWithAssignment();

        $version = $evidence->startOrGetDraftVersion($teacher);
        $version->update(['description' => 'Contenido de prueba']);
        $evidence->submitCurrentVersion();

        return [$evidence->fresh(), $teacher, $leader];
    }

    public function test_approving_notifies_the_reviewer_and_the_teacher(): void
    {
        [$evidence, $teacher, $leader] = $this->submittedEvidence();

        Notification::fake();

        Livewire::actingAs($leader)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->call('approve')
            ->assertHasNoErrors();

        Notification::assertSentTo($leader, EvidenceApprovalConfirmedNotification::class);
        Notification::assertSentTo($teacher, EvidenceApprovedNotification::class);
    }

    public function test_returning_for_adjustment_notifies_the_reviewer_and_the_teacher(): void
    {
        [$evidence, $teacher, $leader] = $this->submittedEvidence();

        Notification::fake();

        Livewire::actingAs($leader)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->set('observation', 'Falta el archivo de soporte.')
            ->call('returnForAdjustment')
            ->assertHasNoErrors();

        Notification::assertSentTo($leader, EvidenceReturnConfirmedNotification::class);
        Notification::assertSentTo($teacher, EvidenceNeedsAdjustmentNotification::class);
    }

    /**
     * reopen() también deja el estado en NeedsAdjustment (mismo valor que
     * produce returnForAdjustment()), pero es una acción excepcional de
     * Administrador sin una Review asociada — no es uno de los 5
     * escenarios pedidos, así que no debe disparar ninguna notificación.
     */
    public function test_reopening_an_approved_evidence_does_not_send_any_notification(): void
    {
        [$evidence, , $leader] = $this->submittedEvidence();

        Livewire::actingAs($leader)->test(ReviewShow::class, ['evidence' => $evidence])->call('approve');
        $evidence->refresh();

        $admin = $this->userWithRole(RoleName::Administrator);

        Notification::fake();

        Livewire::actingAs($admin)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->call('reopen');

        $this->assertEquals(EvidenceStatus::NeedsAdjustment, $evidence->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_marking_exempt_notifies_the_actor_and_the_teacher(): void
    {
        $evidence = Evidence::factory()->create(['status' => EvidenceStatus::Pending]);
        $admin = $this->userWithRole(RoleName::Administrator);

        Notification::fake();

        Livewire::actingAs($admin)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('exemptionJustification', 'Docente en licencia.')
            ->call('markExempt')
            ->assertHasNoErrors();

        Notification::assertSentTo($admin, EvidenceExemptionConfirmedNotification::class);
        Notification::assertSentTo($evidence->user, EvidenceExemptedNotification::class);
    }

    /**
     * Quitar una exención no está entre los 5 escenarios pedidos — solo
     * marcarla lo está.
     */
    public function test_removing_an_exemption_does_not_send_any_notification(): void
    {
        $evidence = Evidence::factory()->create(['status' => EvidenceStatus::Exempt]);
        $admin = $this->userWithRole(RoleName::Administrator);

        Notification::fake();

        Livewire::actingAs($admin)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->call('removeExemption')
            ->assertHasNoErrors();

        Notification::assertNothingSent();
    }
}
