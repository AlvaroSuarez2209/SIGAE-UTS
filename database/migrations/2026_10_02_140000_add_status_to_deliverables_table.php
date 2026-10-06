<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Default 'published' para que todo entregable ya existente quede
        // exactamente como se comportaba antes de este campo (visible y con
        // destinatarios/evidencia ya sincronizados) — ver Prioridad 5,
        // punto 2 de la revisión de la directora.
        Schema::table('deliverables', function (Blueprint $table) {
            $table->string('status')->default('published')->after('deliverable_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
