<?php

namespace Database\Seeders\Concerns;

/**
 * Datos de demostración/prueba — nunca deben poder sembrarse en
 * producción. `DatabaseSeeder` ya los deja comentados (solo corre
 * `RoleSeeder` y `UserSeeder`), pero eso no impide invocar uno de estos
 * directamente (`php artisan db:seed --class=EvidenceSeeder`), saltándose
 * esa lista — este trait cierra ese camino alterno. `RoleSeeder` y
 * `UserSeeder` (el Administrador inicial) NO lo usan a propósito: esos sí
 * deben poder correr en producción.
 *
 * `--force` es la misma opción que `db:seed` ya trae de fábrica (Laravel
 * la usa para saltar la confirmación interactiva de "¿seguro que quieres
 * correr esto en producción?") — se reutiliza tal cual en vez de inventar
 * una bandera nueva: `php artisan db:seed --class=DemoCycleSeeder --force`.
 * `hasOption()` evita un error si algún día este trait se usa desde un
 * contexto sin esa opción registrada.
 */
trait RefusesInProduction
{
    private function abortIfProduction(): bool
    {
        if (! app()->environment('production')) {
            return false;
        }

        if ($this->command?->hasOption('force') && $this->command->option('force')) {
            $this->command->warn(class_basename($this).' corre en producción porque se pasó --force explícitamente.');

            return false;
        }

        $this->command?->error(class_basename($this).' es un seeder de demostración/prueba — nunca se ejecuta en producción sin --force.');

        return true;
    }
}
