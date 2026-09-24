<?php

namespace App\Http\Controllers\Cron;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Sustituye a un worker de colas persistente (`php artisan queue:work` en
 * segundo plano) en un despliegue donde no existe tal cosa — Render, en su
 * plan gratuito, no ofrece background workers. Un cron externo
 * (cron-job.org) golpea esta ruta cada pocos minutos con el token
 * correcto; `--stop-when-empty` hace que el comando termine solo si no
 * queda nada pendiente, y `--max-time=50` lo corta de todas formas antes
 * de que el request HTTP entero pueda agotar el límite de tiempo de la
 * plataforma.
 *
 * Deliberadamente fuera de los grupos de middleware auth/role
 * (routes/web.php): quien la llama es un servicio externo sin sesión de
 * usuario, nunca una persona autenticada — su único control de acceso es
 * el token compartido (config/cron.php), comparado con hash_equals() para
 * evitar timing attacks.
 */
class ProcessQueueController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('cron.secret');
        $token = $request->query('token') ?? $request->header('X-Cron-Secret');

        // blank($secret) hace que esto falle cerrado si CRON_SECRET no se
        // configuró en el entorno — nunca "cualquier token vacío pasa".
        if (blank($secret) || ! is_string($token) || ! hash_equals($secret, $token)) {
            abort(403);
        }

        Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--max-time' => 50,
        ]);

        return response()->json([
            'ok' => true,
            'output' => trim(Artisan::output()),
        ]);
    }
}
