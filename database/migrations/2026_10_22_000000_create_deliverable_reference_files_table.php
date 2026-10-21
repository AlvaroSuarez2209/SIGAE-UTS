<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-038: archivo(s) de referencia (plantilla/instructivo/formato) que
 * Administración/Coordinación puede adjuntar a un entregable, además del
 * texto libre de `instructions`. Mismo patrón que `evidence_files`:
 * `stored_name` aleatorio en disco, `original_name` solo como dato,
 * nunca una ruta pública. `is_active` en vez de borrado físico — mismo
 * principio de "desactivar, nunca eliminar" que ya usan los catálogos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliverable_reference_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliverable_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('stored_name')->unique();
            $table->string('disk_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverable_reference_files');
    }
};
