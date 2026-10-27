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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('local');
    }

    private function attachFile(\App\Models\EvidenceVersion $version, string $name): \App\Models\EvidenceFile
    {
        $file = UploadedFile::fake()->create($name, 50, 'application/pdf');
        $storedPath = $file->store('evidence/'.$version->id, 'local');

        return $version->files()->create([
            'original_name' => $name,
            'stored_name' => basename($storedPath),
            'disk_path' => $storedPath,
            'mime_type' => 'application/pdf',
            'size_bytes' => $file->getSize(),
        ]);
    }

    /**
     * Evidencia devuelta una vez por el líder vigente (versión 1, con un
     * archivo) y luego reenviada (versión 2, actual) — dos versiones y dos
     * entradas en el histórico de revisiones, lo mínimo para probar el
     * historial de versiones y el "Versión N" junto a cada revisión.
     */
    private function returnedThenResubmittedEvidence(): array
    {
        [$evidence, $leader, $teacher] = $this->submittedEvidenceWithLeader();

        $v1 = $evidence->currentVersion;
        $v1File = $this->attachFile($v1, 'version-1.pdf');

        $evidence->returnCurrentReviewForAdjustment($leader, 'Ajusta el documento.');

        $v2 = $evidence->startOrGetDraftVersion($teacher);
        $v2->update(['description' => 'Contenido corregido']);
        $evidence->submitCurrentVersion();

        return [$evidence->fresh(), $leader, $teacher, $v1File];
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
     * RS-005/RF-027/RF-056 a RF-059 del documento de alcance: mientras un
     * líder vigente cubra la actividad, sigue siendo el único revisor —
     * Administrador no tiene ningún atajo general, ni siquiera si ese
     * líder todavía no ha actuado. Ver
     * test_administrator_can_review_evidence_from_an_activity_with_no_leader_at_all()
     * para el respaldo real (punto 7, ampliado a Coordinación en el punto
     * 1 de la revisión de perfil Coordinación): solo aplica cuando NINGÚN
     * líder cubre la actividad en este momento.
     */
    public function test_administrator_cannot_review_evidence_when_a_leader_currently_covers_its_scope(): void
    {
        [$evidence] = $this->submittedEvidenceWithLeader(leaderInScope: true);
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)->get('/reviews/'.$evidence->id)->assertForbidden();

        $this->assertEquals(EvidenceStatus::Submitted, $evidence->fresh()->status);
    }

    /**
     * Mismo respaldo, mismo límite: mientras exista un líder vigente,
     * Coordinación tampoco tiene ningún atajo — ni siquiera con el
     * respaldo del punto 1, que solo cubre la AUSENCIA de un líder, nunca
     * compite con uno que ya existe.
     */
    public function test_coordination_cannot_review_evidence_when_a_leader_currently_covers_its_scope(): void
    {
        [$evidence] = $this->submittedEvidenceWithLeader(leaderInScope: true);
        $coordination = $this->userWithRole(RoleName::Coordination);

        $this->actingAs($coordination)->get('/reviews/'.$evidence->id)->assertForbidden();

        $this->assertEquals(EvidenceStatus::Submitted, $evidence->fresh()->status);
    }

    /**
     * Punto 7: respaldo para actividades sin ningún líder vigente (nadie
     * fue asignado, o su liderazgo ya terminó) — sin esto, esas
     * evidencias quedarían permanentemente sin nadie que las apruebe o
     * devuelva.
     */
    public function test_administrator_can_review_evidence_from_an_activity_with_no_leader_at_all(): void
    {
        [$evidence] = $this->submittedEvidenceWithLeader(leaderInScope: false);
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(ReviewShow::class, ['evidence' => $evidence->fresh()])
            ->call('approve')
            ->assertHasNoErrors();

        $this->assertEquals(EvidenceStatus::Approved, $evidence->fresh()->status);
    }

    /**
     * Punto 1 de la revisión de perfil Coordinación (opción A completa):
     * el mismo respaldo del punto 7 ahora también cubre a Coordinación,
     * no solo a Administrador.
     */
    public function test_coordination_can_review_evidence_from_an_activity_with_no_leader_at_all(): void
    {
        [$evidence] = $this->submittedEvidenceWithLeader(leaderInScope: false);
        $coordination = $this->userWithRole(RoleName::Coordination);

        Livewire::actingAs($coordination)
            ->test(ReviewShow::class, ['evidence' => $evidence->fresh()])
            ->call('approve')
            ->assertHasNoErrors();

        $this->assertEquals(EvidenceStatus::Approved, $evidence->fresh()->status);
    }

    /**
     * Los compromisos transversales no tienen actividad, así que
     * estructuralmente nunca pueden tener un líder que los cubra — sin
     * esta excepción quedarían sin nadie que los apruebe/devuelva.
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

    /**
     * Punto 1: el mismo respaldo para compromisos transversales ahora
     * también cubre a Coordinación.
     */
    public function test_coordination_can_review_a_cross_cutting_evidence(): void
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

        Livewire::actingAs($coordination)
            ->test(ReviewShow::class, ['evidence' => $evidence->fresh()])
            ->call('approve')
            ->assertHasNoErrors();

        $this->assertEquals(EvidenceStatus::Approved, $evidence->fresh()->status);
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

    public function test_version_history_shows_each_version_with_its_files_and_the_missing_file_indicator(): void
    {
        [$evidence, $leader, , $v1File] = $this->returnedThenResubmittedEvidence();

        // Simula el disco efímero de Render: la fila sigue en la base,
        // pero el archivo físico de la versión 1 ya no existe.
        Storage::disk('local')->delete($v1File->disk_path);

        $html = Livewire::actingAs($leader)->test(ReviewShow::class, ['evidence' => $evidence])->html();

        $this->assertStringContainsString('Historial de versiones', $html);
        $this->assertStringContainsString('Versión 1', $html);
        $this->assertStringContainsString('Versión 2', $html);
        $this->assertStringContainsString('Archivo no disponible', $html);
    }

    public function test_review_history_shows_the_version_number_next_to_each_review(): void
    {
        [$evidence, $leader] = $this->returnedThenResubmittedEvidence();

        $html = Livewire::actingAs($leader)->test(ReviewShow::class, ['evidence' => $evidence])->html();

        $this->assertMatchesRegularExpression('/Versión 1.*·.*'.$leader->name.'/s', $html);
    }

    public function test_metadata_card_shows_period_activity_component_weight_and_mandatory(): void
    {
        [$evidence, $leader] = $this->submittedEvidenceWithLeader();
        $deliverable = $evidence->deliverable;
        $deliverable->update(['weight_percentage' => 40, 'is_mandatory' => true]);

        $html = Livewire::actingAs($leader)->test(ReviewShow::class, ['evidence' => $evidence->fresh()])->html();

        $this->assertStringContainsString($deliverable->academicPeriod->name, $html);
        $this->assertStringContainsString($deliverable->activity->component->name, $html);
        $this->assertStringContainsString($deliverable->activity->name, $html);
        $this->assertStringContainsString('40%', $html);
        $this->assertStringContainsString('Obligatorio', $html);
    }

    public function test_metadata_card_shows_transversal_commitment_name_for_cross_cutting_deliverables(): void
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

        $html = Livewire::actingAs($admin)->test(ReviewShow::class, ['evidence' => $evidence->fresh()])->html();

        $this->assertStringContainsString('Transversal: '.$deliverable->crossCuttingCommitment->name, $html);
    }

    /**
     * Punto 9: el segundo líder puede seguir abriendo la página (su ámbito
     * la sigue autorizando — isReviewableBy() no mira quién decidió, solo
     * el ámbito), pero ya no puede actuar: isStillPendingReview() rechaza
     * cualquier acción en cuanto el estado deja de ser Enviada, sin
     * importar quién la puso en ese estado. Mismo guard que ya cubre
     * test_cannot_act_on_an_evidence_that_is_no_longer_pending_review(),
     * aquí con un segundo líder real en vez de un ->update() manual.
     */
    public function test_second_leader_over_the_same_scope_cannot_act_after_the_first_one_decided(): void
    {
        [$evidence, $leaderA, $teacher] = $this->submittedEvidenceWithLeader();
        $leaderB = $this->userWithRole(RoleName::Leader);

        // Mismo ámbito que $leaderA: mismo periodo/programa de
        // submittedEvidenceWithLeader(), buscado desde la propia evidencia.
        $assignment = $evidence->matchingTeacherAssignment();
        Leadership::factory()->create([
            'user_id' => $leaderB->id,
            'academic_period_id' => $assignment->academic_period_id,
            'program_unit_id' => $assignment->program_unit_id,
            'activity_id' => null,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        Livewire::actingAs($leaderA)
            ->test(ReviewShow::class, ['evidence' => $evidence])
            ->call('approve')
            ->assertHasNoErrors();

        Livewire::actingAs($leaderB)
            ->test(ReviewShow::class, ['evidence' => $evidence->fresh()])
            ->call('approve')
            ->assertHasErrors('observation');

        $this->assertEquals(EvidenceStatus::Approved, $evidence->fresh()->status);
        $this->assertEquals(1, $evidence->fresh()->currentVersion->reviews()->count());
    }

    public function test_shows_the_late_submission_indicator_when_submitted_after_the_due_date(): void
    {
        [$evidence, $leader] = $this->submittedEvidenceWithLeader();
        $evidence->deliverable->update(['due_at' => now()->subDays(5)]);

        $html = Livewire::actingAs($leader)->test(ReviewShow::class, ['evidence' => $evidence->fresh()])->html();

        $this->assertStringContainsString('5 días después de la fecha límite', $html);
    }

    public function test_does_not_show_the_late_submission_indicator_when_submitted_on_time(): void
    {
        [$evidence, $leader] = $this->submittedEvidenceWithLeader();
        // due_at de la factory por defecto está en el futuro (now()+2 semanas).

        $html = Livewire::actingAs($leader)->test(ReviewShow::class, ['evidence' => $evidence->fresh()])->html();

        $this->assertStringNotContainsString('después de la fecha límite', $html);
    }

    public function test_leader_outside_scope_cannot_download_a_file_from_a_previous_version(): void
    {
        [$evidence, , , $v1File] = $this->returnedThenResubmittedEvidence();
        $outsideLeader = $this->userWithRole(RoleName::Leader);

        $this->actingAs($outsideLeader)
            ->get(route('evidence-files.download', $v1File))
            ->assertForbidden();
    }
}
