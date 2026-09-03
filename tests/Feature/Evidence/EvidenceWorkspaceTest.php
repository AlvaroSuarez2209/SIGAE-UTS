<?php

namespace Tests\Feature\Evidence;

use App\Enums\EvidenceStatus;
use App\Enums\EvidenceType;
use App\Enums\RoleName;
use App\Livewire\Evidence\EvidenceWorkspace;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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

    private function evidenceFor(array $deliverableAttributes = []): Evidence
    {
        $teacher = $this->userWithRole(RoleName::Teacher);
        $deliverable = Deliverable::factory()->create(array_merge([
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

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->call('saveDraft')
            ->assertHasErrors('newFiles.0');

        $this->assertEquals(EvidenceStatus::Pending, $evidence->fresh()->status);
    }

    public function test_uploaded_file_must_not_exceed_the_max_size(): void
    {
        $evidence = $this->evidenceFor(['max_file_size_mb' => 1]);
        $file = UploadedFile::fake()->create('grande.pdf', 2000, 'application/pdf');

        Livewire::actingAs($evidence->user)
            ->test(EvidenceWorkspace::class, ['evidence' => $evidence])
            ->set('newFiles', [$file])
            ->call('saveDraft')
            ->assertHasErrors('newFiles.0');
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
}
