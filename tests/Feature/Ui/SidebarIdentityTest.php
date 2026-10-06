<?php

namespace Tests\Feature\Ui;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El bloque de identidad del sidebar mostraba los roles del usuario con
 * `truncate` — heredado de un ancho pensado para un solo rol corto
 * ("Administrador"), cortaba con "…" combinaciones más largas como
 * "Líder, Docente".
 */
class SidebarIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_sidebar_shows_all_role_labels_without_truncating(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach(Role::whereIn('name', [
            RoleName::Leader->value,
            RoleName::Teacher->value,
        ])->pluck('id'));

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Líder, Docente');
        $this->assertStringNotContainsString(
            'truncate text-sm text-text-secondary">Líder, Docente',
            $response->getContent()
        );
    }

    /**
     * Prioridad de diseño: avatar con iniciales en el color primario de
     * marca, y rol como chip en el tono sutil de marca (no texto gris
     * plano) — ver docs/manual-diseno.md.
     */
    public function test_sidebar_renders_the_avatar_initials_and_the_role_chip(): void
    {
        $user = User::factory()->create(['is_active' => true, 'name' => 'Claudia Acevedo']);
        $user->roles()->attach(Role::where('name', RoleName::Leader->value)->first());

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('CA', $html);
        $this->assertStringContainsString('bg-brand-primary text-sm font-semibold text-white', $html);
        $this->assertStringContainsString('badge mb-3 whitespace-normal bg-brand-primary-subtle text-brand-primary', $html);
        $this->assertStringContainsString('title="Claudia Acevedo"', $html);
    }

    /**
     * Mismo bug ya corregido una vez para el nombre institucional (ver
     * InstitutionSettingsTest::test_a_long_institution_name_wraps_to_two_lines...):
     * un nombre largo se cortaba con "..." en una sola línea, perdiendo el
     * apellido. Se corrigió igual: wrap de hasta 2 líneas (`line-clamp-2`
     * + `break-words`) en vez de `truncate`, con el nombre completo
     * siempre disponible en `title`.
     */
    public function test_a_long_user_name_wraps_to_two_lines_in_the_sidebar_instead_of_being_truncated(): void
    {
        $longName = 'Claudia Lorena Acevedo Rincón';
        $user = User::factory()->create(['is_active' => true, 'name' => $longName]);
        $user->roles()->attach(Role::where('name', RoleName::Teacher->value)->first());

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $html = $response->getContent();

        // El nombre completo debe estar en el HTML entero, sin "..." — ver
        // el comentario equivalente en InstitutionSettingsTest: `truncate`
        // no recorta el texto fuente, solo lo oculta visualmente, así que
        // lo que de verdad distingue el fix es la clase CSS usada.
        $this->assertStringContainsString($longName, $html);
        $this->assertStringNotContainsString('truncate text-base font-semibold', $html);
        $this->assertStringContainsString('line-clamp-2 break-words', $html);
        $this->assertStringContainsString('title="'.$longName.'"', $html);
    }
}
