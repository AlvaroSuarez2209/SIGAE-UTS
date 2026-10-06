<?php

namespace Tests\Feature\Ui;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prueba directa sobre resources/views/components/flash-message.blade.php
 * (mismo enfoque que ReportAccessTest::test_pdf_template_shows_filters_summary...,
 * que también renderiza una plantilla compartida directamente en vez de
 * pasar por un componente Livewire completo) — es el único punto de
 * traducción de session('status')/session('error') a la notificación
 * visible, reutilizado por los layouts y por las pantallas que no
 * recargan la página completa al flashear (ver el docblock del propio
 * componente).
 */
class FlashMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_the_success_message_when_status_is_flashed(): void
    {
        session()->flash('status', 'Guardado correctamente.');

        $html = view('components.flash-message')->render();

        $this->assertStringContainsString('Guardado correctamente.', $html);
        $this->assertStringContainsString('bg-status-success-subtle', $html);
    }

    public function test_renders_the_error_message_when_error_is_flashed(): void
    {
        session()->flash('error', 'No se pudo generar el documento.');

        $html = view('components.flash-message')->render();

        $this->assertStringContainsString('No se pudo generar el documento.', $html);
        $this->assertStringContainsString('bg-status-error-subtle', $html);
    }

    public function test_renders_nothing_when_no_flash_is_present(): void
    {
        $html = view('components.flash-message')->render();

        $this->assertStringNotContainsString('bg-status-success-subtle', $html);
        $this->assertStringNotContainsString('bg-status-error-subtle', $html);
    }

    public function test_can_show_both_success_and_error_at_once_if_both_are_flashed(): void
    {
        session()->flash('status', 'Guardado correctamente.');
        session()->flash('error', 'Pero algo más falló.');

        $html = view('components.flash-message')->render();

        $this->assertStringContainsString('Guardado correctamente.', $html);
        $this->assertStringContainsString('Pero algo más falló.', $html);
    }
}
