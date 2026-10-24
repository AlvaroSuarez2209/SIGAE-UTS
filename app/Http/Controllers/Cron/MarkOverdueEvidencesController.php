<?php

namespace App\Http\Controllers\Cron;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Mismo patrón y mismo secreto compartido que
 * App\Http\Controllers\Cron\ProcessQueueController (mismo cron-job.org,
 * un segundo job apuntando aquí): evidences:mark-overdue depende del
 * scheduler de Laravel (routes/console.php), que a su vez necesita un
 * cron de sistema operativo — Render, en su plan gratuito, no lo ofrece.
 * Sin este endpoint, nada marca las evidencias vencidas salvo correrlo a
 * mano.
 */
class MarkOverdueEvidencesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('cron.secret');
        $token = $request->query('token') ?? $request->header('X-Cron-Secret');

        if (blank($secret) || ! is_string($token) || ! hash_equals($secret, $token)) {
            abort(403);
        }

        Artisan::call('evidences:mark-overdue');

        return response()->json([
            'ok' => true,
            'output' => trim(Artisan::output()),
        ]);
    }
}
