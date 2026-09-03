<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_unit_id')->constrained()->restrictOnDelete();
            $table->decimal('assigned_hours', 5, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'academic_period_id', 'activity_id', 'program_unit_id'],
                'teacher_assignments_unique_combination'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_assignments');
    }
};
