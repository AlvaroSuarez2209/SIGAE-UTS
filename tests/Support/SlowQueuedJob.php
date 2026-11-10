<?php

namespace Tests\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job de prueba deliberadamente lento — solo para comprobar que
 * App\Services\Mail\QueueFlusher::flush() respeta su --max-time:
 * "queue:work" revisa el tiempo transcurrido ANTES de tomar el
 * siguiente job (nunca interrumpe uno ya en curso), así que un primer
 * job que tarda más que el presupuesto deja el resto sin tocar.
 */
class SlowQueuedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $seconds) {}

    public function handle(): void
    {
        sleep($this->seconds);
    }
}
