<?php

namespace Tests\Unit\Seeders;

use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Auditoría de seguridad: admin@uts.edu.co / ***REMOVED*** vivió en texto
 * plano en este seeder (y en README.md/docs/manual-tecnico.md) — la
 * contraseña ahora viene de config('seeding.admin_initial_password')
 * (ADMIN_INITIAL_PASSWORD) y el seeder falla fuerte si falta, en vez de
 * caer a cualquier valor fijo.
 */
class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_fails_with_a_clear_message_when_admin_initial_password_is_missing(): void
    {
        config(['seeding.admin_initial_password' => null]);

        $this->seed(RoleSeeder::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ADMIN_INITIAL_PASSWORD');

        (new UserSeeder)->run();
    }
}
