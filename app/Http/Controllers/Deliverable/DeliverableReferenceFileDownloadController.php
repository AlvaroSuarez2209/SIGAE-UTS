<?php

namespace App\Http\Controllers\Deliverable;

use App\Http\Controllers\Controller;
use App\Models\DeliverableReferenceFile;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mismo patrón que Evidence\EvidenceFileDownloadController: sin URL
 * pública, el disco 'local' no tiene symlink — toda descarga pasa por
 * aquí y verifica permisos en cada solicitud vía
 * DeliverableReferenceFilePolicy.
 */
class DeliverableReferenceFileDownloadController extends Controller
{
    public function __invoke(DeliverableReferenceFile $deliverableReferenceFile): StreamedResponse
    {
        Gate::authorize('view', $deliverableReferenceFile);

        return $deliverableReferenceFile->disk()->download(
            $deliverableReferenceFile->disk_path,
            $deliverableReferenceFile->original_name
        );
    }
}
