<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prioridad 4 de la revisión de la directora: a diferencia de los demás
 * catálogos del sistema (components, subcomponents, activities,
 * program_units, cross_cutting_commitments), deliverable_templates no
 * tenía ninguna restricción de unicidad de nombre — ni en base de datos ni
 * en el formulario (TemplateForm solo validaba required|string|max:255).
 * La protección real (insensible a mayúsculas/tildes) la hace
 * CaseAccentInsensitiveUnique en TemplateForm::save(); este unique de BD
 * es solo el respaldo contra condición de carrera, mismo criterio que ya
 * tienen los demás catálogos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliverable_templates', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('deliverable_templates', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};
