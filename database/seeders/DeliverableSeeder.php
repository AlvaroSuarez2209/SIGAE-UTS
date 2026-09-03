<?php

namespace Database\Seeders;

use App\Enums\EvidenceType;
use App\Enums\PeriodicityType;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\CrossCuttingCommitment;
use App\Models\Deliverable;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;

class DeliverableSeeder extends Seeder
{
    public function run(): void
    {
        $period = AcademicPeriod::where('name', '2026-1')->first();

        // Escenario obligatorio: la actividad de 5 horas (Dirección de
        // trabajos de grado) recibe dos entregables, no cinco — las horas
        // asignadas nunca determinan la cantidad de entregables.
        $thesisDirection = Activity::where('name', 'Dirección de trabajos de grado')->first();
        $this->createForActivity($period, $thesisDirection, [
            [
                'name' => 'Propuesta de trabajo de grado',
                'is_mandatory' => true,
                'periodicity_type' => PeriodicityType::Single,
                'evidence_types' => [EvidenceType::File],
                'file_types' => ['pdf'],
                'days_to_due' => 20,
            ],
            [
                'name' => 'Informe de avance de dirección',
                'is_mandatory' => true,
                'periodicity_type' => PeriodicityType::ByTerm,
                'evidence_types' => [EvidenceType::File, EvidenceType::Text],
                'file_types' => ['pdf', 'docx'],
                'days_to_due' => 60,
            ],
        ]);

        $classes = Activity::where('name', 'Clases teóricas')->first();
        $this->createForActivity($period, $classes, [
            [
                'name' => 'Syllabus del curso',
                'is_mandatory' => true,
                'periodicity_type' => PeriodicityType::Single,
                'evidence_types' => [EvidenceType::File],
                'file_types' => ['pdf'],
                'days_to_due' => 10,
            ],
        ]);

        $commitment = CrossCuttingCommitment::where('name', 'Capacitación institucional')->first();
        $recipients = User::where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', 'teacher'))
            ->get();

        $deliverable = Deliverable::firstOrCreate([
            'academic_period_id' => $period->id,
            'cross_cutting_commitment_id' => $commitment->id,
            'name' => 'Certificado de capacitación en TIC',
        ], [
            'is_mandatory' => false,
            'periodicity_type' => PeriodicityType::Extraordinary,
            'opens_at' => $period->start_date,
            'due_at' => $period->start_date->copy()->addDays(90),
            'allowed_evidence_types' => [EvidenceType::File->value],
            'allowed_file_types' => ['pdf', 'jpg'],
            'max_files' => 1,
            'max_file_size_mb' => 5,
        ]);

        $deliverable->recipients()->sync($recipients->pluck('id'));
        $deliverable->ensureEvidencesForRecipients($recipients->pluck('id')->all());
    }

    private function createForActivity(AcademicPeriod $period, Activity $activity, array $definitions): void
    {
        $recipients = TeacherAssignment::where('academic_period_id', $period->id)
            ->where('activity_id', $activity->id)
            ->pluck('user_id');

        foreach ($definitions as $definition) {
            $deliverable = Deliverable::firstOrCreate([
                'academic_period_id' => $period->id,
                'activity_id' => $activity->id,
                'name' => $definition['name'],
            ], [
                'is_mandatory' => $definition['is_mandatory'],
                'periodicity_type' => $definition['periodicity_type'],
                'opens_at' => $period->start_date,
                'due_at' => $period->start_date->copy()->addDays($definition['days_to_due']),
                'allowed_evidence_types' => array_map(fn ($type) => $type->value, $definition['evidence_types']),
                'allowed_file_types' => $definition['file_types'],
                'max_files' => 1,
                'max_file_size_mb' => 10,
            ]);

            $deliverable->recipients()->sync($recipients);
            $deliverable->ensureEvidencesForRecipients($recipients->all());
        }
    }
}
