<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_id')->constrained('evidences')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->text('description')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['evidence_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_versions');
    }
};
