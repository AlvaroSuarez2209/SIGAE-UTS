<?php

namespace App\Livewire\Admin\Users;

use App\Exports\TeacherImportRejectedRowsExport;
use App\Imports\TeacherImport;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TeacherImport\TeacherImportService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

#[Layout('layouts.app')]
#[Title('Importar docentes')]
class TeacherImportWizard extends Component
{
    use WithFileUploads;

    public $file = null;

    /** upload | preview | result */
    public string $step = 'upload';

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public ?string $fileError = null;

    public ?string $originalFileName = null;

    /** @var array{created: int, updated: int, skipped: int, rejected: int, rejected_rows: array}|null */
    public ?array $summary = null;

    public function mount(): void
    {
        Gate::authorize('create', User::class);
    }

    public function updatedFile(): void
    {
        $this->fileError = null;
    }

    public function preview(): void
    {
        Gate::authorize('create', User::class);

        $this->fileError = null;

        $this->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:1024'],
        ]);

        try {
            $sheets = Excel::toCollection(new TeacherImport, $this->file->getRealPath());
        } catch (Throwable) {
            $this->fileError = 'No se pudo leer el archivo. Verifica que sea un Excel (.xlsx) o CSV válido, descargado a partir de la plantilla.';

            return;
        }

        $rows = $sheets->first() ?? collect();

        if ($rows->isEmpty()) {
            $this->fileError = 'El archivo no tiene ninguna fila de datos.';

            return;
        }

        $headers = array_keys($rows->first()->toArray());
        $missing = array_diff(TeacherImportService::REQUIRED_HEADERS, $headers);

        if ($missing !== []) {
            $this->fileError = 'Faltan columnas obligatorias en el archivo: '.implode(', ', $missing).'. Usa la plantilla descargable sin cambiar los nombres de columna.';

            return;
        }

        if ($rows->count() > TeacherImportService::MAX_ROWS) {
            $this->fileError = 'El archivo tiene '.$rows->count().' filas; el máximo permitido por importación es '.TeacherImportService::MAX_ROWS.'. Divide el archivo en lotes más pequeños.';

            return;
        }

        $this->originalFileName = $this->file->getClientOriginalName();
        $this->rows = app(TeacherImportService::class)->validateRows($rows);
        $this->step = 'preview';
    }

    public function confirm(): void
    {
        Gate::authorize('create', User::class);

        $summary = app(TeacherImportService::class)->commit($this->rows);

        AuditLog::record('teachers_bulk_imported', null, [
            'file_name' => $this->originalFileName,
            'processed' => count($this->rows),
            'created' => $summary['created'],
            'updated' => $summary['updated'],
            'skipped' => $summary['skipped'],
            'rejected' => $summary['rejected'],
        ]);

        $this->summary = $summary;
        $this->rows = [];
        $this->step = 'result';
    }

    public function downloadRejected()
    {
        if (! $this->summary || empty($this->summary['rejected_rows'])) {
            return;
        }

        return Excel::download(
            new TeacherImportRejectedRowsExport($this->summary['rejected_rows']),
            'docentes-rechazados.xlsx'
        );
    }

    public function startOver(): void
    {
        $this->reset(['file', 'rows', 'summary', 'fileError', 'originalFileName']);
        $this->step = 'upload';
    }

    public function render()
    {
        $validCount = count(array_filter($this->rows, fn ($row) => $row['errors'] === []));

        return view('livewire.admin.users.teacher-import-wizard', [
            'validCount' => $validCount,
            'invalidCount' => count($this->rows) - $validCount,
        ]);
    }
}
