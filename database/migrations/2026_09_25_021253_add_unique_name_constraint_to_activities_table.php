<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Respaldo a nivel de BD contra duplicados exactos — a diferencia de
     * los demás catálogos, "activities" no tenía ninguna restricción de
     * unicidad. La protección real (insensible a mayúsculas/tildes) la
     * hace CaseAccentInsensitiveUnique en el formulario; esto solo cierra
     * la ventana de condición de carrera para el caso de bytes idénticos.
     *
     * subcomponent_id es nullable, y Postgres trata cada NULL como distinto
     * en un índice único normal — un UNIQUE(component_id, subcomponent_id,
     * name) por sí solo NO bloquearía dos actividades con el mismo nombre
     * bajo el mismo componente sin subcomponente. Por eso se agregan dos
     * índices: uno para cuando sí hay subcomponente, y un índice único
     * parcial para cuando no lo hay.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->unique(['component_id', 'subcomponent_id', 'name'], 'activities_component_subcomponent_name_unique');
        });

        DB::statement(
            'create unique index activities_component_name_unique_no_subcomponent '.
            'on activities (component_id, name) where subcomponent_id is null'
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists activities_component_name_unique_no_subcomponent');

        Schema::table('activities', function (Blueprint $table) {
            $table->dropUnique('activities_component_subcomponent_name_unique');
        });
    }
};
