<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcomponents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['component_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcomponents');
    }
};
