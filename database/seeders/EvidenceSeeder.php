<?php

namespace Database\Seeders;

use App\Enums\EvidenceStatus;
use App\Enums\ReviewDecision;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\EvidenceVersion;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\Concerns\RefusesInProduction;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

class EvidenceSeeder extends Seeder
{
    use RefusesInProduction;

    public function run(): void
    {
        if ($this->abortIfProduction()) {
            return;
        }

        // Desactivado: dependía de las cuentas de demostración
        // (docente1@sigae.local, lider1@sigae.local) que UserSeeder ya no
        // crea — solo siembra un Administrador. Vuelve a activarse (junto
        // con TeacherAssignmentSeeder y LeadershipSeeder, que dependen de
        // las mismas cuentas) si se necesita un seed completo de demostración.
        /*
        $docente1 = User::where('email', 'docente1@sigae.local')->first();
        $lider1 = User::where('email', 'lider1@sigae.local')->first();

        $deliverable = Deliverable::where('name', 'Propuesta de trabajo de grado')->first();

        $evidence = Evidence::where('deliverable_id', $deliverable->id)
            ->where('user_id', $docente1->id)
            ->first();

        if (! $evidence || $evidence->versions()->exists()) {
            return;
        }

        // Escenario obligatorio de demostración: la evidencia pasa primero
        // por una devolución (con observación) y luego por una aprobación,
        // generando dos versiones reales.
        $versionOne = $evidence->startOrGetDraftVersion($docente1);
        $this->attachFakeFile($versionOne, 'propuesta_v1.pdf');
        $evidence->submitCurrentVersion();

        $returnReview = Review::create([
            'evidence_version_id' => $versionOne->id,
            'reviewer_id' => $lider1->id,
            'decision' => ReviewDecision::Returned,
            'decided_at' => now()->subDays(5),
        ]);
        $returnReview->observations()->create([
            'body' => 'Falta el cronograma de actividades y el aval del director. Por favor ajusta y reenvía.',
        ]);
        $evidence->update(['status' => EvidenceStatus::NeedsAdjustment]);

        $versionTwo = $evidence->startOrGetDraftVersion($docente1);
        $this->attachFakeFile($versionTwo, 'propuesta_v2.pdf');
        $evidence->submitCurrentVersion();

        $approveReview = Review::create([
            'evidence_version_id' => $versionTwo->id,
            'reviewer_id' => $lider1->id,
            'decision' => ReviewDecision::Approved,
            'decided_at' => now()->subDay(),
        ]);
        $approveReview->observations()->create([
            'body' => 'Cronograma y aval incluidos. Propuesta aprobada.',
        ]);
        $evidence->update(['status' => EvidenceStatus::Approved]);
        */
    }

    private function attachFakeFile(EvidenceVersion $version, string $name): void
    {
        $file = UploadedFile::fake()->create($name, 50, 'application/pdf');
        $storedPath = $file->store('evidence/'.$version->id, 'local');

        $version->files()->create([
            'original_name' => $name,
            'stored_name' => basename($storedPath),
            'disk_path' => $storedPath,
            'mime_type' => 'application/pdf',
            'size_bytes' => $file->getSize(),
        ]);
    }
}
