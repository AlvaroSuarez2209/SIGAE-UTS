<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Punto 1 (almacenamiento persistente) + anexo: storage:check corre
 * desde docker/start.sh en cada arranque, sin consola disponible en
 * Render free — tiene que reportar con claridad si el disco de
 * soportes configurado de verdad funciona, y nunca lanzar una
 * excepción sin capturar (eso tumbaría el arranque pese al "|| true").
 */
class StorageCheckTest extends TestCase
{
    public function test_reports_success_and_leaves_no_trace_when_the_disk_works(): void
    {
        Storage::fake('local');

        $this->artisan('storage:check')
            ->assertSuccessful();

        Storage::disk('local')->assertDirectoryEmpty('_storage_check');
    }

    /**
     * Un disco mal configurado (nombre inexistente) no debe lanzar una
     * excepción sin capturar — storage:check debe reportarlo con
     * claridad y devolver un código de error, nada más.
     */
    public function test_reports_failure_without_throwing_when_the_disk_is_misconfigured(): void
    {
        config(['filesystems.attachments_disk' => 'disco-que-no-existe']);

        $this->artisan('storage:check')
            ->assertFailed();
    }
}
