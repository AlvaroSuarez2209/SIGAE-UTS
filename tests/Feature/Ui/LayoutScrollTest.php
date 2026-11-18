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

    /**
     * Correcciones a las vistas, punto 3 (el selector de archivos recortaba
     * el layout): overflow-hidden en "body" no bastaba porque el navegador
     * puede seguir moviendo document.documentElement.scrollTop por código
     * (confirmado con Playwright al enfocar un <input type="file"> oculto),
     * sin importar overflow:hidden/clip. La única forma de que no haya
     * nada que desplazar es que <main> nunca tenga overflow de documento
     * que medir: pasa de "flex-1" (un hijo más del flex-col, su tamaño
     * final dependía del reparto del flexbox) a position:absolute con
     * límites propios e inamovibles, dentro de un contenedor ahora
     * position:relative.
     */
    public function test_main_content_area_is_absolutely_positioned_not_flex_sized(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '/<div class="relative flex h-screen[^"]*">/',
            $response->getContent()
        );
        $this->assertMatchesRegularExpression(
            '/<main class="absolute inset-x-0 bottom-0[^"]*">/',
            $response->getContent()
        );
    }
}
