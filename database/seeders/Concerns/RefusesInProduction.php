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
 */
trait RefusesInProduction
{
    private function abortIfProduction(): bool
    {
        if (! app()->environment('production')) {
            return false;
        }

        $this->command?->error(class_basename($this).' es un seeder de demostración/prueba — nunca se ejecuta en producción.');

        return true;
    }
}
