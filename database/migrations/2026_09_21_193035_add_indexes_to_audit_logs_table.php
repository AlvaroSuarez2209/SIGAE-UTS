<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `AuditLogIndex` (bandeja de Auditoría) ordena siempre por `created_at`
 * DESC y opcionalmente filtra por `user_id`, `action` y un rango de
 * `created_at` — las tres columnas que este componente realmente usa en
 * WHERE/ORDER BY no tenían ningún índice de apoyo (`foreignId()` por sí
 * solo no crea uno en PostgreSQL, a diferencia de MySQL/InnoDB, que sí
 * indexa las columnas de llave foránea automáticamente). Con la tabla de
 * demostración (~170 filas) no se nota, pero `audit_logs` es, por
 * diseño, la tabla que más crece sin límite en el tiempo (es de solo
 * escritura, nunca se purga) — sin estos índices, tanto el ORDER BY
 * como el filtro de fecha terminarían haciendo un recorrido secuencial
 * completo de la tabla en producción real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('user_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['action']);
        });
    }
};
