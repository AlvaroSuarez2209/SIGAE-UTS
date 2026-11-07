<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tope semanal de horas por tipo de vinculación — usado por AssignmentForm
 * para validar la distribución docente (ver revisión de la directora).
 * duration_months es puramente informativo (nunca se usa para validar).
 *
 * Los 4 valores insertados abajo son PROVISIONALES hasta que la directora
 * los confirme (ver aviso en App\Livewire\Admin\EmploymentTypeHourLimitIndex)
 * — van en la migración, no en un seeder, porque los seeders usan
 * RefusesInProduction y no corren en producción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_type_hour_limits', function (Blueprint $table) {
            $table->id();
            $table->string('employment_type')->unique();
            $table->unsignedInteger('weekly_hour_limit');
            $table->unsignedInteger('duration_months')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('employment_type_hour_limits')->insert([
            ['employment_type' => 'planta', 'weekly_hour_limit' => 40, 'duration_months' => null, 'created_at' => $now, 'updated_at' => $now],
            ['employment_type' => 'tiempo_completo', 'weekly_hour_limit' => 40, 'duration_months' => null, 'created_at' => $now, 'updated_at' => $now],
            ['employment_type' => 'medio_tiempo_1m', 'weekly_hour_limit' => 20, 'duration_months' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['employment_type' => 'medio_tiempo_4m', 'weekly_hour_limit' => 20, 'duration_months' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_type_hour_limits');
    }
};
