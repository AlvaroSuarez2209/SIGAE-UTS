<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Token del cron externo de colas
    |--------------------------------------------------------------------------
    |
    | Protege GET /cron/process-queue (ver routes/web.php y
    | App\Http\Controllers\Cron\ProcessQueueController) — el punto de
    | entrada que un cron externo (cron-job.org) usa para procesar la cola
    | en un despliegue sin worker persistente (Render, plan gratuito).
    | Sin este valor configurado, el endpoint rechaza toda solicitud
    | (falla cerrado, nunca abierto) — ver ProcessQueueController.
    |
    */

    'secret' => env('CRON_SECRET'),

];
