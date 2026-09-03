<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliverable_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->text('completion_criteria')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->string('periodicity_type');
            $table->json('allowed_evidence_types');
            $table->json('allowed_file_types')->nullable();
            $table->unsignedInteger('max_files')->default(1);
            $table->unsignedInteger('max_file_size_mb')->default(10);
            $table->unsignedTinyInteger('weight_percentage')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverable_templates');
    }
};
