<?php

namespace Tests\Feature\Evidence;

use App\Enums\EvidenceStatus;
use App\Models\AuditLog;
use App\Models\Evidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prueba directa del backfill de la migración
 * 2026_10_04_000000_add_exemption_reason_to_evidences_table (revisión del
 * estado Exento) — recupera `exemption_reason` para evidencias YA exentas
 * a partir de la fila de auditoría `evidence_marked_exempt` más reciente
 * de cada una, la única fuente que existía antes de esta columna.
 *
 * Se invoca el método de backfill directamente (no up(), que intentaría
 * crear la columna otra vez — RefreshDatabase ya corrió la migración real
 * sobre un esquema vacío antes de este test, sin nada que recuperar) para
 * poder simular con datos reales qué habría pasado si hubiera corrido
 * sobre evidencias ya exentas en producción.
 */
class EvidenceExemptionBackfillTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_04_000000_add_exemption_reason_to_evidences_table.php');
    }

    public function test_backfills_the_reason_from_the_most_recent_audit_log_entry(): void
    {
        $evidence = Evidence::factory()->create(['status' => EvidenceStatus::Exempt]);

        AuditLog::record('evidence_marked_exempt', $evidence, [
            'justification' => 'Licencia de maternidad durante todo el periodo.',
            'previous_status' => 'pending',
        ]);

        DB::table('evidences')->where('id', $evidence->id)->update(['exemption_reason' => null]);

        $this->migration()->backfillExemptionReasonsFromAuditLog();

        $this->assertEquals(
            'Licencia de maternidad durante todo el periodo.',
            $evidence->fresh()->exemption_reason
        );
    }

    /**
     * Si una evidencia fue exentada más de una vez a lo largo de su
     * historia (exentada, revertida, exentada de nuevo con otro motivo),
     * el backfill debe tomar la fila de auditoría MÁS RECIENTE, no la
     * primera.
     */
    public function test_backfill_uses_the_most_recent_exemption_when_there_is_more_than_one(): void
    {
        $evidence = Evidence::factory()->create(['status' => EvidenceStatus::Exempt]);

        AuditLog::record('evidence_marked_exempt', $evidence, [
            'justification' => 'Motivo original (ya revertido).',
            'previous_status' => 'pending',
        ]);
        AuditLog::record('evidence_marked_exempt', $evidence, [
            'justification' => 'Motivo más reciente.',
            'previous_status' => 'pending',
        ]);

        DB::table('evidences')->where('id', $evidence->id)->update(['exemption_reason' => null]);

        $this->migration()->backfillExemptionReasonsFromAuditLog();

        $this->assertEquals('Motivo más reciente.', $evidence->fresh()->exemption_reason);
    }

    public function test_backfill_leaves_null_when_no_audit_log_entry_exists(): void
    {
        $evidence = Evidence::factory()->create(['status' => EvidenceStatus::Exempt]);

        DB::table('evidences')->where('id', $evidence->id)->update(['exemption_reason' => null]);

        $this->migration()->backfillExemptionReasonsFromAuditLog();

        $this->assertNull($evidence->fresh()->exemption_reason);
    }

    public function test_backfill_does_not_touch_evidences_that_are_not_exempt(): void
    {
        $evidence = Evidence::factory()->create(['status' => EvidenceStatus::Pending]);

        $this->migration()->backfillExemptionReasonsFromAuditLog();

        $this->assertNull($evidence->fresh()->exemption_reason);
    }
}
