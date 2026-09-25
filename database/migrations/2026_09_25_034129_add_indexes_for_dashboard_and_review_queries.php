<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mismo hallazgo que ya corregimos en audit_logs: `foreignId()->constrained()`
 * no crea un índice en PostgreSQL por sí solo (a diferencia de MySQL/InnoDB).
 * Estas 5 columnas/combinaciones son las que el diagnóstico de rendimiento
 * del Dashboard y la bandeja de revisión encontró filtradas u ordenadas
 * frecuentemente sin ningún índice de apoyo:
 *
 * - evidences.status: Evidence::where('status', ...) en ReviewInbox,
 *   Dashboard (leaderPanel/coordinationPanel) y UserForm.
 * - evidences.user_id: User::evidences() (relación usada en MyDeliverables,
 *   ComplianceCalculator, teacherPanel) — el unique(deliverable_id, user_id)
 *   existente solo cubre user_id como segunda columna, no como filtro
 *   independiente.
 * - deliverables.academic_period_id: filtro repetido en casi toda la app
 *   (Dashboard, DeliverableForm, AssignmentForm, LeadershipForm...).
 * - teacher_assignments (academic_period_id, program_unit_id, activity_id):
 *   Dashboard::leaderPanel() filtra por estas 3 sin tocar user_id (la
 *   columna líder del unique existente), así que ese índice no le sirve.
 * - leaderships (user_id, academic_period_id, program_unit_id): sin ningún
 *   índice hoy; usada en User::leaderships()->where('academic_period_id', ...)
 *   y en el JOIN de Evidence::scopeReviewableBy().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidences', function (Blueprint $table) {
            $table->index('status');
            $table->index('user_id');
        });

        Schema::table('deliverables', function (Blueprint $table) {
            $table->index('academic_period_id');
        });

        Schema::table('teacher_assignments', function (Blueprint $table) {
            $table->index(['academic_period_id', 'program_unit_id', 'activity_id'], 'teacher_assignments_period_program_activity_index');
        });

        Schema::table('leaderships', function (Blueprint $table) {
            $table->index(['user_id', 'academic_period_id', 'program_unit_id'], 'leaderships_user_period_program_index');
        });
    }

    public function down(): void
    {
        Schema::table('evidences', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('deliverables', function (Blueprint $table) {
            $table->dropIndex(['academic_period_id']);
        });

        Schema::table('teacher_assignments', function (Blueprint $table) {
            $table->dropIndex('teacher_assignments_period_program_activity_index');
        });

        Schema::table('leaderships', function (Blueprint $table) {
            $table->dropIndex('leaderships_user_period_program_index');
        });
    }
};
