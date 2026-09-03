<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliverables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliverable_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cross_cutting_commitment_id')->nullable()->constrained()->restrictOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->text('completion_criteria')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->string('periodicity_type');

            $table->dateTime('opens_at');
            $table->dateTime('due_at');
            $table->dateTime('closes_at')->nullable();

            $table->json('allowed_evidence_types');
            $table->json('allowed_file_types')->nullable();
            $table->unsignedInteger('max_files')->default(1);
            $table->unsignedInteger('max_file_size_mb')->default(10);
            $table->unsignedTinyInteger('weight_percentage')->nullable();

            $table->timestamps();
        });

        // Un entregable está ligado a una actividad (de la distribución) o es
        // un compromiso transversal, nunca ambos ni ninguno.
        DB::statement(<<<'SQL'
            ALTER TABLE deliverables
            ADD CONSTRAINT deliverables_exactly_one_scope CHECK (
                (activity_id IS NOT NULL)::int + (cross_cutting_commitment_id IS NOT NULL)::int = 1
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverables');
    }
};
