<?php

namespace Tests\Feature\Console;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedIfEmptyTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_the_database_when_the_users_table_is_empty(): void
    {
        $this->assertSame(0, User::count());

        $this->artisan('db:seed-if-empty')->assertExitCode(0);

        $this->assertSame(1, User::count());
        $this->assertTrue(User::where('email', 'admin@uts.edu.co')->exists());
        $this->assertTrue(Role::where('name', 'administrator')->exists());
    }

    /**
     * El escenario real que motiva este comando: Render reinicia el
     * contenedor cada vez que el plan gratuito lo "despierta" tras
     * inactividad — correr esto de nuevo sobre una base ya sembrada no
     * debe duplicar ni volver a crear nada.
     */
    public function test_does_not_reseed_when_a_user_already_exists(): void
    {
        User::factory()->create();

        $this->artisan('db:seed-if-empty')->assertExitCode(0);

        $this->assertSame(1, User::count());
    }
}
