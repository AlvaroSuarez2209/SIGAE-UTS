<?php

namespace App\Http\Controllers\Evidence;

use App\Http\Controllers\Controller;
use App\Models\EvidenceFile;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceFileDownloadController extends Controller
{
    public function __invoke(EvidenceFile $evidenceFile): StreamedResponse
    {
        Gate::authorize('view', $evidenceFile);

        return $evidenceFile->disk()->download($evidenceFile->disk_path, $evidenceFile->original_name);
    }
}
