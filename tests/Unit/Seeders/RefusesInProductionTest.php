<?php

namespace Tests\Unit\Seeders;

use App\Models\AcademicPeriod;
use App\Models\CrossCuttingCommitment;
use Database\Seeders\AcademicPeriodSeeder;
use Database\Seeders\CrossCuttingCommitmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Auditoría de seguridad: DatabaseSeeder ya deja estos 8 seeders de
 * demostración/prueba comentados (nunca corren vía `db:seed` normal en
 * ningún entorno), pero eso no impide invocar uno directamente
 * (`php artisan db:seed --class=...`), saltándose esa lista por completo
 * — Database\Seeders\Concerns\RefusesInProduction cierra ese camino
 * alterno específicamente en producción.
 */
class RefusesInProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_demo_seeder_does_nothing_when_app_env_is_production(): void
    {
        $this->app['env'] = 'production';

        (new AcademicPeriodSeeder)->run();

        $this->assertSame(0, AcademicPeriod::count());
    }

    public function test_a_demo_seeder_runs_normally_outside_production(): void
    {
        (new CrossCuttingCommitmentSeeder)->run();

        $this->assertGreaterThan(0, CrossCuttingCommitment::count());
    }
}
