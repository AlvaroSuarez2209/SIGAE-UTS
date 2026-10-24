<?php

namespace App\Http\Controllers\Deliverable;

use App\Http\Controllers\Controller;
use App\Models\DeliverableReferenceFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mismo patrón que Evidence\EvidenceFileDownloadController: sin URL
 * pública, el disco 'local' no tiene symlink — toda descarga pasa por
 * aquí y verifica permisos en cada solicitud vía
 * DeliverableReferenceFilePolicy. También comparte la misma verificación
 * de existencia física — el disco de Render es efímero y puede dejar la
 * fila sin su archivo tras un redeploy.
 */
class DeliverableReferenceFileDownloadController extends Controller
{
    public function __invoke(DeliverableReferenceFile $deliverableReferenceFile): StreamedResponse
    {
        Gate::authorize('view', $deliverableReferenceFile);

        if (! $deliverableReferenceFile->existsOnDisk()) {
            Log::warning('Descarga de deliverable_reference_file con archivo físico faltante', ['deliverable_reference_file_id' => $deliverableReferenceFile->id]);

            abort(404, 'El archivo ya no está disponible. Es posible que se haya perdido tras un reinicio del servidor.');
        }

        return $deliverableReferenceFile->disk()->download(
            $deliverableReferenceFile->disk_path,
            $deliverableReferenceFile->original_name
        );
    }
}
