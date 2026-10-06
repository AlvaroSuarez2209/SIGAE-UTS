<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Único punto donde se aplican los 4 filtros de la bitácora (Usuario,
 * Acción, Desde, Hasta) — usado tanto por la pantalla
 * (App\Livewire\Audit\AuditLogIndex, paginada) como por la exportación
 * (App\Services\Audit\AuditLogExportBuilder, sin paginar), para que ambos
 * canales filtren exactamente igual sin repetir la consulta dos veces.
 */
class AuditLogQuery
{
    /**
     * @param  array{user?: ?string, action?: ?string, from?: ?string, to?: ?string}  $filters
     */
    public static function filtered(array $filters): Builder
    {
        return AuditLog::query()
            ->with(['user', 'auditable'])
            ->when($filters['user'] ?? null, fn ($q, $value) => $q->where('user_id', $value))
            ->when($filters['action'] ?? null, fn ($q, $value) => $q->where('action', $value))
            ->when($filters['from'] ?? null, fn ($q, $value) => $q->where('created_at', '>=', $value))
            ->when($filters['to'] ?? null, fn ($q, $value) => $q->where('created_at', '<=', $value.' 23:59:59'))
            ->orderByDesc('created_at');
    }
}
