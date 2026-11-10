<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Cambio: vaciado inmediato de la cola de correo, para que una
 * notificación encolada salga en segundos en vez de esperar a la
 * próxima pasada de /cron/process-queue (cada 2-5 minutos en
 * producción). Nunca reemplaza ese cron: sigue siendo el respaldo para
 * lo que no alcance a salir aquí (lotes grandes) y para los reintentos
 * de jobs ya fallidos — ver docs/manual-tecnico.md §8.
 *
 * Cache::lock evita dos vaciados en paralelo (p. ej. una acción de
 * usuario y /cron/process-queue al mismo tiempo). Esto es una capa
 * extra, no la única protección: `Illuminate\Queue\DatabaseQueue::pop()`
 * ya reserva cada fila con `reserved_at` dentro de una transacción, así
 * que dos `queue:work` corriendo a la vez nunca podrían tomar el mismo
 * job de todas formas — el lock solo evita el costo de arrancar dos
 * procesos de cola por nada, no corrige un hueco real de duplicidad.
 */
class QueueFlusher
{
    private const LOCK_NAME = 'mail-queue-flush';

    /**
     * Registra el vaciado para después de responder al usuario —
     * nombre fijo ('mail-queue-flush') a propósito: si el mismo
     * request encola correo más de una vez (p. ej. un bucle de
     * importación masiva), Illuminate\Support\Defer\DeferredCallbackCollection
     * descarta los duplicados por nombre y solo corre uno al final,
     * en vez de un vaciado por cada fila del lote. ->always(): el
     * correo ya quedó encolado (fila ya comprometida en la base) antes
     * de llegar aquí, así que el vaciado debe intentarse igual aunque
     * algo después, en el mismo request, termine en error.
     */
    public static function deferFlush(): void
    {
        defer(fn () => self::flush(), name: self::LOCK_NAME, always: true);
    }

    /**
     * Corre `queue:work --stop-when-empty` de verdad, acotado a
     * $maxSeconds (por defecto, config('queue_flush.immediate_flush_seconds')).
     * Nunca lanza: un fallo de envío o del propio vaciado solo queda
     * registrado (`report()`), nunca rompe la acción que lo disparó —
     * la notificación sigue en `jobs` (o pasa a `failed_jobs` con su
     * reintento normal de Laravel) para que /cron/process-queue la
     * recoja después.
     *
     * @return bool true si de verdad corrió queue:work, false si otro
     *              vaciado ya tenía el lock (ver clase) y este se saltó.
     */
    public static function flush(?int $maxSeconds = null): bool
    {
        $maxSeconds ??= config('queue_flush.immediate_flush_seconds');

        $lock = Cache::lock(self::LOCK_NAME, $maxSeconds + 5);

        if (! $lock->get()) {
            return false;
        }

        try {
            Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--max-time' => $maxSeconds,
            ]);
        } catch (Throwable $e) {
            report($e);
        } finally {
            $lock->release();
        }

        return true;
    }
}
