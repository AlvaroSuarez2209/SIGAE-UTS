<?php

namespace Tests\Feature\Deliverables;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceType;
use App\Enums\RoleName;
use App\Livewire\Deliverables\DeliverableForm;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\CrossCuttingCommitment;
use App\Models\Deliverable;
use App\Models\DeliverableTemplate;
use App\Models\Evidence;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeliverableTest extends TestCase
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

    private function teacher(): User
    {
        return $this->userWithRole(RoleName::Teacher);
    }

    private function baseFormState(): array
    {
        return [
            'name' => 'Entregable de prueba',
            'periodicity_type' => 'single',
            'opens_at' => now()->toDateTimeString(),
            'due_at' => now()->addWeek()->toDateTimeString(),
            'allowed_evidence_types' => [EvidenceType::File->value],
        ];
    }

    public function test_teacher_cannot_manage_deliverables(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($teacher)->get('/deliverables')->assertForbidden();
    }

    public function test_leader_cannot_manage_deliverable_templates(): void
    {
        $leader = $this->userWithRole(RoleName::Leader);

        $this->actingAs($leader)->get('/deliverable-templates')->assertForbidden();
    }

    public function test_can_create_a_deliverable_scoped_to_an_activity(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->set('academic_period_id', $period->id)
            ->set('scope_type', 'activity')
            ->set('activity_id', $activity->id)
            ->set($this->baseFormState())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('deliverables', [
            'name' => 'Entregable de prueba',
            'activity_id' => $activity->id,
            'cross_cutting_commitment_id' => null,
        ]);
    }

    public function test_can_create_a_cross_cutting_deliverable_without_an_activity(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $commitment = CrossCuttingCommitment::factory()->create();

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->set('academic_period_id', $period->id)
            ->set('scope_type', 'cross_cutting')
            ->set('cross_cutting_commitment_id', $commitment->id)
            ->set($this->baseFormState())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('deliverables', [
            'name' => 'Entregable de prueba',
            'activity_id' => null,
            'cross_cutting_commitment_id' => $commitment->id,
        ]);
    }

    public function test_cannot_leave_the_activity_unselected_when_scope_is_activity(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->set('academic_period_id', $period->id)
            ->set('scope_type', 'activity')
            ->set($this->baseFormState())
            ->call('save')
            ->assertHasErrors('activity_id');

        $this->assertDatabaseCount('deliverables', 0);
    }

    public function test_database_rejects_a_deliverable_with_both_an_activity_and_a_commitment(): void
    {
        // Defensa en profundidad: el CHECK constraint de la base de datos
        // debe rechazar esto incluso si algo se salta la validación de Livewire.
        $this->expectException(QueryException::class);

        Deliverable::factory()->create([
            'activity_id' => Activity::factory(),
            'cross_cutting_commitment_id' => CrossCuttingCommitment::factory(),
        ]);
    }

    public function test_database_rejects_a_deliverable_with_neither_scope(): void
    {
        $this->expectException(QueryException::class);

        Deliverable::factory()->create([
            'activity_id' => null,
            'cross_cutting_commitment_id' => null,
        ]);
    }

    public function test_deliverable_count_is_independent_from_assigned_hours(): void
    {
        // Regla de negocio central: una actividad de 5 horas puede tener
        // cualquier cantidad de entregables, nunca 5 "porque sí".
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();

        TeacherAssignment::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'assigned_hours' => 5,
        ]);

        foreach (['Propuesta', 'Informe final'] as $name) {
            Livewire::actingAs($admin)
                ->test(DeliverableForm::class)
                ->set('academic_period_id', $period->id)
                ->set('scope_type', 'activity')
                ->set('activity_id', $activity->id)
                ->set($this->baseFormState())
                ->set('name', $name)
                ->call('save');
        }

        $this->assertDatabaseCount('deliverables', 2);
        $this->assertNotEquals(5, Deliverable::where('activity_id', $activity->id)->count());
    }

    public function test_recipient_mode_all_includes_every_teacher_currently_assigned_to_the_activity(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $teacherA = $this->teacher();
        $teacherB = $this->teacher();

        foreach ([$teacherA, $teacherB] as $teacher) {
            TeacherAssignment::factory()->create([
                'user_id' => $teacher->id,
                'academic_period_id' => $period->id,
                'activity_id' => $activity->id,
            ]);
        }

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->set('academic_period_id', $period->id)
            ->set('scope_type', 'activity')
            ->set('activity_id', $activity->id)
            ->set($this->baseFormState())
            ->set('recipient_mode', 'all')
            ->call('save');

        $deliverable = Deliverable::firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$teacherA->id, $teacherB->id],
            $deliverable->recipients->pluck('id')->all()
        );
    }

    public function test_recipient_mode_subset_only_includes_selected_teachers(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $teacherA = $this->teacher();
        $teacherB = $this->teacher();

        foreach ([$teacherA, $teacherB] as $teacher) {
            TeacherAssignment::factory()->create([
                'user_id' => $teacher->id,
                'academic_period_id' => $period->id,
                'activity_id' => $activity->id,
            ]);
        }

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->set('academic_period_id', $period->id)
            ->set('scope_type', 'activity')
            ->set('activity_id', $activity->id)
            ->set($this->baseFormState())
            ->set('recipient_mode', 'subset')
            ->set('recipient_ids', [$teacherA->id])
            ->call('save');

        $deliverable = Deliverable::firstOrFail();
        $this->assertEquals([$teacherA->id], $deliverable->recipients->pluck('id')->all());
    }

    public function test_subset_mode_requires_at_least_one_recipient(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->set('academic_period_id', $period->id)
            ->set('scope_type', 'activity')
            ->set('activity_id', $activity->id)
            ->set($this->baseFormState())
            ->set('recipient_mode', 'subset')
            ->set('recipient_ids', [])
            ->call('save')
            ->assertHasErrors('recipient_ids');

        $this->assertDatabaseCount('deliverables', 0);
    }

    public function test_cannot_create_a_deliverable_for_a_closed_period(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Closed]);
        $activity = Activity::factory()->create();

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->set('academic_period_id', $period->id)
            ->set('scope_type', 'activity')
            ->set('activity_id', $activity->id)
            ->set($this->baseFormState())
            ->call('save')
            ->assertHasErrors('academic_period_id');

        $this->assertDatabaseCount('deliverables', 0);
    }

    public function test_leaving_the_optional_closing_date_blank_does_not_error(): void
    {
        // Regresión: closes_at es opcional y llegaba como cadena vacía en
        // lugar de null, lo que Postgres rechazaba como timestamp inválido.
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->set('academic_period_id', $period->id)
            ->set('scope_type', 'activity')
            ->set('activity_id', $activity->id)
            ->set($this->baseFormState())
            ->set('closes_at', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(Deliverable::firstOrFail()->closes_at);
    }

    public function test_loading_a_template_copies_its_plain_array_evidence_types_without_crashing(): void
    {
        // Regresión: DeliverableTemplate::allowed_evidence_types es un array
        // plano (cast 'array'), no una colección de enums; tratarlo como tal
        // (->map->value) lanzaba un error fatal al seleccionar la plantilla.
        $admin = $this->userWithRole(RoleName::Administrator);
        $template = DeliverableTemplate::factory()->create([
            'allowed_evidence_types' => [EvidenceType::File->value, EvidenceType::Text->value],
        ]);

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->set('template_id', $template->id)
            ->call('$refresh')
            ->assertSet('allowed_evidence_types', [EvidenceType::File->value, EvidenceType::Text->value])
            ->assertSet('name', $template->name);
    }

    public function test_editing_a_deliverable_in_a_closed_period_is_locked(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Closed])->id,
        ]);

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class, ['deliverable' => $deliverable])
            ->assertSet('periodLocked', true)
            ->set('name', 'Nombre cambiado')
            ->call('save')
            ->assertHasErrors('academic_period_id');

        $this->assertNotEquals('Nombre cambiado', $deliverable->fresh()->name);
    }

    public function test_administrator_can_view_the_recipients_list(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $teacher = $this->userWithRole(RoleName::Teacher);
        $deliverable = Deliverable::factory()->create();
        Evidence::factory()->create(['deliverable_id' => $deliverable->id, 'user_id' => $teacher->id]);

        $this->actingAs($admin)
            ->get(route('deliverables.recipients', $deliverable))
            ->assertOk()
            ->assertSee($teacher->name);
    }

    public function test_teacher_cannot_view_the_recipients_list(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $deliverable = Deliverable::factory()->create();

        $this->actingAs($teacher)
            ->get(route('deliverables.recipients', $deliverable))
            ->assertForbidden();
    }
}
