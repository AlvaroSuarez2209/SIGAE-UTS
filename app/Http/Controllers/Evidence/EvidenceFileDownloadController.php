<?php

namespace App\Http\Controllers\Evidence;

use App\Http\Controllers\Controller;
use App\Models\EvidenceFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceFileDownloadController extends Controller
{
    /**
     * El disco de Render (plan gratuito) es efímero — un redeploy puede
     * dejar la fila en la base sin su archivo físico. Sin esta
     * verificación, Storage::download() deja pasar la excepción cruda de
     * Flysystem (UnableToRetrieveMetadata) como un 500 sin explicación.
     */
    public function __invoke(EvidenceFile $evidenceFile): StreamedResponse
    {
        Gate::authorize('view', $evidenceFile);

        if (! $evidenceFile->existsOnDisk()) {
            Log::warning('Descarga de evidence_file con archivo físico faltante', ['evidence_file_id' => $evidenceFile->id]);

            abort(404, 'El archivo ya no está disponible. Es posible que se haya perdido tras un reinicio del servidor.');
        }

        return $evidenceFile->disk()->download($evidenceFile->disk_path, $evidenceFile->original_name);
    }
}
