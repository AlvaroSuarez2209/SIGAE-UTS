<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliverable_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            // FK a evidence_versions se agrega en una migración posterior,
            // ya que esa tabla todavía no existe (dependencia circular).
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();

            $table->unique(['deliverable_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidences');
    }
};
