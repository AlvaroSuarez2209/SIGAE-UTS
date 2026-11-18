<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Correcciones a las vistas, punto 2: "body" solo tenía min-h-screen, sin
 * overflow — con suficiente contenido vertical dentro de <main>, el
 * documento quedaba scrolleable además del contenedor interno (ver
 * layouts/app.blade.php), desplazando visualmente la barra lateral. Solo
 * la sesión autenticada (el "app shell" con sidebar fijo) necesita
 * overflow-hidden en "body" — la pantalla de login nunca tuvo este
 * problema y no debe perder su scroll normal.
 */
class LayoutScrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_authenticated_layout_hides_overflow_on_body(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/<body class="[^"]*\boverflow-hidden\b[^"]*">/',
            $response->getContent()
        );
    }

    /**
     * /login usa layouts.guest, un <body> totalmente aparte (nunca tuvo
     * el bug ni se tocó) — esta prueba solo confirma que overflow-hidden
     * se quedó condicionado a la sesión autenticada en layouts/app.blade.php
     * y no se filtró a la pantalla de login por error.
     */
    public function test_guest_login_page_keeps_normal_document_overflow(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $this->assertDoesNotMatchRegularExpression(
            '/<body class="[^"]*\boverflow-hidden\b[^"]*">/',
            $response->getContent()
        );
    }
}
