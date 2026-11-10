<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vaciado inmediato de la cola tras encolar un correo
    |--------------------------------------------------------------------------
    |
    | Segundos máximos que App\Services\Mail\QueueFlusher::flush() deja
    | corriendo "queue:work --stop-when-empty" justo después de una
    | acción de usuario (vía defer(), ver QueueFlusher::deferFlush()) o
    | al terminar un comando de cron que encola correo
    | (evidences:mark-overdue). Lo que no salga en este tiempo lo
    | recoge /cron/process-queue como respaldo (sigue con su propio
    | máximo de 50s, sin cambios).
    |
    | No agrega un número de intentos nuevo: queue:work corre aquí sin
    | --tries, igual que /cron/process-queue hoy — usa el valor por
    | defecto de queue:work (o el $tries propio de cada notificación,
    | si lo tuviera; hoy ninguna lo define).
    |
    | 10s por defecto: alcanza para un lote de varias decenas de
    | destinatarios contra la API de Brevo y acota cuánto puede quedar
    | bloqueado el único proceso de `php artisan serve` en Render
    | mientras vacía (ver PHP_CLI_SERVER_WORKERS en docker/start.sh).
    |
    */

    'immediate_flush_seconds' => (int) env('MAIL_QUEUE_IMMEDIATE_FLUSH_SECONDS', 10),

];
