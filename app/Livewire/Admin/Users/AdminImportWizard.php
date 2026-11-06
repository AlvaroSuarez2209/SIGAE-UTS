<?php

namespace App\Livewire\Admin\Users;

use App\Exports\AdminImportRejectedRowsExport;
use App\Imports\TeacherImport;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AdminImport\AdminImportService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Importación masiva de administradores/coordinadores — revisión de la
 * directora, punto 7. Mismo asistente de 3 pasos que TeacherImportWizard,
 * pero sobre AdminImportService; reutiliza App\Imports\TeacherImport como
 * lector de encabezados porque esa clase no tiene ninguna lógica propia de
 * docentes (solo le dice a Excel::toCollection() que respete la fila 1
 * como encabezados).
 */
#[Layout('layouts.app')]
#[Title('Importar administradores')]
class AdminImportWizard extends Component
{
    use WithFileUploads;

    public $file = null;

    /**
     * Mismo patrón que UserForm: solo recibe estos filtros por la URL
     * (puestos ahí por el enlace "Importar" de UserIndex) y los reenvía
     * en "Volver a Usuarios"/"Ir a Usuarios".
     */
    #[Url]
    public string $search = '';

    #[Url]
    public string $roleFilter = '';

    public function usersIndexUrl(): string
    {
        return route('admin.users.index', ['search' => $this->search, 'roleFilter' => $this->roleFilter]);
    }

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
        Gate::authorize('importAdministratorAccounts', User::class);
    }

    public function updatedFile(): void
    {
        $this->fileError = null;
    }

    public function preview(): void
    {
        Gate::authorize('importAdministratorAccounts', User::class);

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
        $missing = array_diff(AdminImportService::REQUIRED_HEADERS, $headers);

        if ($missing !== []) {
            $this->fileError = 'Faltan columnas obligatorias en el archivo: '.implode(', ', $missing).'. Usa la plantilla descargable sin cambiar los nombres de columna.';

            return;
        }

        if ($rows->count() > AdminImportService::MAX_ROWS) {
            $this->fileError = 'El archivo tiene '.$rows->count().' filas; el máximo permitido por importación es '.AdminImportService::MAX_ROWS.'. Divide el archivo en lotes más pequeños.';

            return;
        }

        $this->originalFileName = $this->file->getClientOriginalName();
        $this->rows = app(AdminImportService::class)->validateRows($rows);
        $this->step = 'preview';
    }

    public function confirm(): void
    {
        Gate::authorize('importAdministratorAccounts', User::class);

        $summary = app(AdminImportService::class)->commit($this->rows);

        AuditLog::record('admins_bulk_imported', null, [
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
            new AdminImportRejectedRowsExport($this->summary['rejected_rows']),
            'administradores-rechazados.xlsx'
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

        return view('livewire.admin.users.admin-import-wizard', [
            'validCount' => $validCount,
            'invalidCount' => count($this->rows) - $validCount,
        ]);
    }
}
