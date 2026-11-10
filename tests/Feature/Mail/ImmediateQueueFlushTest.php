<?php

namespace Tests\Feature\Mail;

use App\Livewire\Auth\ForgotPassword;
use App\Models\Deliverable;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\Mail\QueueFlusher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Support\SlowQueuedJob;
use Tests\TestCase;

/**
 * Cambio: vaciado inmediato de la cola de correo tras cualquier acción
 * que la encole (ver App\Services\Mail\QueueFlusher), en vez de
 * depender de la próxima pasada de /cron/process-queue. withoutDefer()
 * (Illuminate\Foundation\Testing\Concerns\InteractsWithContainer) hace
 * que defer() corra de inmediato en vez de esperar al final del
 * request — necesario aquí para poder comprobar el resultado sin
 * depender de si el kernel HTTP de prueba invoca sus callbacks
 * diferidos por su cuenta.
 */
class ImmediateQueueFlushTest extends TestCase
{
    use RefreshDatabase;

    /**
     * phpunit.xml fija QUEUE_CONNECTION=sync para el resto de la suite
     * (los jobs corren inline, nunca tocan la tabla "jobs") — pero todo
     * este archivo prueba justo el comportamiento real de la cola de
     * base de datos, así que necesita la conexión real aquí.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'database']);
    }

    public function test_an_individual_user_action_no_longer_depends_on_the_cron(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'docente@uts.edu.co']);

        $this->withoutDefer();

        Livewire::test(ForgotPassword::class)
            ->set('email', $user->email)
            ->call('sendResetLink');

        // La notificación quedó encolada Y YA se procesó — sin correr
        // "queue:work" a mano ni esperar al cron.
        $this->assertDatabaseCount('jobs', 0);
    }

    /**
     * A propósito, NO usa withoutDefer() aquí: esa ayuda de prueba hace
     * que defer() corra de inmediato en cada llamada, lo que evitaría
     * probar justo lo que importa — que las 5 llamadas dentro del bucle
     * (mismo nombre 'mail-queue-flush') colapsan a UN solo vaciado real
     * al final, no a 5. En su lugar, se deja que se acumulen como en un
     * request real y se invoca la colección una sola vez al final,
     * exactamente lo que hace el middleware InvokeDeferredCallbacks de
     * Laravel al terminar de verdad un request HTTP.
     */
    public function test_a_batch_of_several_recipients_goes_out_in_a_single_flush(): void
    {
        Mail::fake();

        $deliverable = Deliverable::factory()->create();
        $recipients = User::factory()->count(5)->create();

        $deliverable->ensureEvidencesForRecipients($recipients->pluck('id')->all());

        $this->assertDatabaseCount('evidences', 5);
        $this->assertDatabaseCount('jobs', 5);

        app(DeferredCallbackCollection::class)->invoke();

        // Las 5 notificaciones (una por destinatario) se encolaron
        // durante el bucle y se procesaron todas en ESTE único
        // vaciado — defer() con el mismo nombre colapsó las 5 llamadas
        // a una sola corrida real de "queue:work" (ver
        // QueueFlusher::deferFlush()).
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_when_the_max_time_is_exhausted_the_rest_stays_queued(): void
    {
        SlowQueuedJob::dispatch(2);
        SlowQueuedJob::dispatch(2);

        // Presupuesto de 1s: el primer job (2s) ya lo agota, así que
        // "queue:work" nunca llega a tomar el segundo.
        $ran = QueueFlusher::flush(1);

        $this->assertTrue($ran);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_a_failure_during_the_flush_never_breaks_the_user_action(): void
    {
        Mail::fake();

        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:work', ['--stop-when-empty' => true, '--max-time' => 10])
            ->andThrow(new \RuntimeException('fallo simulado del vaciado'));

        $user = User::factory()->create(['email' => 'docente2@uts.edu.co']);

        $this->withoutDefer();

        Livewire::test(ForgotPassword::class)
            ->set('email', $user->email)
            ->call('sendResetLink')
            ->assertSet('status', fn ($status) => filled($status));

        // El fallo del vaciado quedó contenido (QueueFlusher lo
        // reporta, no lo relanza) — la notificación sigue encolada,
        // intacta, para que /cron/process-queue la recoja después.
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_no_duplicate_sends_when_a_flush_is_already_in_progress(): void
    {
        $alreadyRunning = Cache::lock('mail-queue-flush', 30);
        $this->assertTrue($alreadyRunning->get());

        $user = User::factory()->create();
        $user->notify(new ResetPasswordNotification('un-token'));

        $this->assertDatabaseCount('jobs', 1);

        $ran = QueueFlusher::flush();

        $this->assertFalse($ran);
        // Nadie tocó el job: ni se procesó dos veces, ni se perdió —
        // sigue esperando al próximo vaciado (el que ya está en curso,
        // o uno futuro).
        $this->assertDatabaseCount('jobs', 1);

        $alreadyRunning->release();
    }

    public function test_the_cron_still_drains_whatever_is_left_pending(): void
    {
        config(['cron.secret' => 'el-secreto']);
        Mail::fake();

        $user = User::factory()->create();
        $user->notify(new ResetPasswordNotification('otro-token'));

        $this->assertDatabaseCount('jobs', 1);

        $this->get('/cron/process-queue?token=el-secreto')
            ->assertOk()
            ->assertJson(['ok' => true, 'ran' => true]);

        $this->assertDatabaseCount('jobs', 0);
    }
}
