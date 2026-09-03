<?php

namespace Tests\Feature\Evidence;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\RoleName;
use App\Livewire\Deliverables\DeliverableForm;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Deliverable;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EvidenceAutoCreationTest extends TestCase
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

    public function test_assigning_a_deliverable_creates_a_pending_evidence_for_each_recipient(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $teacherA = $this->userWithRole(RoleName::Teacher);
        $teacherB = $this->userWithRole(RoleName::Teacher);

        foreach ([$teacherA, $teacherB] as $teacher) {
            TeacherAssignment::factory()->create([
                'user_id' => $teacher->id,
                'academic_period_id' => $period->id,
                'activity_id' => $activity->id,
            ]);
        }

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->set([
                'academic_period_id' => $period->id,
                'scope_type' => 'activity',
                'activity_id' => $activity->id,
                'name' => 'Entregable con destinatarios',
                'periodicity_type' => 'single',
                'opens_at' => now()->toDateTimeString(),
                'due_at' => now()->addWeek()->toDateTimeString(),
                'allowed_evidence_types' => ['file'],
                'recipient_mode' => 'all',
            ])
            ->call('save');

        $deliverable = Deliverable::firstOrFail();

        foreach ([$teacherA, $teacherB] as $teacher) {
            $this->assertDatabaseHas('evidences', [
                'deliverable_id' => $deliverable->id,
                'user_id' => $teacher->id,
                'status' => EvidenceStatus::Pending->value,
            ]);
        }
    }

    public function test_dropping_a_recipient_does_not_delete_their_existing_evidence(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $teacherA = $this->userWithRole(RoleName::Teacher);
        $teacherB = $this->userWithRole(RoleName::Teacher);

        Livewire::actingAs($admin)->test(DeliverableForm::class)
            ->set([
                'academic_period_id' => $period->id,
                'scope_type' => 'activity',
                'activity_id' => $activity->id,
                'name' => 'Entregable',
                'periodicity_type' => 'single',
                'opens_at' => now()->toDateTimeString(),
                'due_at' => now()->addWeek()->toDateTimeString(),
                'allowed_evidence_types' => ['file'],
                'recipient_mode' => 'subset',
                'recipient_ids' => [$teacherA->id],
            ])
            ->call('save');

        $deliverable = Deliverable::firstOrFail();
        $this->assertDatabaseHas('evidences', ['deliverable_id' => $deliverable->id, 'user_id' => $teacherA->id]);

        // Coordinación cambia el destinatario de A a B.
        Livewire::actingAs($admin)->test(DeliverableForm::class, ['deliverable' => $deliverable])
            ->set('recipient_mode', 'subset')
            ->set('recipient_ids', [$teacherB->id])
            ->call('save');

        // La evidencia de A ya creada nunca se borra, aunque ya no sea destinatario.
        $this->assertDatabaseHas('evidences', ['deliverable_id' => $deliverable->id, 'user_id' => $teacherA->id]);
        $this->assertDatabaseMissing('deliverable_recipients', ['deliverable_id' => $deliverable->id, 'user_id' => $teacherA->id]);
        $this->assertDatabaseHas('evidences', ['deliverable_id' => $deliverable->id, 'user_id' => $teacherB->id]);
    }
}
