<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_version_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('stored_name')->unique();
            $table->string('disk_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_files');
    }
};
