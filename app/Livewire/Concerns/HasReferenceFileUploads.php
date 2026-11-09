<?php

namespace App\Livewire\Concerns;

use App\Models\Deliverable;
use App\Models\DeliverableReferenceFile;
use App\Models\DeliverableTemplate;
use Illuminate\Support\Facades\Validator;

/**
 * Carga y validación de los nuevos archivos de referencia pendientes
 * de subir — compartido entre DeliverableForm y TemplateForm (punto C
 * de las correcciones post-prueba: "mismos tipos, tamaño y cantidad"
 * en los dos formularios — antes una plantilla solo admitía 1 archivo
 * sin ningún límite explícito de tamaño/tipo propio, mientras que un
 * entregable ya admitía hasta DeliverableReferenceFile::MAX_FILES).
 *
 * El componente que use este trait debe implementar
 * referenceFileOwner(): el Deliverable o DeliverableTemplate dueño de
 * los archivos YA guardados (o null si todavía no existe, p. ej.
 * mientras se crea uno nuevo). reservedReferenceFileSlots() es un
 * segundo punto de extensión opcional para reservar cupo por archivos
 * que van a copiarse pero aún no son filas reales (ver
 * DeliverableForm::$inheritedReferenceFiles).
 */
trait HasReferenceFileUploads
{
    public array $newReferenceFiles = [];

    abstract protected function referenceFileOwner(): Deliverable|DeliverableTemplate|null;

    protected function reservedReferenceFileSlots(): int
    {
        return 0;
    }

    /**
     * Valida cada archivo apenas se adjunta (no al confirmar) — mismo
     * criterio que EvidenceWorkspace::updatedNewFiles(): un archivo
     * fuera de la whitelist nunca debe quedar "adjunto en silencio"
     * hasta el guardado final. El tope cuenta los ya existentes (si se
     * está editando), los reservados (p. ej. heredados de una
     * plantilla) y los pendientes de subir — no solo estos últimos.
     */
    public function updatedNewReferenceFiles(): void
    {
        $this->resetErrorBag(['newReferenceFiles', 'newReferenceFiles.*']);

        $existingCount = ($this->referenceFileOwner()?->activeReferenceFiles()->count() ?? 0)
            + $this->reservedReferenceFileSlots();

        $rules = [
            'file',
            'max:'.(DeliverableReferenceFile::MAX_FILE_SIZE_MB * 1024),
            'mimes:'.implode(',', Deliverable::DEFAULT_ALLOWED_FILE_TYPES),
        ];

        $validFiles = [];

        foreach ($this->newReferenceFiles as $file) {
            if ($existingCount + count($validFiles) >= DeliverableReferenceFile::MAX_FILES) {
                $this->addError('newReferenceFiles', 'Se admite un máximo de '.DeliverableReferenceFile::MAX_FILES.' archivo(s) de referencia.');

                break;
            }

            $validator = Validator::make(['newReferenceFiles' => [$file]], ['newReferenceFiles.*' => $rules]);

            if ($validator->fails()) {
                $this->addError('newReferenceFiles', $validator->errors()->first('newReferenceFiles.*'));

                continue;
            }

            $validFiles[] = $file;
        }

        $this->newReferenceFiles = $validFiles;
    }

    public function removeNewReferenceFile(int $index): void
    {
        unset($this->newReferenceFiles[$index]);
        $this->newReferenceFiles = array_values($this->newReferenceFiles);
    }
}
