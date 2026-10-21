<?php

namespace Tests\Feature\Ui;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Fallo de diseño móvil: tocar el logo o el nombre "SIGAE-UTS" en la
 * barra superior móvil no navegaba al dashboard — la invocación de
 * <livewire:institution-brand-header> en esa barra (resources/views/layouts/
 * app.blade.php) pasaba :clickable="false", así que
 * InstitutionBrandHeader renderizaba un <span> en vez de un <a href>.
 * Se corrigió quitando ese override (clickable=true es el valor por
 * defecto del componente, el mismo que ya usa el sidebar de escritorio).
 *
 * El drawer móvil (el menú que abre el botón de hamburguesa) sigue sin
 * ser un enlace a propósito — no se tocó, el hallazgo era solo sobre la
 * barra superior — ver la aserción negativa al final.
 */
class MobileTopBarNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::where('name', $role->value)->first());

        return $user;
    }

    /**
     * @return iterable<string, array{RoleName}>
     */
    public static function everyRole(): iterable
    {
        yield 'administrator' => [RoleName::Administrator];
        yield 'coordination' => [RoleName::Coordination];
        yield 'leader' => [RoleName::Leader];
        yield 'teacher' => [RoleName::Teacher];
        yield 'auditor' => [RoleName::Auditor];
    }

    #[DataProvider('everyRole')]
    public function test_the_mobile_top_bar_logo_and_name_link_to_the_dashboard(RoleName $role): void
    {
        $user = $this->userWithRole($role);

        // La propia pantalla de "Mi perfil" sirve de punto de partida
        // neutral: cualquier rol puede verla, y no es el dashboard — así
        // la prueba confirma una navegación real, no que ya estuviéramos
        // ahí de entrada.
        $response = $this->actingAs($user)->get('/profile');
        $response->assertOk();

        $html = $response->getContent();

        // gap-2 identifica específicamente la instancia de la barra
        // superior móvil (resources/views/livewire/institution-brand-header.blade.php)
        // — el sidebar de escritorio usa el gap por defecto (gap-3) y no
        // comparte esta combinación exacta de clases.
        $this->assertStringContainsString(
            'href="'.route('dashboard').'" class="flex items-center gap-2 text-lg font-semibold text-brand-primary"',
            $html,
            'El logo/nombre de la barra superior móvil debe ser un enlace real al dashboard.'
        );
    }

    public function test_the_mobile_drawer_brand_block_remains_a_non_link_on_purpose(): void
    {
        $user = $this->userWithRole(RoleName::Administrator);

        $html = $this->actingAs($user)->get('/profile')->getContent();

        // Misma combinación de clases que el enlace, pero como <span> — es
        // el bloque del drawer (brand-sidebar-mobile-drawer), que
        // deliberadamente no se tocó en esta corrección.
        $this->assertStringContainsString(
            '<span class="flex items-center gap-3 text-lg font-semibold text-brand-primary">',
            $html
        );
    }
}
