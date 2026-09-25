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
}
