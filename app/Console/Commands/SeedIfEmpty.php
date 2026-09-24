<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Corre `db:seed` solo si la base todavía no tiene ningún usuario —
 * pensado para docker/start.sh en Render: el contenedor se reinicia cada
 * vez que el plan gratuito lo "despierta" tras inactividad, así que
 * sembrar sin esta guarda repetiría el seed en cada arranque. `users` es
 * la señal correcta de "¿ya se inicializó esta base?": es la única tabla
 * que garantizamos no vacía después de un `db:seed` exitoso
 * (`DatabaseSeeder` solo corre `RoleSeeder` y `UserSeeder` — ver ese
 * archivo).
 *
 * Deliberadamente NO usa un archivo marcador en el filesystem del
 * contenedor (ej. storage/.seeded): en el plan gratuito de Render, el
 * contenedor puede recrearse desde la imagen al "despertar" — no hay
 * garantía de que sea el mismo disco de antes de dormirse. La base de
 * datos en Neon es la única fuente de verdad que de verdad persiste entre
 * arranques del contenedor.
 */
class SeedIfEmpty extends Command
{
    protected $signature = 'db:seed-if-empty';

    protected $description = 'Corre el seeder por defecto solo si la tabla users está vacía (para arranques repetidos en Render)';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->info('La base ya tiene usuarios — se omite el seed.');

            return self::SUCCESS;
        }

        $this->info('La base está vacía — sembrando datos iniciales.');

        return $this->call('db:seed', ['--force' => true]);
    }
}
