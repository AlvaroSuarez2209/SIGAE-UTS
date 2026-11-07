<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisión de la directora: tipo de vinculación del Docente/Líder
 * (planta, tiempo_completo, medio_tiempo_1m, medio_tiempo_4m) — nullable,
 * nunca obligatorio ni para Docente/Líder: un usuario sin valor se
 * muestra como "Sin definir" en vez de bloquear el guardado (ver
 * App\Livewire\Admin\Users\UserForm).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employment_type')->nullable()->after('program_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('employment_type');
        });
    }
};
