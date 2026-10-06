<?php

namespace Tests\Feature\Evidence;

use App\Enums\AcademicPeriodStatus;
use App\Enums\EvidenceStatus;
use App\Enums\EvidenceType;
use App\Enums\RoleName;
use App\Livewire\Evidence\EvidenceWorkspace;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\AuditLog;
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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EvidenceWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('local');
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', $role->value)->first());

        return $user;
    }

    /**
     * Periodo Activo por defecto — AcademicPeriodFactory por defecto crea
     * uno en Planeación, que ahora bloquea todas las acciones de escritura
     * (ver Deliverable::acceptsEvidenceSubmissions() y los tests de más
     * abajo sobre el bloqueo por estado de periodo); las pruebas de este
     * archivo ejercitan ese flujo de escritura, así que necesitan un
     * periodo operativo salvo que prueben justamente el bloqueo.
     */
    private function evidenceFor(array $deliverableAttributes = []): Evidence
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $deliverable = Deliverable::factory()->create(array_merge([
            'academic_period_id' => $period->id,
            'allowed_evidence_types' => [EvidenceType::File->value],
            'allowed_file_types' => ['pdf'],
            'max_files' => 1,
            'max_file_size_mb' => 5,
        ], $deliverableAttributes));

        return Evidence::factory()->create([
            'deliverable_id' => $deliverable->id,
            'user_id' => $teacher->id,
            'status' => EvidenceStatus::Pending,
        ]);
    }

    public function test_teacher_can_save_a_draft_with_a_file(): void
    {
        $evidence = $this->evidenceFor();
        $file = UploadedFile::fake()->create('propuesta.pdf', 100, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->call('saveDraft')
            ->assertHasNoErrors();

        $evidence->refresh();
        $this->assertEquals(EvidenceStatus::Draft, $evidence->status);
        $this->assertCount(1, $evidence->currentVersion->files);
        $this->assertEquals('propuesta.pdf', $evidence->currentVersion->files->first()->original_name);
    }

    /**
     * Bloque de ajustes de interfaz, punto 7: "Guardar borrador" era,
     * según el diagnóstico, el único botón de todo el sistema sin ningún
     * paso de loading — ni siquiera wire:loading simple. "Enviar
     * evidencia" (vía confirm-modal) tampoco lo tenía.
     */
    public function test_save_draft_and_submit_buttons_disable_themselves_and_show_their_own_loading_state(): void
    {
        $evidence = $this->evidenceFor();

        $html = Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->html();

        $this->assertStringContainsString('wire:target="saveDraft,submit"', $html);
        $this->assertStringContainsString('wire:loading.attr="disabled"', $html);
        $this->assertStringContainsString('Guardando...', $html);
        $this->assertStringContainsString('Enviando...', $html);
    }

    public function test_cannot_submit_without_meeting_minimum_evidence_requirement(): void
    {
        $evidence = $this->evidenceFor();

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->call('submit')
            ->assertSet('submissionError', fn ($message) => str_contains($message, 'Debes adjuntar'));

        // saveDraft() ya movió el estado a Draft (se creó una versión vacía);
        // lo que debe seguir bloqueado es la transición final a Submitted.
        $this->assertEquals(EvidenceStatus::Draft, $evidence->fresh()->status);
    }

    public function test_submitting_locks_the_evidence_from_further_direct_edits(): void
    {
        $evidence = $this->evidenceFor();
        $file = UploadedFile::fake()->create('propuesta.pdf', 100, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->call('submit');

        $evidence->refresh();
        $this->assertEquals(EvidenceStatus::Submitted, $evidence->status);
        $this->assertNotNull($evidence->currentVersion->submitted_at);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('description', 'intento de edición')
            ->call('saveDraft');

        $this->assertNull($evidence->currentVersion->fresh()->description);
    }

    public function test_returning_to_needs_adjustment_and_resaving_creates_a_new_version(): void
    {
        $evidence = $this->evidenceFor(['allowed_evidence_types' => [EvidenceType::Text->value]]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('description', 'Primera versión')
            ->call('submit');

        $evidence->refresh();
        $firstVersionId = $evidence->current_version_id;
        $this->assertEquals(1, $evidence->versions()->count());

        // El líder devuelve la evidencia (esto lo hará el módulo 7; aquí solo
        // simulamos el efecto sobre el estado para probar el versionado).
        $evidence->update(['status' => EvidenceStatus::NeedsAdjustment]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('description', 'Segunda versión ajustada')
            ->call('submit');

        $evidence->refresh();
        $this->assertEquals(2, $evidence->versions()->count());
        $this->assertNotEquals($firstVersionId, $evidence->current_version_id);

        $firstVersion = $evidence->versions()->where('version_number', 1)->first();
        $this->assertEquals('Primera versión', $firstVersion->description);

        $secondVersion = $evidence->versions()->where('version_number', 2)->first();
        $this->assertEquals('Segunda versión ajustada', $secondVersion->description);
    }

    public function test_uploaded_file_must_match_the_allowed_extension(): void
    {
        $evidence = $this->evidenceFor(['allowed_file_types' => ['pdf']]);
        $file = UploadedFile::fake()->create('imagen.jpg', 100, 'image/jpeg');

        // updatedNewFiles() valida y descarta el archivo inválido apenas se
        // adjunta — para cuando saveDraft() correría, newFiles ya está
        // vacío, así que el rechazo ya ocurrió antes de llegar ahí.
        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->assertHasErrors('newFiles')
            ->assertSet('newFiles', []);

        $this->assertEquals(EvidenceStatus::Pending, $evidence->fresh()->status);
    }

    /**
     * "Formatos de archivo permitidos" es opcional en el formulario de
     * entregable — pero dejarlo vacío nunca debe habilitar cualquier
     * extensión. Ver Deliverable::DEFAULT_ALLOWED_FILE_TYPES.
     */
    public function test_file_upload_falls_back_to_the_default_whitelist_when_none_is_configured(): void
    {
        $evidence = $this->evidenceFor(['allowed_file_types' => []]);
        $file = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->assertHasErrors('newFiles')
            ->assertSet('newFiles', []);
    }

    public function test_invalid_file_is_rejected_and_removed_immediately_on_attach(): void
    {
        $evidence = $this->evidenceFor(['allowed_file_types' => ['pdf']]);
        $file = UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->assertHasErrors('newFiles')
            ->assertSet('newFiles', [])
            ->assertDontSee('virus.exe');
    }

    public function test_error_clears_after_retrying_with_a_valid_file_in_the_same_session(): void
    {
        $evidence = $this->evidenceFor(['allowed_file_types' => ['pdf']]);
        $invalid = UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload');
        $valid = UploadedFile::fake()->create('propuesta.pdf', 100, 'application/pdf');

        $test = Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$invalid])
            ->assertHasErrors('newFiles');

        $test->set('newFiles', [$valid])
            ->assertHasNoErrors()
            ->assertSee('propuesta.pdf');
    }

    public function test_valid_file_appears_in_the_pending_list_immediately_after_attaching(): void
    {
        $evidence = $this->evidenceFor();
        $file = UploadedFile::fake()->create('propuesta.pdf', 100, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->assertHasNoErrors()
            ->assertSee('propuesta.pdf')
            ->assertSee('100 KB');
    }

    public function test_removing_a_pending_file_takes_it_out_of_new_files(): void
    {
        $evidence = $this->evidenceFor(['max_files' => 2]);
        $fileA = UploadedFile::fake()->create('a.pdf', 50, 'application/pdf');
        $fileB = UploadedFile::fake()->create('b.pdf', 50, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$fileA, $fileB])
            ->call('removeNewFile', 0)
            ->assertSet('newFiles', fn ($files) => count($files) === 1 && $files[0]->getClientOriginalName() === 'b.pdf');
    }

    public function test_file_upload_accepts_a_default_whitelisted_extension_when_none_is_configured(): void
    {
        $evidence = $this->evidenceFor(['allowed_file_types' => []]);
        $file = UploadedFile::fake()->create('propuesta.pdf', 100, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->call('saveDraft')
            ->assertHasNoErrors();
    }

    public function test_uploaded_file_must_not_exceed_the_max_size(): void
    {
        $evidence = $this->evidenceFor(['max_file_size_mb' => 1]);
        $file = UploadedFile::fake()->create('grande.pdf', 2000, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->assertHasErrors('newFiles')
            ->assertSet('newFiles', []);
    }

    public function test_cannot_exceed_the_max_files_limit(): void
    {
        $evidence = $this->evidenceFor(['max_files' => 1]);
        $fileA = UploadedFile::fake()->create('a.pdf', 50, 'application/pdf');
        $fileB = UploadedFile::fake()->create('b.pdf', 50, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$fileA, $fileB])
            ->call('saveDraft')
            ->assertHasErrors('newFiles');

        $this->assertNull($evidence->fresh()->current_version_id);
    }

    public function test_owner_can_view_their_own_evidence(): void
    {
        $evidence = $this->evidenceFor();

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->assertOk();
    }

    public function test_unrelated_teacher_cannot_view_someone_elses_evidence(): void
    {
        $evidence = $this->evidenceFor();
        $otherTeacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($otherTeacher)
            ->get('/my-deliverables/'.$evidence->id)
            ->assertForbidden();
    }

    public function test_administrator_can_view_but_not_mutate_someone_elses_evidence(): void
    {
        $evidence = $this->evidenceFor();
        $admin = $this->userWithRole(RoleName::Administrator);

        $this->actingAs($admin)->get('/my-deliverables/'.$evidence->id)->assertOk();

        Livewire::actingAs($admin)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('description', 'intento no autorizado')
            ->call('saveDraft')
            ->assertForbidden();
    }

    public function test_administrator_can_mark_evidence_as_exempt_with_justification(): void
    {
        $evidence = $this->evidenceFor();
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('exemptionJustification', 'Docente en licencia de maternidad durante todo el periodo.')
            ->call('markExempt')
            ->assertHasNoErrors();

        $evidence->refresh();
        $this->assertEquals(EvidenceStatus::Exempt, $evidence->status);

        $log = AuditLog::where('action', 'evidence_marked_exempt')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals($admin->id, $log->user_id);
        $this->assertEquals($evidence->id, $log->auditable_id);
        $this->assertStringContainsString('licencia de maternidad', $log->metadata['justification']);
        $this->assertEquals('pending', $log->metadata['previous_status']);
    }

    /**
     * Bloque de ajustes de interfaz, punto 7: "Marcar como exento" no
     * tenía ningún estado de carga.
     */
    public function test_mark_exempt_button_disables_itself_and_shows_a_loading_state(): void
    {
        $evidence = $this->evidenceFor();
        $admin = $this->userWithRole(RoleName::Administrator);

        $html = Livewire::actingAs($admin)->test(EvidenceWorkspace::class, ['evidence' => $evidence])->html();

        $this->assertStringContainsString('wire:target="markExempt"', $html);
        $this->assertStringContainsString('wire:loading.attr="disabled"', $html);
        $this->assertStringContainsString('Marcando...', $html);
    }

    public function test_coordination_can_also_mark_evidence_as_exempt(): void
    {
        $evidence = $this->evidenceFor();
        $coordination = $this->userWithRole(RoleName::Coordination);

        Livewire::actingAs($coordination)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('exemptionJustification', 'Reasignación administrativa aprobada por decanatura.')
            ->call('markExempt')
            ->assertHasNoErrors();

        $this->assertEquals(EvidenceStatus::Exempt, $evidence->fresh()->status);
    }

    public function test_marking_exempt_without_justification_fails(): void
    {
        $evidence = $this->evidenceFor();
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('exemptionJustification', '')
            ->call('markExempt')
            ->assertHasErrors('exemptionJustification');

        $this->assertEquals(EvidenceStatus::Pending, $evidence->fresh()->status);
    }

    /**
     * Revisión del estado Exento (aclaración de la directora): el docente
     * dueño ahora puede marcarse exento cuando otra prioridad le impide
     * cumplir la entrega — antes esto estaba prohibido por completo (ver
     * EvidencePolicy::markExempt()).
     */
    public function test_teacher_can_mark_their_own_evidence_as_exempt_when_pending(): void
    {
        $evidence = $this->evidenceFor();

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('exemptionJustification', 'Tengo una carga académica adicional este periodo que no me permite cumplir.')
            ->call('markExempt')
            ->assertHasNoErrors();

        $evidence->refresh();
        $this->assertEquals(EvidenceStatus::Exempt, $evidence->status);
        $this->assertEquals(
            'Tengo una carga académica adicional este periodo que no me permite cumplir.',
            $evidence->exemption_reason
        );
    }

    public function test_teacher_can_mark_their_own_evidence_as_exempt_when_needs_adjustment(): void
    {
        $evidence = $this->evidenceFor();
        $evidence->update(['status' => EvidenceStatus::NeedsAdjustment]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('exemptionJustification', 'Un percance familiar me impide ajustarla a tiempo.')
            ->call('markExempt')
            ->assertHasNoErrors();

        $this->assertEquals(EvidenceStatus::Exempt, $evidence->fresh()->status);
    }

    /**
     * "Docente ajeno": un docente sin ninguna relación con esta evidencia
     * (no es el dueño, no es su líder) — EvidencePolicy::view() ya lo
     * bloquearía antes de llegar a markExempt() si se montara la pantalla
     * completa (ver test_unrelated_teacher_cannot_view_someone_elses_evidence
     * más abajo), así que esta prueba va directo contra la policy
     * específica de markExempt(), para aislar justo la regla que cambió
     * (el dueño sí puede, cualquier otro docente no).
     */
    public function test_another_teachers_evidence_cannot_be_marked_exempt_by_a_different_teacher(): void
    {
        $evidence = $this->evidenceFor();
        $otherTeacher = $this->userWithRole(RoleName::Teacher);

        $this->assertFalse($otherTeacher->can('markExempt', $evidence));
    }

    /**
     * Estados bloqueados: una evidencia Enviada o Aprobada ya tiene (o
     * tuvo) una revisión en curso o resuelta — ni siquiera el propio
     * docente puede eximirse por detrás de eso.
     */
    public function test_teacher_cannot_mark_their_own_submitted_evidence_as_exempt(): void
    {
        $evidence = $this->evidenceFor();
        $evidence->update(['status' => EvidenceStatus::Submitted]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('exemptionJustification', 'Intento no válido')
            ->call('markExempt')
            ->assertForbidden();
    }

    public function test_teacher_cannot_mark_their_own_approved_evidence_as_exempt(): void
    {
        $evidence = $this->evidenceFor();
        $evidence->update(['status' => EvidenceStatus::Approved]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('exemptionJustification', 'Intento no válido')
            ->call('markExempt')
            ->assertForbidden();
    }

    /**
     * Ajuste del bloqueo: solo Enviada y Aprobada están bloqueadas —
     * Borrador y Vencida (y Pendiente/Requiere ajustes, ya probados
     * arriba) son elegibles.
     */
    public function test_teacher_can_mark_their_own_draft_evidence_as_exempt(): void
    {
        $evidence = $this->evidenceFor();
        $evidence->update(['status' => EvidenceStatus::Draft]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('exemptionJustification', 'Tengo otra prioridad este periodo.')
            ->call('markExempt')
            ->assertHasNoErrors();

        $this->assertEquals(EvidenceStatus::Exempt, $evidence->fresh()->status);
    }

    /**
     * Vencida es un valor real de `evidences.status` (lo pone el comando
     * diario `evidences:mark-overdue`, no se calcula a partir de la fecha
     * límite) — se verifica aquí poniéndolo directamente, igual que
     * cualquier otro estado.
     */
    public function test_teacher_can_mark_their_own_expired_evidence_as_exempt(): void
    {
        $evidence = $this->evidenceFor();
        $evidence->update(['status' => EvidenceStatus::Expired]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('exemptionJustification', 'Tengo otra prioridad este periodo.')
            ->call('markExempt')
            ->assertHasNoErrors();

        $this->assertEquals(EvidenceStatus::Exempt, $evidence->fresh()->status);
    }

    /**
     * Ventana real entre que la fecha límite pasa y el comando diario
     * `evidences:mark-overdue` todavía no corrió: la evidencia sigue
     * siendo Pendiente en la base de datos — ya era elegible antes de
     * este ajuste (Pendiente siempre lo fue) y lo sigue siendo.
     */
    public function test_a_pending_evidence_past_its_due_date_but_not_yet_processed_by_the_scheduler_can_still_be_exempted(): void
    {
        $evidence = $this->evidenceFor(['due_at' => now()->subDay()]);
        $this->assertEquals(EvidenceStatus::Pending, $evidence->status);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('exemptionJustification', 'Tengo otra prioridad este periodo.')
            ->call('markExempt')
            ->assertHasNoErrors();

        $this->assertEquals(EvidenceStatus::Exempt, $evidence->fresh()->status);
    }

    /**
     * Si el estado anterior a la exención era Borrador (con archivos ya
     * cargados en una versión en edición), quitar la exención no debe
     * perder nada: vuelve a Pendiente con la misma versión/archivos
     * intactos — removeExemption() solo toca `status`/`exemption_reason`,
     * nunca `current_version_id` ni las versiones/archivos.
     */
    public function test_removing_an_exemption_from_a_formerly_draft_evidence_keeps_its_files_intact(): void
    {
        $evidence = $this->evidenceFor();
        $file = UploadedFile::fake()->create('propuesta.pdf', 100, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->call('saveDraft')
            ->assertHasNoErrors();

        $evidence->refresh();
        $this->assertEquals(EvidenceStatus::Draft, $evidence->status);
        $versionId = $evidence->current_version_id;

        $evidence->update(['status' => EvidenceStatus::Exempt, 'exemption_reason' => 'Motivo temporal.']);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->call('removeExemption')
            ->assertHasNoErrors();

        $evidence->refresh();
        $this->assertEquals(EvidenceStatus::Pending, $evidence->status);
        $this->assertNull($evidence->exemption_reason);
        $this->assertEquals($versionId, $evidence->current_version_id);
        $this->assertCount(1, $evidence->currentVersion->files);
        $this->assertEquals('propuesta.pdf', $evidence->currentVersion->files->first()->original_name);
    }

    public function test_leader_cannot_mark_evidence_as_exempt(): void
    {
        // El líder debe poder VER la evidencia (está dentro de su ámbito de
        // liderazgo) para que la denegación observada sea específicamente
        // la de markExempt, no un rechazo más temprano de la política view.
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
            'program_unit_id' => $programUnit->id,
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

        Livewire::actingAs($leader)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->assertOk()
            ->set('exemptionJustification', 'Intento no autorizado')
            ->call('markExempt')
            ->assertForbidden();
    }

    public function test_cannot_mark_an_already_approved_evidence_as_exempt(): void
    {
        $evidence = $this->evidenceFor();
        $evidence->update(['status' => EvidenceStatus::Approved]);
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('exemptionJustification', 'Intento no válido')
            ->call('markExempt')
            ->assertForbidden();
    }

    public function test_administrator_can_remove_an_exemption(): void
    {
        $evidence = $this->evidenceFor();
        $evidence->update(['status' => EvidenceStatus::Exempt]);
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->call('removeExemption')
            ->assertHasNoErrors();

        $this->assertEquals(EvidenceStatus::Pending, $evidence->fresh()->status);
        $this->assertTrue(AuditLog::where('action', 'evidence_exemption_removed')->where('auditable_id', $evidence->id)->exists());
    }

    /**
     * Revisión del estado Exento: el docente dueño también puede quitar
     * su propia exención — antes solo Administrador/Coordinación podían.
     * `exemption_reason` debe limpiarse al revertir.
     */
    public function test_teacher_can_remove_their_own_exemption(): void
    {
        $evidence = $this->evidenceFor();
        $evidence->update(['status' => EvidenceStatus::Exempt, 'exemption_reason' => 'Motivo original.']);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->call('removeExemption')
            ->assertHasNoErrors();

        $evidence->refresh();
        $this->assertEquals(EvidenceStatus::Pending, $evidence->status);
        $this->assertNull($evidence->exemption_reason);
    }

    public function test_leader_cannot_remove_an_exemption(): void
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
            'program_unit_id' => $programUnit->id,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ]);

        $evidence = Evidence::factory()->create([
            'user_id' => $teacher->id,
            'status' => EvidenceStatus::Exempt,
            'exemption_reason' => 'Motivo original.',
            'deliverable_id' => Deliverable::factory()->create([
                'academic_period_id' => $period->id,
                'activity_id' => $activity->id,
            ])->id,
        ]);

        Livewire::actingAs($leader)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->assertOk()
            ->call('removeExemption')
            ->assertForbidden();
    }

    /**
     * Visibilidad de la razón: cualquiera que pueda ver la evidencia
     * (docente, líder en su ámbito, Coordinación) debe ver el motivo
     * cuando está Exenta — antes no se mostraba a nadie.
     */
    public function test_exemption_reason_is_visible_to_the_teacher_when_exempt(): void
    {
        $evidence = $this->evidenceFor();
        $evidence->update(['status' => EvidenceStatus::Exempt, 'exemption_reason' => 'Carga académica adicional este periodo.']);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->assertSee('Carga académica adicional este periodo.');
    }

    public function test_exemption_reason_is_visible_to_the_leader_in_scope_when_exempt(): void
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
            'program_unit_id' => $programUnit->id,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
        ]);

        $evidence = Evidence::factory()->create([
            'user_id' => $teacher->id,
            'status' => EvidenceStatus::Exempt,
            'exemption_reason' => 'Un percance familiar.',
            'deliverable_id' => Deliverable::factory()->create([
                'academic_period_id' => $period->id,
                'activity_id' => $activity->id,
            ])->id,
        ]);

        Livewire::actingAs($leader)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->assertSee('Un percance familiar.');
    }

    /**
     * Estado bloqueado, mensaje claro: quien tiene el rol para eximir
     * (aquí, el propio docente) pero está en un estado no elegible debe
     * ver una explicación — no que la sección de exención simplemente
     * desaparezca sin decir por qué.
     */
    public function test_teacher_sees_a_clear_message_when_their_submitted_evidence_cannot_be_exempted(): void
    {
        $evidence = $this->evidenceFor();
        $evidence->update(['status' => EvidenceStatus::Submitted]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->assertSee('No puedes marcarte exento mientras esta evidencia esté Enviada o Aprobada');
    }

    /**
     * Bloque de ajustes de interfaz, punto 7: "Quitar exención" tampoco
     * tenía ningún estado de carga.
     */
    public function test_remove_exemption_button_disables_itself_and_shows_a_loading_state(): void
    {
        $evidence = $this->evidenceFor();
        $evidence->update(['status' => EvidenceStatus::Exempt]);
        $admin = $this->userWithRole(RoleName::Administrator);

        $html = Livewire::actingAs($admin)->test(EvidenceWorkspace::class, ['evidence' => $evidence])->html();

        $this->assertStringContainsString('wire:target="removeExemption"', $html);
        $this->assertStringContainsString('wire:loading.attr="disabled"', $html);
        $this->assertStringContainsString('Quitando...', $html);
    }

    public function test_cannot_remove_exemption_from_an_evidence_that_is_not_exempt(): void
    {
        $evidence = $this->evidenceFor();
        $admin = $this->userWithRole(RoleName::Administrator);

        Livewire::actingAs($admin)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->call('removeExemption')
            ->assertForbidden();
    }

    public static function nonActivePeriodStatuses(): array
    {
        return [
            'en planeación' => [AcademicPeriodStatus::Planning],
            'cerrado' => [AcademicPeriodStatus::Closed],
            'archivado' => [AcademicPeriodStatus::Archived],
        ];
    }

    /**
     * RF-009 nombra 4 estados de periodo; el documento solo detalla
     * "cerrado". Se resolvió que un docente solo puede guardar borrador,
     * adjuntar archivos o enviar mientras el periodo está Activo — en
     * cualquier otro estado (planeación, cerrado, archivado) se bloquea.
     */
    #[DataProvider('nonActivePeriodStatuses')]
    public function test_cannot_save_draft_when_period_is_not_active(AcademicPeriodStatus $status): void
    {
        $period = AcademicPeriod::factory()->create(['status' => $status]);
        $evidence = $this->evidenceFor(['academic_period_id' => $period->id]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->call('saveDraft')
            ->assertSet('submissionError', fn ($message) => str_contains($message, 'No es posible cargar evidencias'));

        $this->assertNull($evidence->fresh()->current_version_id);
    }

    public function test_cannot_submit_when_period_is_in_planning(): void
    {
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Planning]);
        $evidence = $this->evidenceFor([
            'academic_period_id' => $period->id,
            'allowed_evidence_types' => [EvidenceType::Text->value],
        ]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('description', 'Contenido de prueba')
            ->call('submit');

        $this->assertEquals(EvidenceStatus::Pending, $evidence->fresh()->status);
    }

    public function test_cannot_attach_a_file_when_period_is_in_planning(): void
    {
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Planning]);
        $evidence = $this->evidenceFor(['academic_period_id' => $period->id]);
        $file = UploadedFile::fake()->create('propuesta.pdf', 100, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->assertHasErrors('newFiles')
            ->assertSet('newFiles', []);
    }

    public function test_cannot_add_a_link_when_period_is_in_planning(): void
    {
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Planning]);
        $evidence = $this->evidenceFor([
            'academic_period_id' => $period->id,
            'allowed_evidence_types' => [EvidenceType::Link->value],
        ]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newLinkUrl', 'https://example.com')
            ->call('addLink')
            ->assertHasErrors('newLinkUrl');

        $this->assertNull($evidence->fresh()->current_version_id);
    }

    /**
     * Bloque de ajustes de interfaz, punto 3: `newLinkLabel` ya tenía su
     * regla `max:255`, pero la plantilla solo mostraba el @error() de
     * `newLinkUrl` — un error de etiqueta quedaba invisible. Confirma que
     * el mensaje aparece en el HTML, no solo en el error bag.
     */
    public function test_a_too_long_link_label_shows_its_own_visible_error(): void
    {
        $evidence = $this->evidenceFor(['allowed_evidence_types' => [EvidenceType::Link->value]]);

        $component = Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newLinkUrl', 'https://example.com')
            ->set('newLinkLabel', str_repeat('a', 256))
            ->call('addLink')
            ->assertHasErrors('newLinkLabel');

        $this->assertStringContainsString('no debe tener más de 255 caracteres', $component->html());
    }

    public function test_saving_a_draft_and_submitting_works_normally_when_period_is_active(): void
    {
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $evidence = $this->evidenceFor(['academic_period_id' => $period->id]);
        $file = UploadedFile::fake()->create('propuesta.pdf', 100, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertEquals(EvidenceStatus::Submitted, $evidence->fresh()->status);
    }

    /**
     * Reproduce el error real reportado: la subida falla en el endpoint
     * interno de Livewire (/livewire/upload-file, ej. el archivo excede el
     * límite propio de Livewire) ANTES de que newFiles llegue a asignarse
     * — WithFileUploads::_uploadErrored() deja el mensaje bajo la key
     * indexada 'newFiles.0' (nunca pasa por nuestro updatedNewFiles()).
     * Sin el fix, resetErrorBag('newFiles') no limpia esa key y el
     * mensaje seguía visible al adjuntar un archivo válido después.
     */
    public function test_upload_endpoint_error_clears_when_a_valid_file_is_attached_afterward(): void
    {
        $evidence = $this->evidenceFor(['allowed_file_types' => ['pdf']]);

        $errorsInJson = json_encode([
            'message' => 'The files.0 field failed to upload.',
            'errors' => ['files.0' => ['No se pudo subir el archivo del campo files.0.']],
        ]);

        $test = Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->call('_uploadErrored', 'newFiles', $errorsInJson, true);

        $test->assertHasErrors('newFiles.0');

        $valid = UploadedFile::fake()->create('propuesta.pdf', 100, 'application/pdf');

        $test->set('newFiles', [$valid])
            ->assertHasNoErrors()
            ->assertSee('propuesta.pdf');
    }

    public function test_mimes_validation_message_names_the_field_in_spanish_not_the_literal_key(): void
    {
        $evidence = $this->evidenceFor(['allowed_file_types' => ['pdf']]);
        $file = UploadedFile::fake()->create('documento.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $test = Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file]);

        $this->assertSame(
            'El campo archivo debe ser un archivo de tipo: pdf.',
            $test->instance()->getErrorBag()->first('newFiles')
        );
    }
}
