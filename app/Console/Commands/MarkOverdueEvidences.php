<?php

namespace App\Console\Commands;

use App\Enums\EvidenceStatus;
use App\Models\AuditLog;
use App\Models\Evidence;
use Illuminate\Console\Command;

/**
 * Transición automática pendiente/borrador -> vencido. Se ejecuta a diario
 * desde el scheduler (ver routes/console.php); requiere que el servidor
 * tenga el cron de Laravel configurado — ver docs/manual-tecnico.md.
 */
class MarkOverdueEvidences extends Command
{
    protected $signature = 'evidences:mark-overdue';

    protected $description = 'Marca como "Vencido" las evidencias pendientes o en borrador cuya fecha límite ya pasó';

    public function handle(): int
    {
        // now() ya está en America/Bogota (config('app.timezone')) y due_at
        // se guarda como hora de pared en la misma zona (ver "Zona horaria"
        // en el manual técnico) — comparar ambas directamente es correcto,
        // sin conversión adicional.
        $overdue = Evidence::query()
            ->whereIn('status', [EvidenceStatus::Pending, EvidenceStatus::Draft])
            ->whereHas('deliverable', fn ($query) => $query->where('due_at', '<', now()))
            ->with('deliverable')
            ->get();

        foreach ($overdue as $evidence) {
            $previousStatus = $evidence->status;

            $evidence->update(['status' => EvidenceStatus::Expired]);

            // Acción del sistema, no de una persona autenticada: en un
            // comando de consola auth()->id() es null, así que
            // AuditLog::record() deja user_id en null — la bitácora
            // muestra claramente que fue el scheduler, no un usuario.
            AuditLog::record('evidence_marked_overdue', $evidence, [
                'previous_status' => $previousStatus->value,
                'due_at' => $evidence->deliverable->due_at->toIso8601String(),
            ]);
        }

        $this->info("Evidencias marcadas como vencidas: {$overdue->count()}");

        return self::SUCCESS;
    }
}
