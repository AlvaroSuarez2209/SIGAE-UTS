<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Punto 5: enlace al formato institucional — texto libre validado solo
 * http/https en la capa de aplicación (ver App\Rules\HttpUrl), nunca en
 * la base. Nullable y sin valor por defecto: las filas existentes
 * quedan intactas (sin enlace), exactamente igual que antes de este
 * cambio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            $table->string('institutional_format_url', 2048)->nullable()->after('completion_criteria');
        });

        Schema::table('deliverable_templates', function (Blueprint $table) {
            $table->string('institutional_format_url', 2048)->nullable()->after('completion_criteria');
        });
    }

    public function down(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            $table->dropColumn('institutional_format_url');
        });

        Schema::table('deliverable_templates', function (Blueprint $table) {
            $table->dropColumn('institutional_format_url');
        });
    }
};
