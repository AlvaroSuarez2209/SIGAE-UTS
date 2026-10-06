<?php

namespace App\Livewire\Audit;

use App\Livewire\Concerns\HasStandardPagination;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogQuery;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Auditoría')]
class AuditLogIndex extends Component
{
    use HasStandardPagination, WithPagination;

    public string $userFilter = '';

    public string $actionFilter = '';

    public string $fromFilter = '';

    public string $toFilter = '';

    public ?int $selectedLogId = null;

    public function updating($property): void
    {
        if (in_array($property, ['userFilter', 'actionFilter', 'fromFilter', 'toFilter'], true)) {
            $this->resetPage();
        }
    }

    public function showDetail(int $logId): void
    {
        $this->selectedLogId = $logId;
    }

    public function closeDetail(): void
    {
        $this->selectedLogId = null;
    }

    public function render()
    {
        $filters = [
            'user' => $this->userFilter ?: null,
            'action' => $this->actionFilter ?: null,
            'from' => $this->fromFilter ?: null,
            'to' => $this->toFilter ?: null,
        ];

        $logs = AuditLogQuery::filtered($filters)->paginate(self::PER_PAGE);

        return view('livewire.audit.audit-log-index', [
            'logs' => $logs,
            'selectedLog' => $this->selectedLogId
                ? AuditLog::with(['user', 'auditable'])->find($this->selectedLogId)
                : null,
            'exportFilters' => array_filter($filters, fn ($value) => $value !== null),
            'users' => User::orderBy('name')->get(),
            // El propio Livewire vuelve a ejecutar render() en cada interacción
            // de este componente, incluido un simple cambio de página — sin
            // caché, esta consulta (un DISTINCT sin índice de apoyo sobre
            // `action`) repetiría un recorrido completo de audit_logs en cada
            // clic, aunque el conjunto de acciones distintas casi nunca cambia
            // (solo cuando se agrega una acción nueva en el código). 10
            // minutos es un margen de "desactualización" aceptable para las
            // OPCIONES de un filtro (no para los datos que se filtran, que
            // siempre se leen sin caché arriba).
            'actions' => Cache::remember(
                'audit-log-distinct-actions',
                now()->addMinutes(10),
                fn () => AuditLog::query()->distinct()->orderBy('action')->pluck('action')
            ),
        ]);
    }
}
