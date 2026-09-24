<?php

namespace Tests\Feature\Cron;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProcessQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_a_request_with_no_token(): void
    {
        Config::set('cron.secret', 'el-secreto');

        $this->get('/cron/process-queue')->assertForbidden();
    }

    public function test_rejects_a_request_with_the_wrong_token(): void
    {
        Config::set('cron.secret', 'el-secreto');

        $this->get('/cron/process-queue?token=incorrecto')->assertForbidden();
    }

    /**
     * Sin CRON_SECRET configurado, ni siquiera un token vacío debe pasar
     * — el endpoint falla cerrado, nunca abierto.
     */
    public function test_rejects_every_request_when_no_secret_is_configured(): void
    {
        Config::set('cron.secret', null);

        $this->get('/cron/process-queue?token=')->assertForbidden();
    }

    public function test_accepts_a_request_with_the_correct_token_without_any_authentication(): void
    {
        Config::set('cron.secret', 'el-secreto');

        // Sin actingAs() a propósito: quien llama es un cron externo, no
        // una persona con sesión — la ruta debe funcionar igual de
        // deslogueado.
        $this->get('/cron/process-queue?token=el-secreto')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_accepts_the_token_via_header_instead_of_the_query_string(): void
    {
        Config::set('cron.secret', 'el-secreto');

        $this->withHeader('X-Cron-Secret', 'el-secreto')
            ->get('/cron/process-queue')
            ->assertOk();
    }
}
