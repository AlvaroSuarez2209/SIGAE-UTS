<?php

namespace Tests\Feature\Console;

use App\Models\Deliverable;
use App\Models\DeliverableReferenceFile;
use App\Models\EvidenceFile;
use App\Models\EvidenceVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Punto 1 (almacenamiento persistente): storage:migrate-to-s3 copia al
 * disco de soportes configurado los archivos que solo existen en
 * "local" — --dry-run es el comportamiento por defecto (nunca escribe
 * nada sin --apply) y nunca sobrescribe un archivo que ya exista en el
 * destino.
 */
class StorageMigrateLocalToS3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('target');
    }

    private function evidenceFileAt(string $path): EvidenceFile
    {
        $version = EvidenceVersion::factory()->create();

        return EvidenceFile::create([
            'evidence_version_id' => $version->id,
            'original_name' => basename($path),
            'stored_name' => basename($path),
            'disk_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
        ]);
    }

    private function referenceFileAt(string $path): DeliverableReferenceFile
    {
        return DeliverableReferenceFile::create([
            'deliverable_id' => Deliverable::factory()->create()->id,
            'original_name' => basename($path),
            'stored_name' => basename($path),
            'disk_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'uploaded_by' => User::factory()->create()->id,
        ]);
    }

    public function test_reports_a_no_op_when_the_configured_disk_is_still_local(): void
    {
        config(['filesystems.attachments_disk' => 'local']);

        $this->artisan('storage:migrate-to-s3')
            ->expectsOutputToContain('ya es "local"')
            ->assertSuccessful();
    }

    public function test_dry_run_is_the_default_and_never_writes_to_the_target_disk(): void
    {
        config(['filesystems.attachments_disk' => 'target']);
        Storage::disk('local')->put('evidence/1/a.pdf', 'contenido');
        $this->evidenceFileAt('evidence/1/a.pdf');

        $this->artisan('storage:migrate-to-s3')
            ->expectsOutputToContain('dry-run')
            ->assertSuccessful();

        Storage::disk('target')->assertMissing('evidence/1/a.pdf');
    }

    public function test_apply_copies_the_file_to_the_target_disk_keeping_the_same_disk_path(): void
    {
        config(['filesystems.attachments_disk' => 'target']);
        Storage::disk('local')->put('evidence/1/a.pdf', 'contenido real');
        $file = $this->evidenceFileAt('evidence/1/a.pdf');

        $this->artisan('storage:migrate-to-s3 --apply')
            ->assertSuccessful();

        Storage::disk('target')->assertExists($file->disk_path);
        $this->assertSame('contenido real', Storage::disk('target')->get($file->disk_path));
        // disk_path nunca cambia: ninguna fila necesita actualizarse.
        $this->assertSame('evidence/1/a.pdf', $file->fresh()->disk_path);
    }

    public function test_never_overwrites_a_file_already_present_on_the_target_disk(): void
    {
        config(['filesystems.attachments_disk' => 'target']);
        Storage::disk('local')->put('evidence/1/a.pdf', 'version local');
        Storage::disk('target')->put('evidence/1/a.pdf', 'version que ya estaba en el bucket');
        $this->evidenceFileAt('evidence/1/a.pdf');

        $this->artisan('storage:migrate-to-s3 --apply')
            ->expectsOutputToContain('ya existe en el disco destino')
            ->assertSuccessful();

        $this->assertSame('version que ya estaba en el bucket', Storage::disk('target')->get('evidence/1/a.pdf'));
    }

    public function test_skips_a_row_whose_physical_file_no_longer_exists_locally(): void
    {
        config(['filesystems.attachments_disk' => 'target']);
        $this->evidenceFileAt('evidence/1/perdido.pdf');

        $this->artisan('storage:migrate-to-s3 --apply')
            ->expectsOutputToContain('no existe en el disco local')
            ->assertSuccessful();

        Storage::disk('target')->assertMissing('evidence/1/perdido.pdf');
    }

    public function test_covers_both_evidence_files_and_deliverable_reference_files(): void
    {
        config(['filesystems.attachments_disk' => 'target']);
        Storage::disk('local')->put('evidence/1/a.pdf', 'evidencia');
        Storage::disk('local')->put('deliverables/1/b.pdf', 'referencia');

        $this->evidenceFileAt('evidence/1/a.pdf');
        $this->referenceFileAt('deliverables/1/b.pdf');

        $this->artisan('storage:migrate-to-s3 --apply')->assertSuccessful();

        Storage::disk('target')->assertExists('evidence/1/a.pdf');
        Storage::disk('target')->assertExists('deliverables/1/b.pdf');
    }
}
