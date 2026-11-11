<?php

namespace App\Console\Commands;

use App\Models\DeliverableReferenceFile;
use App\Models\EvidenceFile;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Punto 1 (almacenamiento persistente): copia al disco de soportes
 * configurado (config('filesystems.attachments_disk')) los archivos de
 * EvidenceFile/DeliverableReferenceFile que hoy solo existen en el disco
 * "local" — por ejemplo, justo antes de activar ATTACHMENTS_DISK=s3 por
 * primera vez. --dry-run es el comportamiento por defecto (sin --apply,
 * nunca escribe ni borra nada, solo reporta qué haría); --apply copia
 * de verdad. Nunca sobrescribe: una fila cuyo disk_path ya existe en el
 * disco destino se reporta como "ya existe" y se deja intacta — nunca
 * se compara contenido ni se decide cuál versión es la buena.
 *
 * disk_path nunca cambia (se copia bajo la MISMA ruta en el disco
 * destino) — así ninguna fila de la base necesita actualizarse, solo el
 * archivo físico cambia de disco.
 */
class StorageMigrateLocalToS3 extends Command
{
    protected $signature = 'storage:migrate-to-s3 {--apply : Copia los archivos de verdad; sin esta opción, solo reporta qué se copiaría}';

    protected $description = 'Copia al disco de soportes configurado los archivos que solo existen en el disco local';

    public function handle(): int
    {
        $targetDiskName = config('filesystems.attachments_disk');

        if ($targetDiskName === 'local') {
            $this->info('El disco de soportes configurado (ATTACHMENTS_DISK) ya es "local" — no hay nada que migrar.');

            return self::SUCCESS;
        }

        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'Modo: --apply (copia de verdad).' : 'Modo: dry-run (por defecto, no escribe nada; usa --apply para copiar).');

        $localDisk = Storage::disk('local');
        $targetDisk = Storage::disk($targetDiskName);

        $summary = ['migrated' => 0, 'would_migrate' => 0, 'already_present' => 0, 'missing_locally' => 0, 'failed' => 0];

        foreach ([EvidenceFile::class, DeliverableReferenceFile::class] as $modelClass) {
            $modelClass::query()->each(function (Model $file) use ($modelClass, $localDisk, $targetDisk, $apply, &$summary) {
                $label = class_basename($modelClass)." #{$file->id} ({$file->disk_path})";

                if (! $localDisk->exists($file->disk_path)) {
                    $summary['missing_locally']++;
                    $this->line("  omitido — no existe en el disco local: {$label}");

                    return;
                }

                if ($targetDisk->exists($file->disk_path)) {
                    $summary['already_present']++;
                    $this->line("  omitido — ya existe en el disco destino, no se sobrescribe: {$label}");

                    return;
                }

                if (! $apply) {
                    $summary['would_migrate']++;
                    $this->line("  se copiaría: {$label}");

                    return;
                }

                try {
                    $stream = $localDisk->readStream($file->disk_path);
                    $targetDisk->writeStream($file->disk_path, $stream);

                    if (is_resource($stream)) {
                        fclose($stream);
                    }

                    $summary['migrated']++;
                    $this->line("  copiado: {$label}");
                } catch (Throwable $e) {
                    $summary['failed']++;
                    $this->error("  FALLÓ al copiar {$label}: {$e->getMessage()}");
                    report($e);
                }
            });
        }

        $this->newLine();
        $this->info("Resumen — ya en el destino: {$summary['already_present']}, sin archivo local: {$summary['missing_locally']}, ".
            ($apply
                ? "copiados: {$summary['migrated']}, fallidos: {$summary['failed']}."
                : "se copiarían con --apply: {$summary['would_migrate']}."));

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
