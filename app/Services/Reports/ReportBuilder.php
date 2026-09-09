<?php

namespace App\Services\Reports;

use App\Enums\EvidenceStatus;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\CrossCuttingCommitment;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\Leadership;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\ComplianceCalculator;

/**
 * Arma los 4 informes mínimos del módulo 9. Cada informe se devuelve como
 * ['title' => ..., 'sections' => [['title', 'headings', 'rows'], ...], 'summary' => [...]]
 * — la misma estructura sirve para la vista en pantalla, el PDF y el Excel,
 * evitando triplicar la lógica de cada informe por formato de salida.
 *
 * Las horas asignadas solo aparecen como columna informativa; el % de
 * avance (App\Services\ComplianceCalculator) nunca las usa.
 */
class ReportBuilder
{
    public static function teacher(User $teacher, AcademicPeriod $period): array
    {
        $assignments = TeacherAssignment::where('user_id', $teacher->id)
            ->where('academic_period_id', $period->id)
            ->with(['activity.component', 'programUnit'])
            ->get();

        $assignmentRows = $assignments->map(fn (TeacherAssignment $a) => [
            $a->activity->component->name,
            $a->activity->name,
            $a->programUnit->name,
            self::formatHours($a->assigned_hours),
        ])->all();

        $evidences = Evidence::where('user_id', $teacher->id)
            ->whereHas('deliverable', fn ($q) => $q->where('academic_period_id', $period->id))
            ->with(['deliverable.activity.component', 'deliverable.crossCuttingCommitment', 'currentVersion'])
            ->get();

        $evidenceRows = $evidences->map(fn (Evidence $e) => [
            $e->deliverable->name,
            self::scopeLabel($e->deliverable),
            $e->deliverable->is_mandatory ? 'Sí' : 'No',
            $e->deliverable->due_at->toReadable(),
            $e->status->label(),
            $e->currentVersion?->submitted_at?->toReadable() ?? '—',
        ])->all();

        $compliance = ComplianceCalculator::forUser($teacher, $evidences->pluck('deliverable')->unique('id'));

        return [
            'title' => "Informe individual — {$teacher->name} ({$period->name})",
            'sections' => [
                [
                    'title' => 'Distribución (horas informativas, no determinan entregables)',
                    'headings' => ['Componente', 'Actividad', 'Programa', 'Horas asignadas'],
                    'rows' => $assignmentRows,
                ],
                [
                    'title' => 'Entregables y evidencias',
                    'headings' => ['Entregable', 'Ámbito', 'Obligatorio', 'Fecha límite', 'Estado', 'Enviado el'],
                    'rows' => $evidenceRows,
                ],
            ],
            'summary' => self::complianceSummary($compliance),
        ];
    }

    public static function activity(Activity $activity, AcademicPeriod $period): array
    {
        $assignments = TeacherAssignment::where('activity_id', $activity->id)
            ->where('academic_period_id', $period->id)
            ->with(['user', 'programUnit'])
            ->get()
            ->unique('user_id');

        $deliverables = Deliverable::where('activity_id', $activity->id)
            ->where('academic_period_id', $period->id)
            ->get();

        $assignmentRows = $assignments->map(fn (TeacherAssignment $a) => [
            $a->user->name,
            self::formatHours($a->assigned_hours),
            $a->programUnit->name,
        ])->all();

        $complianceRows = $assignments->map(function (TeacherAssignment $a) use ($deliverables) {
            $compliance = ComplianceCalculator::forUser($a->user, $deliverables);

            return [
                $a->user->name,
                $compliance['total'],
                $compliance['approved'],
                self::percentageLabel($compliance['percentage']),
            ];
        })->all();

        $deliverableRows = $deliverables->map(function (Deliverable $d) {
            $evidences = Evidence::where('deliverable_id', $d->id)->get();

            return [
                $d->name,
                $d->is_mandatory ? 'Sí' : 'No',
                $d->due_at->toReadable(),
                $evidences->count(),
                $evidences->where('status', EvidenceStatus::Submitted)->count(),
                $evidences->where('status', EvidenceStatus::Approved)->count(),
            ];
        })->all();

        return [
            'title' => "Informe por actividad — {$activity->component->name} / {$activity->name} ({$period->name})",
            'sections' => [
                [
                    'title' => 'Docentes asignados (horas informativas)',
                    'headings' => ['Docente', 'Horas asignadas', 'Programa'],
                    'rows' => $assignmentRows,
                ],
                [
                    'title' => 'Cumplimiento por docente',
                    'headings' => ['Docente', 'Obligatorios', 'Aprobados', '% Avance'],
                    'rows' => $complianceRows,
                ],
                [
                    'title' => 'Entregables de la actividad',
                    'headings' => ['Entregable', 'Obligatorio', 'Fecha límite', 'Destinatarios', 'Enviados', 'Aprobados'],
                    'rows' => $deliverableRows,
                ],
            ],
        ];
    }

    public static function crossCutting(AcademicPeriod $period, ?CrossCuttingCommitment $commitment = null): array
    {
        $deliverables = Deliverable::whereNotNull('cross_cutting_commitment_id')
            ->where('academic_period_id', $period->id)
            ->when($commitment, fn ($q) => $q->where('cross_cutting_commitment_id', $commitment->id))
            ->with('crossCuttingCommitment')
            ->get();

        $deliverableRows = $deliverables->map(function (Deliverable $d) {
            $evidences = Evidence::where('deliverable_id', $d->id)->get();

            return [
                $d->crossCuttingCommitment->name,
                $d->name,
                $d->due_at->toReadable(),
                $evidences->count(),
                $evidences->where('status', EvidenceStatus::Submitted)->count(),
                $evidences->where('status', EvidenceStatus::Approved)->count(),
            ];
        })->all();

        $detailRows = Evidence::whereIn('deliverable_id', $deliverables->pluck('id'))
            ->with(['user', 'deliverable.crossCuttingCommitment', 'currentVersion'])
            ->get()
            ->map(fn (Evidence $e) => [
                $e->deliverable->crossCuttingCommitment->name,
                $e->deliverable->name,
                $e->user->name,
                $e->status->label(),
                $e->currentVersion?->submitted_at?->toReadable() ?? '—',
            ])->all();

        return [
            'title' => 'Informe de compromisos transversales — '.($commitment?->name ?? 'Todos').' ('.$period->name.')',
            'sections' => [
                [
                    'title' => 'Entregables transversales',
                    'headings' => ['Compromiso', 'Entregable', 'Fecha límite', 'Destinatarios', 'Enviados', 'Aprobados'],
                    'rows' => $deliverableRows,
                ],
                [
                    'title' => 'Detalle por docente',
                    'headings' => ['Compromiso', 'Entregable', 'Docente', 'Estado', 'Enviado el'],
                    'rows' => $detailRows,
                ],
            ],
        ];
    }

    public static function consolidated(AcademicPeriod $period): array
    {
        $evidences = Evidence::whereHas('deliverable', fn ($q) => $q->where('academic_period_id', $period->id))
            ->with(['user', 'deliverable.activity.component', 'deliverable.crossCuttingCommitment'])
            ->get();

        $consolidatedRows = $evidences->map(function (Evidence $e) use ($period) {
            $deliverable = $e->deliverable;

            if ($deliverable->isCrossCutting()) {
                $componente = 'Transversal';
                $actividad = $deliverable->crossCuttingCommitment->name;
                $lideres = '—';
            } else {
                $componente = $deliverable->activity->component->name;
                $actividad = $deliverable->activity->name;
                $lideres = self::leadersFor($e, $period);
            }

            return [
                $e->user->name,
                $componente,
                $actividad,
                $lideres,
                $deliverable->name,
                $deliverable->is_mandatory ? 'Sí' : 'No',
                $e->status->label(),
            ];
        })->all();

        $statusRows = collect(EvidenceStatus::cases())->map(fn ($status) => [
            $status->label(),
            $evidences->where('status', $status)->count(),
        ])->all();

        $teacherRows = $evidences->pluck('user')->unique('id')->map(function (User $teacher) use ($period) {
            $deliverables = Deliverable::where('academic_period_id', $period->id)
                ->whereHas('recipients', fn ($q) => $q->where('user_id', $teacher->id))
                ->get();
            $compliance = ComplianceCalculator::forUser($teacher, $deliverables);

            return [$teacher->name, $compliance['approved'], $compliance['total'], self::percentageLabel($compliance['percentage'])];
        })->all();

        return [
            'title' => "Consolidado del periodo {$period->name}",
            'sections' => [
                [
                    'title' => 'Consolidado por docente / componente / actividad / líder / estado',
                    'headings' => ['Docente', 'Componente', 'Actividad / Transversal', 'Líder(es)', 'Entregable', 'Obligatorio', 'Estado'],
                    'rows' => $consolidatedRows,
                ],
                [
                    'title' => 'Resumen por estado',
                    'headings' => ['Estado', 'Cantidad'],
                    'rows' => $statusRows,
                ],
                [
                    'title' => 'Resumen por docente',
                    'headings' => ['Docente', 'Aprobados', 'Obligatorios', '% Avance'],
                    'rows' => $teacherRows,
                ],
            ],
        ];
    }

    private static function leadersFor(Evidence $evidence, AcademicPeriod $period): string
    {
        $assignment = $evidence->matchingTeacherAssignment();

        if (! $assignment) {
            return '—';
        }

        $names = Leadership::where('academic_period_id', $period->id)
            ->where('program_unit_id', $assignment->program_unit_id)
            ->where(fn ($q) => $q->whereNull('activity_id')->orWhere('activity_id', $assignment->activity_id))
            ->with('user')
            ->get()
            ->pluck('user.name')
            ->unique();

        return $names->isEmpty() ? '—' : $names->join(', ');
    }

    private static function scopeLabel(Deliverable $deliverable): string
    {
        return $deliverable->isCrossCutting()
            ? 'Transversal: '.$deliverable->crossCuttingCommitment->name
            : $deliverable->activity->component->name.' — '.$deliverable->activity->name;
    }

    private static function formatHours(mixed $hours): string
    {
        return rtrim(rtrim((string) $hours, '0'), '.');
    }

    private static function percentageLabel(?float $percentage): string
    {
        return $percentage === null ? 'Sin entregables obligatorios' : "{$percentage}%";
    }

    /**
     * @param  array{percentage: ?float, approved: int, total: int}  $compliance
     */
    private static function complianceSummary(array $compliance): array
    {
        return [
            'label' => '% de avance (solo entregables obligatorios, no depende de las horas)',
            'value' => self::percentageLabel($compliance['percentage']),
            'detail' => "{$compliance['approved']} de {$compliance['total']} obligatorios aprobados",
        ];
    }
}
