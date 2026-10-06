<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agregadas para la importación masiva de docentes (ver
 * App\Services\TeacherImport\TeacherImportService):
 *
 * - document_type: la plantilla de importación trae "tipo_documento" (CC,
 *   CE, TI, PA) y `users` solo tenía `document_number` sin tipo — se agrega
 *   para no descartar ese dato. Nullable: los usuarios creados antes de
 *   esta migración no lo tienen y nada más del sistema lo exige.
 * - program_unit_id: "codigo_programa" en la plantilla no tiene dónde vivir
 *   hoy — crear una asignación real en `teacher_assignments` exige período +
 *   actividad + horas, datos que la plantilla no trae. Esta columna es solo
 *   el programa de adscripción informativo del docente, independiente de
 *   la distribución docente real. `nullOnDelete`: si se elimina el programa
 *   (no hay UI para eso hoy, pero por seguridad), el usuario no debe
 *   bloquear ni arrastrar el borrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('document_type')->nullable()->after('document_number');
            $table->foreignId('program_unit_id')->nullable()->after('document_type')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_unit_id');
            $table->dropColumn('document_type');
        });
    }
};
