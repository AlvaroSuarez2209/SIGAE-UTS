<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "Múltiples archivos" se fusiona con "Archivo" — max_files ya es el
     * único campo que controla la cantidad, así que tenerlos como 2
     * opciones separadas en "Tipos de evidencia permitidos" era redundante
     * (en el resto del código, EvidenceWorkspace::submit() y la vista de
     * evidencia ya los trataban como equivalentes). Esta migración debe
     * correr ANTES de quitar el caso MultipleFiles del enum EvidenceType:
     * si alguna fila ya tiene 'multiple_files' guardado en su JSON cuando
     * el caso deje de existir, el cast AsEnumCollection de
     * Deliverable::allowed_evidence_types lanzaría un ValueError al
     * cargarla. No es reversible: una vez fusionados no hay forma de saber
     * cuáles filas tenían originalmente "Múltiples archivos" en vez de
     * "Archivo".
     */
    public function up(): void
    {
        $this->mergeOn('deliverables');
        $this->mergeOn('deliverable_templates');
    }

    private function mergeOn(string $table): void
    {
        DB::table($table)
            ->whereNotNull('allowed_evidence_types')
            ->get(['id', 'allowed_evidence_types'])
            ->each(function ($row) use ($table) {
                $types = json_decode($row->allowed_evidence_types, true) ?? [];

                if (! in_array('multiple_files', $types, true)) {
                    return;
                }

                $merged = collect($types)
                    ->map(fn ($type) => $type === 'multiple_files' ? 'file' : $type)
                    ->unique()
                    ->values()
                    ->all();

                DB::table($table)->where('id', $row->id)->update([
                    'allowed_evidence_types' => json_encode($merged),
                ]);
            });
    }

    public function down(): void
    {
        // Irreversible a propósito — ver comentario de up().
    }
};
