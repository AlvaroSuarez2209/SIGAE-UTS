<?php

use App\Models\Evidence;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Semántica nueva del estado Exento (aclaración de la directora, revisión
 * del estado Exento): el docente dueño de la evidencia puede marcarla y
 * quitarla como exenta (además de Administrador/Coordinación, que ya
 * podían) y debe indicar por qué — ver EvidencePolicy::markExempt() y
 * Evidence::markExempt(). Antes de este cambio, esa razón solo quedaba en
 * `audit_logs.metadata->justification` (un registro de auditoría de solo
 * lectura, no un dato de negocio consultable) — nunca se mostraba a
 * nadie, ni siquiera al propio docente. Esta columna la hace un dato real
 * de la evidencia: se guarda al exentar y se limpia al quitar la
 * exención (ver Evidence::removeExemption()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidences', function (Blueprint $table) {
            $table->text('exemption_reason')->nullable()->after('status');
        });

        $this->backfillExemptionReasonsFromAuditLog();
    }

    /**
     * Recupera la razón de las evidencias YA exentas a partir de la fila
     * de auditoría más reciente `evidence_marked_exempt` de cada una — es
     * la única fuente que existía antes de esta columna. Una evidencia
     * exenta sin ninguna fila de auditoría correspondiente (no debería
     * pasar, `audit_logs` es de solo escritura y nunca se purga, pero por
     * si acaso) simplemente queda con `exemption_reason` en null: no hay
     * ningún dato real que inventar para ella. Público (no privado) para
     * poder probarlo de forma aislada sin volver a intentar crear la
     * columna — ver tests/Feature/Evidence/EvidenceExemptionTest.php.
     */
    public function backfillExemptionReasonsFromAuditLog(): void
    {
        DB::table('evidences')
            ->where('status', 'exempt')
            ->orderBy('id')
            ->each(function (object $evidence): void {
                $log = DB::table('audit_logs')
                    ->where('action', 'evidence_marked_exempt')
                    ->where('auditable_type', Evidence::class)
                    ->where('auditable_id', $evidence->id)
                    ->orderByDesc('id')
                    ->first(['metadata']);

                if (! $log) {
                    return;
                }

                $justification = json_decode($log->metadata, true)['justification'] ?? null;

                if ($justification) {
                    DB::table('evidences')->where('id', $evidence->id)->update(['exemption_reason' => $justification]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('evidences', function (Blueprint $table) {
            $table->dropColumn('exemption_reason');
        });
    }
};
