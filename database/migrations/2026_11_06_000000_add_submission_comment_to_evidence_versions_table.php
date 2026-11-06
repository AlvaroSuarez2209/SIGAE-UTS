<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidence_versions', function (Blueprint $table) {
            $table->text('submission_comment')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('evidence_versions', function (Blueprint $table) {
            $table->dropColumn('submission_comment');
        });
    }
};
