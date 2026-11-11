<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Punto 1 (almacenamiento persistente) + anexo: Render, en su plan
 * gratuito, no tiene consola de comandos — este comando corre desde
 * docker/start.sh en cada arranque, para que el resultado quede en los
 * registros del despliegue sin depender de poder conectarse a una shell.
 * Un fallo aquí nunca debe tumbar el arranque del contenedor (ver el
 * "|| true" en start.sh) — por eso nunca relanza la excepción, solo la
 * reporta y devuelve FAILURE.
 */
class StorageCheck extends Command
{
    protected $signature = 'storage:check';

    protected $description = 'Escribe, lee y elimina un objeto de prueba en el disco de soportes configurado (config("filesystems.attachments_disk"))';

    public function handle(): int
    {
        $diskName = config('filesystems.attachments_disk');
        $path = '_storage_check/'.now()->format('Ymd_His').'_'.Str::random(8).'.txt';
        $content = 'storage:check '.now()->toIso8601String();

        try {
            $disk = Storage::disk($diskName);

            $disk->put($path, $content);

            $readBack = $disk->get($path);

            $disk->delete($path);

            if ($readBack !== $content) {
                $this->error("storage:check [disco \"{$diskName}\"]: FALLÓ — el contenido leído no coincide con lo escrito.");

                return self::FAILURE;
            }

            $this->info("storage:check [disco \"{$diskName}\"]: OK — escritura, lectura y borrado verificados.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("storage:check [disco \"{$diskName}\"]: FALLÓ — {$e->getMessage()}");
            report($e);

            return self::FAILURE;
        }
    }
}
