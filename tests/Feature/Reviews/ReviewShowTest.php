<?php

namespace Tests\Feature\Reviews;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\ReviewDecision;
use App\Enums\RoleName;
use App\Livewire\Reviews\ReviewShow;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewShowTest extends TestCase
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
     * Builds a submitted evidence for a teacher, plus a leader whose
     * leadership does or doesn't cover that teacher's activity/program.
     */
    private function submittedEvidenceWithLeader(bool $leaderInScope = true): array
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $leader = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $programUnit = ProgramUnit::factory()->create();

        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $programUnit->id,
        ]);

        Leadership::factory()->create([
            'user_id' => $leader->id,
            'academic_period_id' => $period->id,
            'activity_id' => null,
            'program_unit_id' => $leaderInScope ? $programUnit->id : ProgramUnit::factory()->create()->id,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ]);

        $evidence = Evidence::factory()->create([
            'user_id' => $teacher->id,
            'status' => EvidenceStatus::Pending,
            'deliverable_id' => Deliverable::factory()->create([
                'academic_period_id' => $period->id,
                'activity_id' => $activity->id,
            ])->id,
        ]);

        $version = $evidence->startOrGetDraftVersion($teacher);
        $version->update(['description' => 'Contenido de prueba']);
        $evidence->submitCurrentVersion();

        return [$evidence->fresh(), $leader, $teacher];
    }

    /**
     * Bloque de ajustes de interfaz, punto 7: "Aprobar"/"Devolver" no
     * tenían ningún estado de carga — mismo patrón ya usado en
     * Login/InstitutionSettingsForm/TeacherImportWizard.
     */
    public function test_approve_and_return_buttons_disable_themselves_and_show_their_own_loading_state(): void
    {
        [$evidence, $leader] = $this->submittedEvidenceWithLeader();

        $html = Livewire::actingAs($leader)->test(ReviewShow::class, ['evidence' => $evidence])->html();

        $this->assertStringContainsString('wire:target="approve,returnForAdjustment"', $html);
        $this->assertStringContainsString('wire:loading.attr="disabled"', $html);
        $this->assertStringContainsString('Aprobando...', $html);
        $this->assertStringContainsString('Devolviendo...', $html);
    }

    public function test_teacher_cannot_access_review_routes(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($teacher)->get('/reviews')->assertForbidden();
    }

    public function test_leader_within_scope_can_approve(): void
    {
        [$evidence, $leader] = $this->submittedEvidenceWithLeader(leaderInScope: true);

        Livewire::actingAs($leader)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->call('approve')
            ->assertHasNoErrors()
            ->assertRedirect(route('reviews.index'));

        $evidence->refresh();
        $this->assertEquals(EvidenceStatus::Approved, $evidence->status);
        $this->assertEquals(ReviewDecision::Approved, $evidence->currentVersion->reviews()->first()->decision);

        // Bloque de ajustes de interfaz, punto 5: este flash se perdía —
        // redirigía a reviews.index, que no tenía ningún bloque que lo
        // mostrara. Confirma con una petición real (no Livewire::test(),
        // que no renderiza el layout) que ahora sí llega.
        $this->actingAs($leader)->get(route('reviews.index'))->assertSee('Evidencia aprobada.');
    }

    public function test_leader_outside_scope_cannot_review(): void
    {
        [$evidence, $leader] = $this->submittedEvidenceWithLeader(leaderInScope: false);

        $this->actingAs($leader)->get('/reviews/'.$evidence->id)->assertForbidden();
    }

    public function test_returning_requires_an_observation(): void
    {
        [$evidence, $leader] = $this->submittedEvidenceWithLeader();

        Livewire::actingAs($leader)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->set('observation', '')
            ->call('returnForAdjustment')
            ->assertHasErrors('observation');

        $this->assertEquals(EvidenceStatus::Submitted, $evidence->fresh()->status);
    }

    public function test_returning_with_an_observation_moves_evidence_to_needs_adjustment(): void
    {
        [$evidence, $leader] = $this->submittedEvidenceWithLeader();

        Livewire::actingAs($leader)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->set('observation', 'Falta el archivo de soporte.')
            ->call('returnForAdjustment')
            ->assertHasNoErrors()
            ->assertRedirect(route('reviews.index'));

        $evidence->refresh();
        $this->assertEquals(EvidenceStatus::NeedsAdjustment, $evidence->status);

        $review = $evidence->currentVersion->reviews()->first();
        $this->assertEquals(ReviewDecision::Returned, $review->decision);
        $this->assertEquals('Falta el archivo de soporte.', $review->observations->first()->body);

        // Bloque de ajustes de interfaz, punto 5: este flash se perdía —
        // redirigía a reviews.index, que no tenía ningún bloque que lo
        // mostrara. Confirma con una petición real (no Livewire::test(),
        // que no renderiza el layout) que ahora sí llega.
        $this->actingAs($leader)->get(route('reviews.index'))->assertSee('Evidencia devuelta al docente para ajustes.');
    }

    public function test_teacher_cannot_review_their_own_evidence_even_as_leader(): void
    {
        [$evidence, , $teacher] = $this->submittedEvidenceWithLeader();

        // Le damos también rol de líder al propio docente para probar que
        // el bloqueo es por identidad, no por rol.
        $teacher->roles()->attach(Role::where('name', RoleName::Leader->value)->first());

        $this->actingAs($teacher)->get('/reviews/'.$evidence->id)->assertForbidden();
    }

    /**
     * RS-005/RF-027/RF-056 a RF-059 del documento de alcance: solo el
     * líder asignado revisa — Administrador ya no tiene un atajo general
     * para evidencias de actividad, ni siquiera cuando ningún líder cubre
     * ese ámbito. Ver test_administrator_can_review_a_cross_cutting_evidence()
     * para la única excepción real (compromisos transversales).
     */
    public function test_administrator_cannot_review_evidence_outside_any_leadership_scope(): void
    {
        [$evidence] = $this->submittedEvidenceWithLeader(leaderInScope: false);
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)->get('/reviews/'.$evidence->id)->assertForbidden();

        $this->assertEquals(EvidenceStatus::Submitted, $evidence->fresh()->status);
    }

    /**
     * Los compromisos transversales no tienen actividad, así que
     * estructuralmente nunca pueden tener un líder que los cubra — sin
     * esta excepción quedarían sin nadie que los apruebe/devuelva.
     * Coordinación sigue sin poder, ni siquiera aquí (ver
     * test_coordination_cannot_review_a_cross_cutting_evidence()).
     */
    public function test_administrator_can_review_a_cross_cutting_evidence(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $deliverable = Deliverable::factory()->crossCutting()->create();
        $evidence = Evidence::factory()->create([
            'user_id' => $teacher->id,
            'status' => EvidenceStatus::Pending,
            'deliverable_id' => $deliverable->id,
        ]);
        $version = $evidence->startOrGetDraftVersion($teacher);
        $version->update(['description' => 'Contenido de prueba']);
        $evidence->submitCurrentVersion();

        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(ReviewShow::class, ['evidence' => $evidence->fresh()])
            ->call('approve')
            ->assertHasNoErrors();

        $this->assertEquals(EvidenceStatus::Approved, $evidence->fresh()->status);
    }

    public function test_coordination_cannot_review_a_cross_cutting_evidence(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $deliverable = Deliverable::factory()->crossCutting()->create();
        $evidence = Evidence::factory()->create([
            'user_id' => $teacher->id,
            'status' => EvidenceStatus::Pending,
            'deliverable_id' => $deliverable->id,
        ]);
        $version = $evidence->startOrGetDraftVersion($teacher);
        $version->update(['description' => 'Contenido de prueba']);
        $evidence->submitCurrentVersion();

        $coordination = $this->userWithRole(RoleName::Coordination);

        $this->actingAs($coordination)->get('/reviews/'.$evidence->fresh()->id)->assertForbidden();
    }

    public function test_cannot_act_on_an_evidence_that_is_no_longer_pending_review(): void
    {
        [$evidence, $leader] = $this->submittedEvidenceWithLeader();
        $evidence->update(['status' => EvidenceStatus::Approved]);

        Livewire::actingAs($leader)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->call('approve')
            ->assertHasErrors('observation');

        // El estado ya no era Submitted, así que el guard debe impedir que
        // se cree ninguna revisión duplicada.
        $this->assertEquals(0, $evidence->currentVersion->reviews()->count());
    }

    public function test_only_administrator_can_reopen_an_approved_evidence(): void
    {
        [$evidence, $leader] = $this->submittedEvidenceWithLeader();
        $evidence->update(['status' => EvidenceStatus::Approved]);

        Livewire::actingAs($leader)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->call('reopen')
            ->assertForbidden();

        $this->assertEquals(EvidenceStatus::Approved, $evidence->fresh()->status);

        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->call('reopen');

        $this->assertEquals(EvidenceStatus::NeedsAdjustment, $evidence->fresh()->status);
    }

    /**
     * Bloque de ajustes de interfaz, punto 7: "Reabrir (permiso especial)"
     * tampoco tenía ningún estado de carga.
     */
    public function test_reopen_button_disables_itself_and_shows_a_loading_state(): void
    {
        [$evidence] = $this->submittedEvidenceWithLeader();
        $evidence->update(['status' => EvidenceStatus::Approved]);
        $admin = $this->userWithRole(RoleName::Administrator);

        $html = Livewire::actingAs($admin)->test(ReviewShow::class, ['evidence' => $evidence])->html();

        $this->assertStringContainsString('wire:target="reopen"', $html);
        $this->assertStringContainsString('wire:loading.attr="disabled"', $html);
        $this->assertStringContainsString('Reabriendo...', $html);
    }
}
