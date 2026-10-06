<?php

namespace Tests\Feature\Deliverables;

use App\Enums\AcademicPeriodStatus;
use App\Enums\DeliverableStatus;
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
use App\Services\CatalogDependencyChecker;
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
        TeacherAssignment::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activity->id]);

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
        $this->teacher();

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
        TeacherAssignment::factory()->create(['academic_period_id' => $period->id, 'activity_id' => $activity->id]);

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

    /**
     * Prioridad 5: conversión del formulario de creación en un wizard de 6
     * pasos, con borrador en memoria (nunca persistido a medias en BD) y
     * validación progresiva por paso. save() conserva exactamente el mismo
     * comportamiento de antes (todos los tests previos de esta clase lo
     * ejercitan sin pasar por el wizard), así que estos tests cubren
     * específicamente la navegación nueva: nextStep/previousStep/goToStep.
     */
    public function test_cannot_advance_past_step_one_without_its_required_fields(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->assertSet('step', 1)
            ->call('nextStep')
            ->assertHasErrors(['academic_period_id', 'activity_id'])
            ->assertSet('step', 1)
            ->assertSet('maxStepReached', 1);
    }

    public function test_advancing_through_each_step_only_requires_that_steps_own_fields(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();

        $component = Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->set('academic_period_id', $period->id)
            ->set('scope_type', 'activity')
            ->set('activity_id', $activity->id)
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 2);

        // El paso 2 exige 'name': sin él no debe dejar avanzar al paso 3.
        $component->call('nextStep')
            ->assertHasErrors('name')
            ->assertSet('step', 2);

        $component->set($this->baseFormState())
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 3)
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 4)
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 5)
            ->assertSet('maxStepReached', 5);
    }

    /**
     * Ampliación de la Prioridad 5 (revisión de la directora, punto 2): el
     * borrador permite avanzar y guardar sin destinatarios resueltos
     * todavía — esa exigencia se reserva para "Publicar" (save()), nunca
     * para la navegación del wizard.
     */
    public function test_advancing_past_step_five_never_requires_recipients(): void
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
            ->set('step', 5)
            ->set('maxStepReached', 5)
            ->set('recipient_mode', 'subset')
            ->set('recipient_ids', [])
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 6);
    }

    /**
     * Revisión de la directora, punto 1: "subset" vacío sigue bloqueando
     * la publicación (comportamiento ya existente), pero ahora save()
     * también devuelve al usuario al paso 5 para que vea el error, ya que
     * el wizard pudo haber avanzado hasta el paso 6 sin pasar por ahí.
     */
    public function test_publishing_with_an_empty_subset_returns_to_step_five_with_the_error(): void
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
            ->set('step', 6)
            ->set('maxStepReached', 6)
            ->call('save')
            ->assertHasErrors('recipient_ids')
            ->assertSet('step', 5);

        $this->assertDatabaseCount('deliverables', 0);
    }

    /**
     * Revisión de la directora, punto 1: antes de este cambio, modo "all"
     * sin ningún docente que cumpliera el ámbito (actividad sin
     * asignaciones) dejaba crear el entregable igual, huérfano — sin
     * destinatarios ni evidencia para nadie.
     */
    public function test_publishing_in_all_mode_with_zero_candidate_teachers_is_blocked(): void
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
            ->set('recipient_mode', 'all')
            ->call('save')
            ->assertHasErrors('recipient_ids');

        $this->assertDatabaseCount('deliverables', 0);
    }

    /**
     * Ajuste de UX sobre el punto 1: el aviso informativo (amarillo, "no
     * hay ningún docente en este ámbito todavía") y el error de validación
     * (rojo, tras un intento de publicar fallido) no deben coexistir — el
     * rojo solo aparece después de intentar publicar, y desaparece en
     * cuanto la condición que lo causó se corrige, sin que haga falta otro
     * intento fallido de por medio.
     */
    public function test_recipient_error_clears_once_the_scope_resolves_to_a_teacher(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activityWithoutTeachers = Activity::factory()->create();
        $activityWithTeacher = Activity::factory()->create();
        $teacher = $this->teacher();
        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activityWithTeacher->id,
        ]);

        $component = Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->set('academic_period_id', $period->id)
            ->set('scope_type', 'activity')
            ->set('activity_id', $activityWithoutTeachers->id)
            ->set($this->baseFormState())
            ->set('recipient_mode', 'all')
            ->assertHasNoErrors('recipient_ids');

        $component->call('save')->assertHasErrors('recipient_ids');

        $component->set('activity_id', $activityWithTeacher->id)
            ->assertHasNoErrors('recipient_ids')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('deliverables', 1);
    }

    /**
     * Revisión de la directora, punto 2: guardar como borrador no exige
     * destinatarios y no dispara ningún efecto real (sin sync, sin
     * evidencia creada) — el entregable existe en BD pero no es accionable
     * para ningún docente todavía.
     */
    public function test_saving_as_draft_skips_recipient_sync_and_evidence_creation(): void
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
            ->set('recipient_mode', 'all')
            ->call('saveAsDraft')
            ->assertHasNoErrors();

        $deliverable = Deliverable::firstOrFail();
        $this->assertEquals(DeliverableStatus::Draft, $deliverable->status);
        $this->assertCount(0, $deliverable->recipients);
        $this->assertDatabaseCount('evidences', 0);
    }

    /**
     * Editar un borrador y publicarlo sí dispara los efectos reales en ese
     * momento — es la transición que lo activa.
     */
    public function test_publishing_an_existing_draft_triggers_recipient_sync_and_evidence_creation(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $teacher = $this->teacher();
        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
        ]);
        $deliverable = Deliverable::factory()->draft()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
        ]);

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class, ['deliverable' => $deliverable])
            ->set('recipient_mode', 'all')
            ->call('save')
            ->assertHasNoErrors();

        $deliverable->refresh();
        $this->assertEquals(DeliverableStatus::Published, $deliverable->status);
        $this->assertEquals([$teacher->id], $deliverable->recipients->pluck('id')->all());
        $this->assertDatabaseHas('evidences', ['deliverable_id' => $deliverable->id, 'user_id' => $teacher->id]);
    }

    /**
     * Un entregable ya Publicado no vuelve a Borrador: saveAsDraft() no
     * debe tener ningún efecto sobre él (la transición es de un solo
     * sentido).
     */
    public function test_saving_as_draft_has_no_effect_on_an_already_published_deliverable(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active])->id,
            'name' => 'Nombre original',
        ]);

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class, ['deliverable' => $deliverable])
            ->set('name', 'Nombre cambiado')
            ->call('saveAsDraft');

        $deliverable->refresh();
        $this->assertEquals(DeliverableStatus::Published, $deliverable->status);
        $this->assertEquals('Nombre original', $deliverable->name);
    }

    /**
     * Un entregable en Borrador no cuenta como dependiente "activo" para
     * CatalogDependencyChecker — no es visible ni accionable todavía, así
     * que no debería bloquear desactivar su actividad.
     */
    public function test_a_draft_deliverable_does_not_block_deactivating_its_activity(): void
    {
        $activity = Activity::factory()->create(['is_active' => true]);
        $openPeriod = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        Deliverable::factory()->draft()->create(['activity_id' => $activity->id, 'academic_period_id' => $openPeriod->id]);

        $this->assertSame(0, CatalogDependencyChecker::openDeliverableCountForActivity($activity));
    }

    public function test_cannot_skip_ahead_to_a_step_not_yet_reached(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->assertSet('maxStepReached', 1)
            ->call('goToStep', 6)
            ->assertSet('step', 1);
    }

    public function test_editing_an_existing_deliverable_allows_jumping_to_any_step_immediately(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active])->id,
        ]);

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class, ['deliverable' => $deliverable])
            ->assertSet('maxStepReached', 6)
            ->call('goToStep', 6)
            ->assertSet('step', 6)
            ->call('goToStep', 2)
            ->assertSet('step', 2);
    }

    public function test_completing_the_wizard_end_to_end_creates_the_deliverable_like_the_old_single_page_form(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $teacher = $this->teacher();
        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
        ]);

        Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->set('academic_period_id', $period->id)
            ->set('scope_type', 'activity')
            ->set('activity_id', $activity->id)
            ->call('nextStep')->assertSet('step', 2)
            ->set($this->baseFormState())
            ->call('nextStep')->assertSet('step', 3)
            ->call('nextStep')->assertSet('step', 4)
            ->call('nextStep')->assertSet('step', 5)
            ->set('recipient_mode', 'all')
            ->call('nextStep')->assertSet('step', 6)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('deliverables.index'));

        $deliverable = Deliverable::firstOrFail();
        $this->assertEquals('Entregable de prueba', $deliverable->name);
        $this->assertEquals([$teacher->id], $deliverable->recipients->pluck('id')->all());
        $this->assertDatabaseHas('evidences', ['deliverable_id' => $deliverable->id, 'user_id' => $teacher->id]);

        // Bloque de ajustes de interfaz, punto 5: este flash se perdía —
        // redirigía a deliverables.index, que no tenía ningún bloque que
        // lo mostrara. Confirma con una petición real (no Livewire::test(),
        // que no renderiza el layout) que ahora sí llega.
        $this->actingAs($admin)->get(route('deliverables.index'))->assertSee('Entregable guardado correctamente.');
    }

    /**
     * Bloque de ajustes de interfaz, punto 3: estos 8 campos ya tenían su
     * regla de validación en rulesForStep(), pero la plantilla no tenía
     * ningún @error() que la mostrara — una falla quedaba en el error bag
     * sin ningún indicio visible para quien llena el formulario. Fuerza
     * cada campo a un estado inválido y confirma que el mensaje en
     * español aparece en el HTML renderizado, no solo en assertHasErrors().
     */
    public function test_previously_silent_validation_errors_are_now_visible_in_the_form(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();

        $component = Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->set('academic_period_id', $period->id)
            ->set('scope_type', 'not-a-valid-scope')
            ->set('activity_id', $activity->id)
            ->call('nextStep')
            ->assertHasErrors('scope_type')
            ->assertSet('step', 1);

        $this->assertStringContainsString('tipo de alcance', $component->html());

        // updatedScopeType() limpia activity_id al cambiar de ámbito —
        // hay que volver a fijarlo tras corregir scope_type.
        $component->set('scope_type', 'activity')
            ->set('activity_id', $activity->id)
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 2)
            ->set('name', 'Entregable de prueba')
            ->set('description', str_repeat('a', 2001))
            ->set('instructions', str_repeat('a', 5001))
            ->set('completion_criteria', str_repeat('a', 2001))
            ->call('nextStep')
            ->assertHasErrors(['description', 'instructions', 'completion_criteria'])
            ->assertSet('step', 2);

        $html = $component->html();
        $this->assertStringContainsString('descripción no debe tener más de 2000 caracteres', $html);
        $this->assertStringContainsString('instrucciones no debe tener más de 5000 caracteres', $html);
        $this->assertStringContainsString('criterio de cumplimiento no debe tener más de 2000 caracteres', $html);

        $component->set([
            'description' => '',
            'instructions' => '',
            'completion_criteria' => '',
        ])
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 3)
            ->set('periodicity_type', 'not-a-real-periodicity')
            ->call('nextStep')
            ->assertHasErrors('periodicity_type')
            ->assertSet('step', 3);

        $this->assertStringContainsString('periodicidad', $component->html());

        $component->set(array_merge($this->baseFormState(), ['periodicity_type' => 'single']))
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 4)
            ->set('max_files', 0)
            ->set('max_file_size_mb', 0)
            ->set('weight_percentage', 0)
            ->call('nextStep')
            ->assertHasErrors(['max_files', 'max_file_size_mb', 'weight_percentage'])
            ->assertSet('step', 4);

        $html = $component->html();
        $this->assertStringContainsString('máximo de archivos debe ser al menos 1', $html);
        $this->assertStringContainsString('tamaño máximo de archivo (MB) debe ser al menos 1', $html);
        $this->assertStringContainsString('peso porcentual debe ser al menos 1', $html);
    }

    /**
     * Bloque de ajustes de interfaz, punto 4: publicar por primera vez es
     * irreversible en el sentido de que congela la edición libre, así que
     * el botón ya no llama a save() directo — pasa primero por el
     * confirm-modal compartido (mismo patrón que desactivar un catálogo).
     */
    public function test_publishing_for_the_first_time_requires_confirmation(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();

        $component = Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->set('academic_period_id', $period->id)
            ->set('scope_type', 'activity')
            ->set('activity_id', $activity->id)
            ->call('nextStep')->assertSet('step', 2)
            ->set($this->baseFormState())
            ->call('nextStep')->assertSet('step', 3)
            ->call('nextStep')->assertSet('step', 4)
            ->call('nextStep')->assertSet('step', 5)
            ->set('recipient_mode', 'all')
            ->call('nextStep')->assertSet('step', 6);

        $html = $component->html();
        $this->assertStringContainsString("dispatch('confirm-modal'", $html);
        $this->assertStringContainsString('Publicar entregable', $html);
        $this->assertStringContainsString('$wire.save()', $html);

        $this->assertDatabaseMissing('deliverables', ['name' => 'Entregable de prueba']);
    }

    /**
     * Bloque de ajustes de interfaz, punto 7: "Guardar como borrador" y
     * "Publicar" no tenían ningún estado de carga.
     */
    public function test_draft_and_publish_buttons_disable_themselves_and_show_their_own_loading_state(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();

        $html = Livewire::actingAs($admin)
            ->test(DeliverableForm::class)
            ->set('academic_period_id', $period->id)
            ->set('scope_type', 'activity')
            ->set('activity_id', $activity->id)
            ->set('step', 6)
            ->html();

        $this->assertStringContainsString('wire:target="saveAsDraft,save"', $html);
        $this->assertStringContainsString('wire:loading.attr="disabled"', $html);
        $this->assertStringContainsString('Guardando...', $html);
        $this->assertStringContainsString('Publicando...', $html);
    }

    /**
     * Guardar cambios sobre un entregable YA publicado no congela nada
     * nuevo (ya estaba publicado) — sigue siendo un submit directo, sin
     * el paso de confirmación que sí exige la primera publicación.
     */
    public function test_saving_changes_to_an_already_published_deliverable_does_not_require_confirmation(): void
    {
        $admin = $this->userWithRole(RoleName::Administrator);
        $deliverable = Deliverable::factory()->create();

        $html = Livewire::actingAs($admin)
            ->test(DeliverableForm::class, ['deliverable' => $deliverable])
            ->set('step', 6)
            ->html();

        $this->assertStringNotContainsString("dispatch('confirm-modal'", $html);
        $this->assertStringContainsString('Guardar cambios', $html);
    }
}
