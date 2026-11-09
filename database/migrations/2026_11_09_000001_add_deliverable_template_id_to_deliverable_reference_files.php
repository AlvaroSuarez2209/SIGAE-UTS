<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Punto 5: los archivos de referencia (antes exclusivos de un
 * Deliverable) ahora también pueden pertenecer a una DeliverableTemplate
 * — el archivo del formato institucional se copia a esta tabla (como
 * una fila nueva propia del entregable) al crear un entregable desde la
 * plantilla, ver DeliverableForm::save(). deliverable_id pasa a
 * nullable y el CHECK obliga a que exactamente uno de los dos dueños
 * esté presente — mismo patrón que la restricción
 * deliverables_exactly_one_scope ya usada en la tabla `deliverables`.
 * Las filas existentes quedan intactas: todas ya tienen deliverable_id,
 * así que siguen cumpliendo el CHECK sin tocarlas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliverable_reference_files', function (Blueprint $table) {
            $table->foreignId('deliverable_id')->nullable()->change();
            $table->foreignId('deliverable_template_id')->nullable()->after('deliverable_id')
                ->constrained()->cascadeOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE deliverable_reference_files
            ADD CONSTRAINT deliverable_reference_files_exactly_one_owner CHECK (
                (deliverable_id IS NOT NULL)::int + (deliverable_template_id IS NOT NULL)::int = 1
            )
        SQL);
    }

    /**
     * Revierte solo si no hay filas de plantilla que perder: con alguna
     * existente, falla con un mensaje claro en vez de borrarlas en
     * silencio (este proyecto nunca pierde datos existentes en un
     * rollback) — hay que quitarlas a mano primero si de verdad se
     * quiere revertir.
     */
    public function down(): void
    {
        $templateOwnedCount = DB::table('deliverable_reference_files')
            ->whereNotNull('deliverable_template_id')
            ->count();

        if ($templateOwnedCount > 0) {
            throw new RuntimeException(
                "No se puede revertir: hay {$templateOwnedCount} archivo(s) de referencia que pertenecen a una plantilla ".
                '(deliverable_template_id). Elimínalos manualmente primero si de verdad quieres revertir esta migración.'
            );
        }

        DB::statement('ALTER TABLE deliverable_reference_files DROP CONSTRAINT deliverable_reference_files_exactly_one_owner');

        Schema::table('deliverable_reference_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deliverable_template_id');
            $table->foreignId('deliverable_id')->nullable(false)->change();
        });
    }
};
