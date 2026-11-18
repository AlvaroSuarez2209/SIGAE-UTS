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
use App\Models\DeliverableReferenceFile;
use App\Models\Evidence;
use App\Models\EvidenceFile;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\Role;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
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

    /**
     * Punto 9 de la revisión de la directora: comentario opcional del
     * docente al enviar — se guarda en la versión, pero la auditoría solo
     * registra que se incluyó uno (nunca su texto).
     */
    public function test_submitting_with_a_comment_saves_it_and_audits_only_whether_one_was_included(): void
    {
        $evidence = $this->evidenceFor();
        $file = UploadedFile::fake()->create('propuesta.pdf', 100, 'application/pdf');
        $comment = 'Aquí está mi evidencia completa, cualquier duda me escriben.';

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->set('submissionComment', $comment)
            ->call('submit')
            ->assertHasNoErrors();

        $evidence->refresh();
        $this->assertEquals($comment, $evidence->currentVersion->submission_comment);

        $log = AuditLog::where('action', 'evidence_submitted')->where('auditable_id', $evidence->id)->first();
        $this->assertNotNull($log);
        $this->assertTrue($log->metadata['has_comment']);
        $this->assertStringNotContainsString($comment, json_encode($log->metadata));
    }

    public function test_submitting_without_a_comment_behaves_like_before(): void
    {
        $evidence = $this->evidenceFor();
        $file = UploadedFile::fake()->create('propuesta.pdf', 100, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->call('submit')
            ->assertHasNoErrors();

        $evidence->refresh();
        $this->assertEquals(EvidenceStatus::Submitted, $evidence->status);
        $this->assertNull($evidence->currentVersion->submission_comment);

        $log = AuditLog::where('action', 'evidence_submitted')->where('auditable_id', $evidence->id)->first();
        $this->assertNotNull($log);
        $this->assertFalse($log->metadata['has_comment']);
    }

    public function test_a_comment_over_2000_characters_is_rejected(): void
    {
        $evidence = $this->evidenceFor();

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('submissionComment', str_repeat('a', 2001))
            ->call('saveDraft')
            ->assertHasErrors('submissionComment');
    }

    /**
     * Cuadro de seguimiento, punto 23: el campo de comentario no tenía
     * <label for> ni aria-describedby/aria-invalid.
     */
    public function test_the_comment_field_has_an_accessible_label_and_error_wiring(): void
    {
        $evidence = $this->evidenceFor();

        $html = Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('submissionComment', str_repeat('a', 2001))
            ->call('saveDraft')
            ->assertHasErrors('submissionComment')
            ->html();

        $this->assertStringContainsString('for="submissionComment"', $html);
        $this->assertStringContainsString('id="submissionComment"', $html);
        $this->assertStringContainsString('aria-describedby="submissionComment-error"', $html);
        $this->assertStringContainsString('role="alert"', $html);
    }

    /**
     * Seguimiento al punto 9: el docente también debe ver su propio
     * comentario en un bloque de solo lectura dentro de su workspace —
     * antes solo quedaba visible para el líder en review-show.blade.php.
     */
    public function test_teacher_sees_their_own_comment_in_a_read_only_block_after_submitting(): void
    {
        $evidence = $this->evidenceFor(['allowed_evidence_types' => [EvidenceType::Text->value]]);
        $comment = "Primera línea.\nSegunda línea.";

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('description', 'Mi evidencia')
            ->set('submissionComment', $comment)
            ->call('submit')
            ->assertSee('Comentario:')
            ->assertSee('Primera línea.')
            ->assertSee('Segunda línea.');
    }

    public function test_the_read_only_comment_block_does_not_appear_without_a_comment(): void
    {
        $evidence = $this->evidenceFor(['allowed_evidence_types' => [EvidenceType::Text->value]]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('description', 'Mi evidencia')
            ->call('submit')
            ->assertDontSee('Comentario:');
    }

    /**
     * Tras una devolución, currentVersion sigue siendo la versión YA
     * enviada (con su comentario original) hasta la primera edición real
     * — el textarea para el nuevo envío debe arrancar vacío, no arrastrar
     * el texto de la versión anterior. Ese comentario anterior sigue
     * siendo consultable (ya no en un bloque aparte: ahora en la fila de
     * su propia versión dentro del historial unificado).
     */
    public function test_reopening_after_a_return_starts_the_comment_textarea_empty(): void
    {
        $evidence = $this->evidenceFor(['allowed_evidence_types' => [EvidenceType::Text->value]]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('description', 'Primera versión')
            ->set('submissionComment', 'Comentario de la primera entrega.')
            ->call('submit');

        $evidence->refresh();
        $evidence->update(['status' => EvidenceStatus::NeedsAdjustment]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->assertSet('submissionComment', '')
            ->assertSee('Versiones anteriores')
            ->assertSee('Comentario de la primera entrega.');
    }

    public function test_unrelated_teacher_cannot_view_the_comment_of_someone_elses_evidence(): void
    {
        $evidence = $this->evidenceFor();
        $version = $evidence->startOrGetDraftVersion($evidence->user);
        $version->update(['submission_comment' => 'Comentario privado del docente.']);
        $otherTeacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($otherTeacher)
            ->get('/my-deliverables/'.$evidence->id)
            ->assertForbidden();
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

    public function test_removing_a_file_deletes_it_from_disk_when_no_other_row_uses_the_same_path(): void
    {
        $evidence = $this->evidenceFor();
        $file = UploadedFile::fake()->create('a.pdf', 50, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->call('saveDraft');

        $evidenceFile = $evidence->fresh()->currentVersion->files->first();
        Storage::disk('local')->assertExists($evidenceFile->disk_path);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->call('removeFile', $evidenceFile->id);

        $this->assertDatabaseMissing('evidence_files', ['id' => $evidenceFile->id]);
        Storage::disk('local')->assertMissing($evidenceFile->disk_path);
    }

    /**
     * Revisión de perfil Administrador, punto 4 (condición adicional):
     * hoy existen filas de evidence_files en producción que comparten un
     * mismo disk_path por un motivo externo a este código (ver
     * EvidenceFile::isDiskPathReferencedElsewhere()). Si se borrara el
     * archivo físico solo porque ESTA fila ya no lo necesita, se dejaría
     * sin archivo a otra evidencia que todavía lo usa.
     */
    public function test_removing_a_file_keeps_the_physical_file_when_another_evidence_file_row_shares_its_path(): void
    {
        $evidence = $this->evidenceFor();
        $file = UploadedFile::fake()->create('a.pdf', 50, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->call('saveDraft');

        $evidenceFile = $evidence->fresh()->currentVersion->files->first();
        Storage::disk('local')->assertExists($evidenceFile->disk_path);

        // Otra fila (de otra evidencia, otra versión) apunta al mismo
        // disk_path — anomalía real ya observada en producción, no una
        // situación que el flujo normal de subida pueda producir.
        $otherEvidence = Evidence::factory()
            ->for(Deliverable::factory()->create(['academic_period_id' => $evidence->deliverable->academic_period_id]))
            ->create();
        $otherVersion = $otherEvidence->startOrGetDraftVersion($otherEvidence->user);

        $sharedRow = EvidenceFile::create([
            'evidence_version_id' => $otherVersion->id,
            'original_name' => 'compartido.pdf',
            'stored_name' => 'otro-nombre-distinto.pdf',
            'disk_path' => $evidenceFile->disk_path,
            'mime_type' => 'application/pdf',
            'size_bytes' => $evidenceFile->size_bytes,
        ]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->call('removeFile', $evidenceFile->id);

        $this->assertDatabaseMissing('evidence_files', ['id' => $evidenceFile->id]);
        // El archivo físico sigue intacto: $sharedRow todavía lo necesita.
        Storage::disk('local')->assertExists($evidenceFile->disk_path);

        $this->assertDatabaseHas('evidence_files', ['id' => $sharedRow->id]);
    }

    /**
     * Mismo criterio, pero con la otra tabla de archivos que comparte el
     * disco ('local'): deliverable_reference_files.
     */
    public function test_removing_a_file_keeps_the_physical_file_when_a_deliverable_reference_file_shares_its_path(): void
    {
        $evidence = $this->evidenceFor();
        $file = UploadedFile::fake()->create('a.pdf', 50, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->call('saveDraft');

        $evidenceFile = $evidence->fresh()->currentVersion->files->first();

        DeliverableReferenceFile::create([
            'deliverable_id' => $evidence->deliverable->id,
            'original_name' => 'formato.pdf',
            'stored_name' => 'otro-nombre-de-referencia.pdf',
            'disk_path' => $evidenceFile->disk_path,
            'mime_type' => 'application/pdf',
            'size_bytes' => $evidenceFile->size_bytes,
            'is_active' => true,
        ]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->call('removeFile', $evidenceFile->id);

        $this->assertDatabaseMissing('evidence_files', ['id' => $evidenceFile->id]);
        Storage::disk('local')->assertExists($evidenceFile->disk_path);
    }

    public function test_removing_a_link_deletes_it(): void
    {
        $evidence = $this->evidenceFor(['allowed_evidence_types' => [EvidenceType::Link->value]]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newLinkUrl', 'https://example.com/a')
            ->call('addLink');

        $link = $evidence->fresh()->currentVersion->links->first();

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->call('removeLink', $link->id);

        $this->assertDatabaseMissing('evidence_links', ['id' => $link->id]);
    }

    /**
     * Mismo bug que removeFile() (punto 4): tras una devolución,
     * current_version_id sigue apuntando a la versión 1 — YA enviada y
     * con su enlace ya revisado — hasta la primera edición real. Antes de
     * este fix, removeLink() buscaba el enlace en currentVersion
     * directamente y lo borraba aunque la evidencia estuviera "reabierta"
     * sin ninguna edición todavía, dañando el historial de versiones.
     */
    public function test_removing_a_link_right_after_a_return_never_touches_the_already_submitted_versions_link(): void
    {
        $evidence = $this->evidenceFor(['allowed_evidence_types' => [EvidenceType::Link->value]]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newLinkUrl', 'https://example.com/v1')
            ->call('addLink')
            ->call('submit');

        $evidence->refresh();
        $firstVersion = $evidence->versions()->where('version_number', 1)->first();
        $firstLink = $firstVersion->links->first();

        // El líder devuelve la evidencia (lo hará el módulo 7; aquí solo se
        // simula el efecto sobre el estado) — current_version_id sigue
        // apuntando a la versión 1 porque el docente todavía no hizo
        // ninguna edición real tras la reapertura.
        $evidence->update(['status' => EvidenceStatus::NeedsAdjustment]);
        $this->assertEquals($firstVersion->id, $evidence->fresh()->current_version_id);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->call('removeLink', $firstLink->id);

        // El enlace de la versión 1 sigue intacto: el historial de
        // versiones no se corrompió.
        $this->assertDatabaseHas('evidence_links', ['id' => $firstLink->id]);
        $this->assertEquals('https://example.com/v1', $firstVersion->fresh()->links->first()->url);
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
     * Cuadro de seguimiento, punto 18: saveDraft()/addLink() ya exigían
     * acceptsEvidenceSubmissions() (periodo Activo) para escribir, pero
     * removeFile() nunca lo comprobaba — un docente podía quitar un
     * archivo ya guardado (y, si nadie más comparte su disk_path, borrar
     * el archivo físico) de un borrador cuyo periodo ya cerró o se
     * archivó, aunque adjuntar uno nuevo ya estuviera bloqueado. El
     * archivo se adjunta mientras el periodo todavía está Activo; el
     * periodo cierra/se archiva después, y solo entonces se intenta
     * quitarlo.
     */
    #[DataProvider('nonActivePeriodStatuses')]
    public function test_cannot_remove_a_file_once_the_period_is_no_longer_active(AcademicPeriodStatus $status): void
    {
        $evidence = $this->evidenceFor();
        $file = UploadedFile::fake()->create('a.pdf', 50, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->call('saveDraft');

        $evidenceFile = $evidence->fresh()->currentVersion->files->first();
        $evidence->deliverable->academicPeriod->update(['status' => $status]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->call('removeFile', $evidenceFile->id);

        $this->assertDatabaseHas('evidence_files', ['id' => $evidenceFile->id]);
        Storage::disk('local')->assertExists($evidenceFile->disk_path);
    }

    /**
     * Mismo punto 18, mismo bug en removeLink() — un docente podía
     * borrar un enlace ya guardado de un borrador cuyo periodo ya cerró
     * o se archivó.
     */
    #[DataProvider('nonActivePeriodStatuses')]
    public function test_cannot_remove_a_link_once_the_period_is_no_longer_active(AcademicPeriodStatus $status): void
    {
        $evidence = $this->evidenceFor(['allowed_evidence_types' => [EvidenceType::Link->value]]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newLinkUrl', 'https://example.com')
            ->call('addLink');

        $link = $evidence->fresh()->currentVersion->links->first();
        $evidence->deliverable->academicPeriod->update(['status' => $status]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->call('removeLink', $link->id);

        $this->assertDatabaseHas('evidence_links', ['id' => $link->id]);
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

    /**
     * Cuadro de seguimiento, punto 2: caso feliz — el dueño descarga un
     * archivo que sí existe en disco. Toda la cobertura previa de
     * EvidenceFileDownloadController probaba el 404 (archivo ausente) y
     * el 403 (acceso no autorizado), pero nunca el 200 con el contenido
     * real.
     */
    public function test_downloading_an_existing_evidence_file_succeeds_with_its_real_content(): void
    {
        $evidence = $this->evidenceFor();
        $file = UploadedFile::fake()->create('evidencia.pdf', 100, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->call('saveDraft');

        $evidenceFile = $evidence->fresh()->currentVersion->files->first();

        $response = $this->actingAs($evidence->user)
            ->get(route('evidence-files.download', $evidenceFile));

        $response->assertOk();
        $this->assertStringContainsString('evidencia.pdf', $response->headers->get('content-disposition'));
        $this->assertEquals(
            Storage::disk('local')->get($evidenceFile->disk_path),
            $response->streamedContent()
        );
    }

    /**
     * El disco de Render es efímero — un redeploy puede dejar la fila de
     * evidence_files en la base sin su archivo físico. Antes de esta
     * prueba, Storage::download() dejaba pasar la excepción cruda de
     * Flysystem como un 500 sin explicación (ver
     * EvidenceFileDownloadController). Debe ser un 404 controlado, en
     * español, con su propio registro en el log — sin datos personales,
     * solo el id de la fila.
     */
    public function test_downloading_an_evidence_file_whose_physical_file_is_missing_returns_a_controlled_404(): void
    {
        Log::spy();

        $evidence = $this->evidenceFor();
        $file = UploadedFile::fake()->create('evidencia.pdf', 100, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->call('saveDraft');

        $evidenceFile = $evidence->fresh()->currentVersion->files->first();
        Storage::disk('local')->delete($evidenceFile->disk_path);

        $response = $this->actingAs($evidence->user)
            ->get(route('evidence-files.download', $evidenceFile));

        $response->assertNotFound();
        $this->assertSame(
            'El archivo ya no está disponible. Es posible que se haya perdido tras un reinicio del servidor.',
            $response->exception->getMessage()
        );

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn ($message, $context) => $context === ['evidence_file_id' => $evidenceFile->id]);
    }

    /**
     * Prepara una evidencia con 2 versiones reales: la versión 1 se envía
     * con un archivo y el líder la devuelve (queda NeedsAdjustment, sin
     * crear versión nueva todavía); la versión 2 arranca como el nuevo
     * borrador actual al volver a guardar. Devuelve [$evidence, $teacher,
     * $leader, $oldFile] — $oldFile es el archivo de la versión 1 (ya no
     * la actual).
     *
     * @return array{0: Evidence, 1: User, 2: User, 3: EvidenceFile}
     */
    private function evidenceWithAReturnedPreviousVersion(): array
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

        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'allowed_evidence_types' => [EvidenceType::File->value],
            'allowed_file_types' => ['pdf'],
            'max_files' => 1,
            'max_file_size_mb' => 5,
        ]);
        $evidence = Evidence::factory()->create([
            'deliverable_id' => $deliverable->id,
            'user_id' => $teacher->id,
            'status' => EvidenceStatus::Pending,
        ]);

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [UploadedFile::fake()->create('v1.pdf', 50, 'application/pdf')])
            ->call('submit');

        $evidence->refresh();
        $oldFile = $evidence->currentVersion->files->first();

        $evidence->returnCurrentReviewForAdjustment($leader, 'Ajusta el documento.');

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->call('saveDraft');

        $evidence->refresh();

        return [$evidence, $teacher, $leader, $oldFile];
    }

    public function test_the_owning_teacher_sees_a_download_link_for_files_of_a_previous_version(): void
    {
        [$evidence, $teacher, , $oldFile] = $this->evidenceWithAReturnedPreviousVersion();

        $this->assertNotEquals($evidence->current_version_id, $oldFile->evidence_version_id);

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->assertSee('Versiones anteriores')
            ->assertSee(route('evidence-files.download', $oldFile), false);
    }

    /**
     * evidence_version_id=5 real en Neon (evidencia id=4 del despliegue):
     * la versión 1 nunca tuvo ninguna fila en evidence_files — no es que
     * el archivo se haya perdido del disco, nunca existió uno. El
     * historial debe decirlo explícitamente en vez de no mostrar nada.
     */
    public function test_a_previous_version_with_no_files_shows_a_clear_empty_state(): void
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

        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'allowed_evidence_types' => [EvidenceType::Text->value],
        ]);
        $evidence = Evidence::factory()->create([
            'deliverable_id' => $deliverable->id,
            'user_id' => $teacher->id,
            'status' => EvidenceStatus::Pending,
        ]);

        // Versión 1: enviada solo con texto, sin ningún archivo adjunto.
        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('description', 'Avance descrito en texto, sin archivo adjunto.')
            ->call('submit');

        $evidence->refresh();
        $this->assertCount(0, $evidence->currentVersion->files);

        $evidence->returnCurrentReviewForAdjustment($leader, 'Ajusta el documento.');
        $evidence->startOrGetDraftVersion($teacher);

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->assertSee('Versiones anteriores')
            ->assertSee('Sin archivos adjuntos.');
    }

    /**
     * Archivo que sí quedó registrado en la base pero cuyo físico ya no
     * existe (disco efímero de Render) — debe avisarse, no ocultarse.
     */
    public function test_a_previous_version_with_a_missing_physical_file_shows_a_warning(): void
    {
        [$evidence, $teacher, , $oldFile] = $this->evidenceWithAReturnedPreviousVersion();

        Storage::disk('local')->delete($oldFile->disk_path);

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->assertSee('Versiones anteriores')
            ->assertSee($oldFile->original_name)
            ->assertSee('Archivo no disponible')
            ->assertDontSee(route('evidence-files.download', $oldFile), false);
    }

    public function test_an_unrelated_teacher_cannot_download_a_file_from_a_previous_version(): void
    {
        [, , , $oldFile] = $this->evidenceWithAReturnedPreviousVersion();
        $otherTeacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($otherTeacher)
            ->get(route('evidence-files.download', $oldFile))
            ->assertForbidden();
    }

    public function test_the_review_list_shows_the_version_number_and_the_reviewer_name(): void
    {
        [$evidence, $teacher, $leader] = $this->evidenceWithAReturnedPreviousVersion();

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->assertSee('Versión 1')
            ->assertSee($leader->name);
    }

    /**
     * Ajuste al historial de versiones, punto 1: la versión actual (un
     * borrador en edición, tras "Guardar borrador") no debe aparecer como
     * una tarjeta más dentro de "Versiones anteriores" — solo la línea de
     * contexto de arriba la menciona.
     */
    public function test_version_history_never_repeats_the_current_version_as_a_card(): void
    {
        [$evidence, $teacher] = $this->evidenceWithAReturnedPreviousVersion();

        $html = Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->html();

        $this->assertSame(1, substr_count($html, '(actual)'));
        $this->assertStringNotContainsString('Versión 2 —', $html);
    }

    /**
     * Ajuste al historial de versiones, punto 1: con una sola versión (sin
     * ninguna anterior), el historial lo dice explícitamente.
     */
    public function test_a_single_version_shows_the_first_version_message(): void
    {
        $evidence = $this->evidenceFor();

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [UploadedFile::fake()->create('v1.pdf', 50, 'application/pdf')])
            ->call('submit')
            ->assertSee('Esta es la primera versión.');
    }

    /**
     * Ajuste al historial de versiones, puntos 1 y 2: con 3 versiones
     * (2 anteriores, ya revisadas, más la actual en edición), "Versiones
     * anteriores" las lista de la más reciente a la más antigua, cada una
     * con su insignia de estado, revisor, fecha y observación.
     */
    public function test_previous_versions_are_listed_newest_first_with_status_reviewer_date_and_observation(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $leaderA = $this->userWithRole(RoleName::Leader);
        $leaderB = $this->userWithRole(RoleName::Leader);
        $period = AcademicPeriod::factory()->create(['status' => AcademicPeriodStatus::Active]);
        $activity = Activity::factory()->create();
        $programUnit = ProgramUnit::factory()->create();

        TeacherAssignment::factory()->create([
            'user_id' => $teacher->id,
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'program_unit_id' => $programUnit->id,
        ]);
        foreach ([$leaderA, $leaderB] as $leader) {
            Leadership::factory()->create([
                'user_id' => $leader->id,
                'academic_period_id' => $period->id,
                'activity_id' => null,
                'program_unit_id' => $programUnit->id,
                'starts_at' => now()->subMonth(),
                'ends_at' => null,
            ]);
        }

        $deliverable = Deliverable::factory()->create([
            'academic_period_id' => $period->id,
            'activity_id' => $activity->id,
            'allowed_evidence_types' => [EvidenceType::Text->value],
        ]);
        $evidence = Evidence::factory()->create([
            'deliverable_id' => $deliverable->id,
            'user_id' => $teacher->id,
            'status' => EvidenceStatus::Pending,
        ]);

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('description', 'Primera versión')
            ->call('submit');
        $evidence->refresh();
        $evidence->returnCurrentReviewForAdjustment($leaderA, 'Ajusta la versión 1.');

        $v2 = $evidence->startOrGetDraftVersion($teacher);
        $v2->update(['description' => 'Segunda versión']);
        $evidence->submitCurrentVersion();
        $evidence->returnCurrentReviewForAdjustment($leaderB, 'Ajusta la versión 2.');

        $evidence->startOrGetDraftVersion($teacher);

        $html = Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->html();

        // Versión 2 (más reciente de las anteriores) antes que la versión 1.
        $posV2 = strpos($html, 'Versión 2 —');
        $posV1 = strpos($html, 'Versión 1 —');
        $this->assertNotFalse($posV2);
        $this->assertNotFalse($posV1);
        $this->assertLessThan($posV1, $posV2);

        $this->assertStringContainsString('Devuelto', $html);
        $this->assertStringContainsString($leaderA->name, $html);
        $this->assertStringContainsString($leaderB->name, $html);
        $this->assertStringContainsString('Observación de '.$leaderA->name.':</span> Ajusta la versión 1.', $html);
        $this->assertStringContainsString('Observación de '.$leaderB->name.':</span> Ajusta la versión 2.', $html);

        // La observación de cada versión aparece después de su propio
        // encabezado, nunca antes (cada tarjeta trae su propia revisión).
        $this->assertGreaterThan($posV1, strpos($html, 'Observación de '.$leaderA->name));
        $this->assertGreaterThan($posV2, strpos($html, 'Observación de '.$leaderB->name));
    }

    public function test_submitting_before_the_closing_date_succeeds(): void
    {
        $evidence = $this->evidenceFor(['closes_at' => now()->addDay()]);
        $file = UploadedFile::fake()->create('a-tiempo.pdf', 50, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->call('submit')
            ->assertSet('submissionError', '');

        $this->assertEquals(EvidenceStatus::Submitted, $evidence->fresh()->status);
    }

    /**
     * Ajustes al rediseño visual, punto 9: closes_at ahora bloquea desde
     * updatedNewFiles()/saveDraft(), no solo en el último paso de
     * submit() — el archivo nunca llega a adjuntarse (updatedNewFiles()
     * ya lo rechaza) y el estado se queda en Pendiente, no en Borrador
     * (antes, saveDraft() sí alcanzaba a crear la versión con el
     * archivo, y solo el envío final quedaba bloqueado).
     */
    public function test_submitting_after_the_closing_date_is_blocked_with_a_clear_message(): void
    {
        $evidence = $this->evidenceFor(['closes_at' => now()->subDay()]);
        $file = UploadedFile::fake()->create('tarde.pdf', 50, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->call('submit')
            ->assertSet('submissionError', 'El plazo de envío de este entregable cerró el '.$evidence->deliverable->closes_at->toReadable().'.')
            ->assertSet('newFiles', []);

        $this->assertEquals(EvidenceStatus::Pending, $evidence->fresh()->status);
    }

    /**
     * Sin fecha de cierre configurada (closes_at nulo, el campo es
     * opcional), el envío nunca debe bloquearse por esta regla —
     * Deliverable::isPastClosingDate() debe devolver false siempre que
     * closes_at sea null, no tratarlo como "ya cerrado".
     */
    public function test_submitting_without_a_closing_date_configured_is_never_blocked_by_this_rule(): void
    {
        $evidence = $this->evidenceFor(['closes_at' => null]);
        $file = UploadedFile::fake()->create('sin-cierre.pdf', 50, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->call('submit')
            ->assertSet('submissionError', '');

        $this->assertEquals(EvidenceStatus::Submitted, $evidence->fresh()->status);
    }

    /**
     * Ajustes al rediseño visual, punto 9: antes de este ajuste, "Tu
     * entrega" mostraba la zona de carga y los botones Guardar/Enviar
     * como si todo siguiera activo, aunque closes_at ya hubiera pasado
     * — el único bloqueo real ocurría recién al pulsar "Enviar". Ahora
     * la tarjeta pasa a solo lectura en el mismo momento en que deja de
     * ser posible enviar.
     */
    public function test_the_editable_form_is_hidden_once_the_closing_date_has_passed(): void
    {
        $evidence = $this->evidenceFor(['closes_at' => now()->subDay()]);

        $html = Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->html();

        $this->assertStringNotContainsString('wire:model="newFiles"', $html);
        $this->assertStringNotContainsString('Guardar borrador', $html);
        $this->assertStringNotContainsString('Enviar evidencia', $html);
    }

    public function test_adding_a_link_after_the_closing_date_is_blocked_with_a_clear_message(): void
    {
        $evidence = $this->evidenceFor([
            'closes_at' => now()->subDay(),
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
     * Excepción deliberada: una corrección pedida por el líder no debe
     * quedar varada solo porque el plazo original ya pasó — ver el
     * docblock de Deliverable::isPastClosingDate(). El formulario
     * editable (y no solo el botón Enviar) debe seguir disponible en
     * este caso.
     */
    public function test_resubmitting_after_a_return_is_allowed_even_past_the_closing_date(): void
    {
        [$evidence, $teacher] = $this->evidenceWithAReturnedPreviousVersion();
        $evidence->deliverable->update(['closes_at' => now()->subDay()]);

        $file = UploadedFile::fake()->create('v2.pdf', 50, 'application/pdf');

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->call('submit')
            ->assertSet('submissionError', '');

        $this->assertEquals(EvidenceStatus::Submitted, $evidence->fresh()->status);
    }

    // --- Punto 5: material de referencia (enlace + archivo) en el espacio de trabajo ---

    public function test_shows_the_reference_material_card_with_the_link_domain_opening_in_a_new_tab(): void
    {
        $evidence = $this->evidenceFor(['reference_url' => 'https://uts.edu.co/referencia.pdf']);

        $html = Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->html();

        $this->assertStringContainsString('Material de referencia', $html);
        $this->assertStringContainsString('uts.edu.co', $html);
        $this->assertStringContainsString('href="https://uts.edu.co/referencia.pdf"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    public function test_does_not_show_the_reference_material_card_without_a_link_or_reference_files(): void
    {
        $evidence = $this->evidenceFor();

        $html = Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->html();

        $this->assertStringNotContainsString('Material de referencia', $html);
    }

    public function test_an_unassigned_teacher_cannot_see_or_reach_the_reference_material(): void
    {
        $evidence = $this->evidenceFor(['reference_url' => 'https://uts.edu.co/referencia.pdf']);
        $otherTeacher = $this->userWithRole(RoleName::Teacher);

        $this->actingAs($otherTeacher)
            ->get('/my-deliverables/'.$evidence->id)
            ->assertForbidden();
    }

    /**
     * Mismo criterio "Archivo no disponible" que ya usa DeliverableForm
     * — antes de este punto, EvidenceWorkspace mostraba siempre el
     * enlace de descarga sin comprobar existsOnDisk().
     */
    public function test_shows_archivo_no_disponible_when_the_reference_files_physical_file_is_missing(): void
    {
        $evidence = $this->evidenceFor();
        $referenceFile = $evidence->deliverable->attachReferenceFile(
            UploadedFile::fake()->create('instructivo.pdf', 100, 'application/pdf'),
            $evidence->user
        );
        Storage::disk('local')->delete($referenceFile->disk_path);

        $html = Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->html();

        $this->assertStringContainsString('instructivo.pdf', $html);
        $this->assertStringContainsString('Archivo no disponible', $html);
        $this->assertStringNotContainsString(route('deliverable-reference-files.download', $referenceFile), $html);
    }

    /**
     * Rediseño visual del encabezado: antes leía
     * $deliverable->activity?->is_mandatory (el campo de la Actividad,
     * solo un valor por defecto al crear) en vez de
     * $deliverable->is_mandatory (el campo real, el mismo que ya lee
     * correctamente review-show.blade.php) — corregido como parte de
     * este LOTE. Confirma que la insignia sigue al campo correcto incluso
     * cuando difieren, y que ahora sí aparece en un entregable
     * transversal (antes nunca podía, al no tener actividad).
     */
    public function test_the_mandatory_badge_follows_the_deliverables_own_field_not_the_activitys(): void
    {
        $mandatoryDeliverableOnOptionalActivity = $this->evidenceFor([
            'is_mandatory' => true,
            'activity_id' => Activity::factory()->create(['is_mandatory' => false])->id,
        ]);

        Livewire::actingAs($mandatoryDeliverableOnOptionalActivity->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $mandatoryDeliverableOnOptionalActivity])
            ->assertSee('Obligatoria');

        $optionalDeliverableOnMandatoryActivity = $this->evidenceFor([
            'is_mandatory' => false,
            'activity_id' => Activity::factory()->create(['is_mandatory' => true])->id,
        ]);

        Livewire::actingAs($optionalDeliverableOnMandatoryActivity->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $optionalDeliverableOnMandatoryActivity])
            ->assertDontSee('Obligatoria');
    }

    public function test_the_mandatory_badge_can_now_appear_on_a_cross_cutting_deliverable(): void
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $deliverable = Deliverable::factory()->crossCutting()->create(['is_mandatory' => true]);
        $evidence = Evidence::factory()->create([
            'deliverable_id' => $deliverable->id,
            'user_id' => $teacher->id,
            'status' => EvidenceStatus::Pending,
        ]);

        Livewire::actingAs($teacher)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->assertSee('Obligatoria');
    }

    /**
     * Rediseño visual, franja de estado contextual:
     * EvidenceWorkspace::statusMessage() envuelve el mensaje base de
     * EvidenceStatus::teacherStatusMessage() con el contexto real del
     * periodo y las fechas — para no prometer algo que la pantalla no
     * permite.
     */
    #[DataProvider('nonActivePeriodStatuses')]
    public function test_status_message_warns_about_the_period_instead_of_the_default_message_when_not_active(AcademicPeriodStatus $periodStatus): void
    {
        $evidence = $this->evidenceFor();
        $evidence->deliverable->academicPeriod->update(['status' => $periodStatus]);

        $html = Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->html();

        $this->assertStringContainsString(e("No puedes enviarla mientras el periodo esté en estado \"{$periodStatus->label()}\"."), $html);
        $this->assertStringNotContainsString('Complétala antes de la fecha límite', $html);
    }

    /**
     * Misma franja, pero para una evidencia Devuelta: reenviar tras
     * devolución sí se permite con el periodo no activo? No — ese caso
     * también exige acceptsEvidenceSubmissions() (ver
     * EvidenceWorkspace::saveDraft()), así que el mensaje de periodo
     * inactivo aplica igual aquí, no solo a Pendiente/Borrador.
     */
    public function test_status_message_warns_about_the_period_for_a_returned_evidence_too(): void
    {
        $evidence = $this->evidenceFor();
        $evidence->update(['status' => EvidenceStatus::NeedsAdjustment]);
        $evidence->deliverable->academicPeriod->update(['status' => AcademicPeriodStatus::Closed]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->assertSee('No puedes enviarla mientras el periodo esté en estado "Cerrado".')
            ->assertDontSee('Corrígela y vuelve a enviarla');
    }

    /**
     * Vencida: EvidenceStatus::isEditable() ya excluye este estado de los
     * editables, así que saveDraft()/submit() la bloquean sin importar si
     * closes_at ya pasó o no — el mensaje nunca promete que "todavía se
     * puede enviar hasta el cierre" (hallazgo reportado aparte, no
     * corregido en este LOTE).
     */
    public function test_status_message_for_expired_never_mentions_the_closing_date(): void
    {
        $evidence = $this->evidenceFor(['closes_at' => now()->addMonth()]);
        $evidence->update(['status' => EvidenceStatus::Expired]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->assertSee('El plazo venció y ya no es posible enviarla.')
            ->assertDontSee('todavía puedes enviarla');
    }

    public function test_status_message_for_pending_past_the_closing_date_never_says_before_the_due_date(): void
    {
        $evidence = $this->evidenceFor(['due_at' => now()->subDays(2), 'closes_at' => now()->subDay()]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->assertSee('El plazo de envío de este entregable cerró el '.$evidence->deliverable->closes_at->toReadable().'.')
            ->assertDontSee('Complétala antes de la fecha límite');
    }

    /**
     * A diferencia del caso anterior, una evidencia Devuelta sí puede
     * reenviarse pasado el cierre (ver submit()) — su mensaje por
     * defecto sigue siendo correcto y no debe sobrescribirse.
     */
    /**
     * blockedByClosingDate() comprueba si HUBO alguna vez una revisión
     * real con decisión Devuelto — no solo si el estado actual dice
     * "NeedsAdjustment" — para que la excepción solo aplique a una
     * corrección real pedida por el líder, nunca a un estado puesto a
     * mano sin ese antecedente.
     */
    public function test_status_message_for_needs_adjustment_past_the_closing_date_keeps_the_default_message(): void
    {
        $evidence = $this->evidenceFor(['due_at' => now()->subDays(2), 'closes_at' => now()->subDay()]);
        $version = $evidence->startOrGetDraftVersion($evidence->user);
        \App\Models\Review::factory()->create([
            'evidence_version_id' => $version->id,
            'decision' => \App\Enums\ReviewDecision::Returned,
        ]);
        $evidence->update(['status' => EvidenceStatus::NeedsAdjustment]);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->assertSee('Devuelta para ajustes. Corrígela y vuelve a enviarla.')
            ->assertDontSee('El plazo de envío de este entregable cerró');
    }

    public function test_status_message_shows_the_default_text_for_approved_and_exempt(): void
    {
        $approved = $this->evidenceFor();
        $approved->update(['status' => EvidenceStatus::Approved]);

        Livewire::actingAs($approved->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $approved->fresh()])
            ->assertSee('Aprobada. No es necesario hacer nada más.');

        $exempt = $this->evidenceFor();
        $exempt->update(['status' => EvidenceStatus::Exempt]);

        Livewire::actingAs($exempt->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $exempt->fresh()])
            ->assertSee('Marcada como exenta. No necesitas enviarla.');
    }

    /**
     * Ajustes al rediseño visual, punto 1: "Volver" pasa de texto plano a
     * botón secundario con ícono en el encabezado.
     */
    public function test_volver_is_a_secondary_button_with_an_icon_in_the_header(): void
    {
        $evidence = $this->evidenceFor();

        $html = Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->assertSee('Guardar borrador')
            ->html();

        $this->assertStringContainsString('btn-secondary', $html);
        $this->assertMatchesRegularExpression('/btn-secondary[^"]*"[^>]*>\s*<svg/s', $html);
    }

    /**
     * Correcciones a las vistas, punto 1: un único "Volver" por pantalla
     * (el del encabezado) — "Tu entrega" ya no tiene uno propio junto a
     * Guardar borrador/Enviar evidencia.
     */
    public function test_the_submission_card_has_no_own_volver_button(): void
    {
        $evidence = $this->evidenceFor();

        $html = Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->assertSee('Guardar borrador')
            ->html();

        $this->assertSame(1, substr_count($html, 'Volver'));
    }

    /**
     * Ajustes al rediseño visual, punto 3: la fila de datos bajo el
     * título ahora también muestra el programa y, si ya se envió, la
     * fecha de envío (antes solo aparecían en la vista del líder).
     */
    public function test_data_row_shows_the_program_and_the_submission_date(): void
    {
        $evidence = $this->evidenceFor();
        TeacherAssignment::factory()->create([
            'user_id' => $evidence->user_id,
            'academic_period_id' => $evidence->deliverable->academic_period_id,
            'activity_id' => $evidence->deliverable->activity_id,
            'program_unit_id' => ProgramUnit::factory()->create(['name' => 'Programa De Prueba']),
        ]);
        $evidence->update(['status' => EvidenceStatus::Submitted]);
        $version = $evidence->startOrGetDraftVersion($evidence->user);
        $version->update(['submitted_at' => now(), 'description' => 'Contenido de prueba']);

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence->fresh()])
            ->assertSee('Programa De Prueba')
            ->assertSee('Enviado:');
    }

    /**
     * Ajustes al rediseño visual, punto 5: el recuadro de estado cambia
     * de color según el estado — mismo tono que ya usa <x-status-badge>.
     */
    public function test_status_message_box_changes_color_tone_by_status(): void
    {
        $approved = $this->evidenceFor();
        $approved->update(['status' => EvidenceStatus::Approved]);

        $approvedHtml = Livewire::actingAs($approved->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $approved->fresh()])
            ->html();
        $this->assertStringContainsString('bg-status-success-subtle', $approvedHtml);

        $returned = $this->evidenceFor();
        $returned->update(['status' => EvidenceStatus::NeedsAdjustment]);

        $returnedHtml = Livewire::actingAs($returned->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $returned->fresh()])
            ->html();
        $this->assertStringContainsString('bg-status-warning-subtle', $returnedHtml);
    }

    /**
     * Ajustes al rediseño visual, punto 6: el panel de exención pasa a
     * una sección plegada, cerrada por defecto (sin el atributo HTML
     * "open"), con el nuevo título, y el botón de exentar ya no es
     * btn-warning (relleno) sino btn-secondary (contorno). Validación y
     * permisos sin cambios.
     */
    public function test_the_exemption_panel_is_a_closed_by_default_disclosure_with_a_secondary_button(): void
    {
        $evidence = $this->evidenceFor();

        $html = Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->html();

        $this->assertStringContainsString('¿No puedes realizar esta entrega?', $html);

        // Sin defaultOpenOnDesktop, <x-disclosure> no añade x-data/:open
        // en absoluto — el <details> queda en su estado nativo, cerrado
        // por defecto, sin el atributo "open".
        preg_match('/<details[^>]*>\s*<summary[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*¿No puedes realizar esta entrega\?/s', $html, $matches);
        $this->assertNotEmpty($matches, 'No se encontró el <details> de la sección de exención.');
        $this->assertStringNotContainsString(' open', $matches[0]);

        // La validación y el gate siguen intactos: sin justificación, sigue fallando igual que antes.
        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('exemptionJustification', '')
            ->call('markExempt')
            ->assertHasErrors('exemptionJustification');
    }
}
