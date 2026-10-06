<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prioridad 3 de la revisión de la directora: nombre y logo institucionales
 * configurables desde Administración, en vez de hardcodeados — ver
 * App\Models\InstitutionSettings. Tabla de una sola fila (no un key-value
 * genérico: docs/diccionario-datos.md ya había decidido explícitamente que
 * no hacía falta una tabla `system_parameters`, y sigue sin haber un
 * segundo parámetro global que justifique esa generalización).
 *
 * Dos logos separados, no uno reutilizado: el real tiene dos recortes
 * visuales distintos del isotipo (apaisado 1600×480 para el login,
 * cuadrado 1600×1600 para sidebar/PDF/correos — ver
 * public/images/logo/README.md) y reutilizar una sola imagen subida en
 * ambos contextos deformaría uno de los dos. `logo_login_path` alimenta
 * solo guest.blade.php; `logo_mark_path` alimenta logo-mark.blade.php
 * (sidebar), ReportTheme::logoPath() (PDF) y los correos. Cualquiera de
 * los dos en NULL significa "sin logo personalizado para esa superficie —
 * usar los archivos estáticos actuales de public/images/logo/", nunca una
 * ruta vacía que haya que interpretar — así el sistema se comporta
 * exactamente igual que hoy mientras nadie haya configurado nada, y
 * "restablecer a valores por defecto" es solo volver a dejar ambos en
 * NULL.
 *
 * CHECK(id = 1): singleton garantizado a nivel de base de datos, no solo
 * por convención de aplicación — mismo criterio de defensa en profundidad
 * que ya usa el CHECK de `deliverables` (actividad XOR compromiso
 * transversal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo_login_path')->nullable();
            $table->string('logo_mark_path')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement('alter table institution_settings add constraint institution_settings_singleton check (id = 1)');

        DB::table('institution_settings')->insert([
            'id' => 1,
            'name' => 'Institución Universitaria Tecnológica de Santander',
            'logo_login_path' => null,
            'logo_mark_path' => null,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_settings');
    }
};
