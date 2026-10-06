<?php

namespace App\Services\Reports;

use App\Enums\DeliverableStatus;
use App\Enums\EvidenceStatus;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\CrossCuttingCommitment;
use App\Models\Deliverable;
use App\Models\Evidence;
use App\Models\Leadership;
use App\Models\ProgramUnit;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\ComplianceCalculator;
use App\Services\LeadershipScope;

/**
 * Arma los 4 informes mínimos del módulo 9. Cada informe se devuelve como
 * ['title' => ..., 'file_identifier' => ..., 'sections' => [['title', 'headings', 'rows'], ...], 'summary' => [...]]
 * — la misma estructura sirve para la vista en pantalla, el PDF y el Excel,
 * evitando triplicar la lógica de cada informe por formato de salida.
 *
 * `title` es lo que se ve en pantalla y dentro del documento (PDF/Excel) —
 * puede incluir el nombre completo de una persona sin ningún problema, ya
 * que ahí el usuario que lo está viendo ya sabe a quién corresponde.
 * `file_identifier` es un texto aparte, deliberadamente separado de
 * `title`, que ReportExportController::fileName() convierte en el nombre
 * del archivo descargable (vía Str::slug()) — cuando el título de un
 * informe identificaría a una persona (el docente en `teacher()`),
 * `file_identifier` usa su ID en vez de su nombre, para no dejar un dato
 * personal identificable en el nombre del archivo aunque el contenido del
 * documento sí lo muestre con normalidad. Para los otros 3 informes
 * (`activity()`, `crossCutting()`, `consolidated()`), que no identifican a
 * ninguna persona en su título (nombre de actividad/componente, de un
 * compromiso transversal, o de un periodo — información institucional,
 * no personal), `file_identifier` repite el mismo contenido que `title`.
 * Separar los dos campos explícitamente evita que un cambio futuro al
 * texto de `title` (p. ej. si algún día se agrega el nombre de un líder
 * ahí) se filtre al nombre del archivo sin que nadie lo note.
 *
 * Las horas asignadas solo aparecen como columna informativa; el % de
 * avance (App\Services\ComplianceCalculator) nunca las usa.
 */
class ReportBuilder
{
    public static function teacher(User $teacher, AcademicPeriod $period, ?EvidenceStatus $statusFilter = null): array
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

        // Sin filtrar por estado: es la base real de "cuántos entregables
        // obligatorios tiene este docente" — el % de avance (más abajo)
        // nunca debe depender de qué estado se esté mirando en la tabla.
        $evidences = Evidence::where('user_id', $teacher->id)
            ->whereHas('deliverable', fn ($q) => $q->where('academic_period_id', $period->id))
            ->with(['deliverable.activity.component', 'deliverable.crossCuttingCommitment', 'currentVersion'])
            ->get();

        // El filtro de estado solo acota qué filas se listan en la tabla,
        // nunca lo que entra a ComplianceCalculator (ver comentario arriba).
        $displayEvidences = $statusFilter ? $evidences->where('status', $statusFilter) : $evidences;

        $evidenceRows = $displayEvidences->map(fn (Evidence $e) => [
            $e->deliverable->name,
            self::scopeLabel($e->deliverable),
            $e->deliverable->is_mandatory ? 'Sí' : 'No',
            $e->deliverable->due_at->toReadable(),
            $e->status->label(),
            $e->currentVersion?->submitted_at?->toReadable() ?? '—',
            self::exemptionReasonColumn($e),
        ])->all();

        $compliance = ComplianceCalculator::forUser($teacher, $evidences->pluck('deliverable')->unique('id'), $evidences);

        return [
            'title' => "Informe individual — {$teacher->name} ({$period->name})",
            // El nombre del archivo descargable usa el ID del docente, no su
            // nombre (dato personal) — ver "file_identifier" en la
            // documentación de la clase, más abajo.
            'file_identifier' => "Informe individual {$teacher->id} {$period->name}",
            'sections' => [
                [
                    'title' => 'Distribución (horas informativas, no determinan entregables)',
                    'headings' => ['Componente', 'Actividad', 'Programa', 'Horas asignadas'],
                    'rows' => $assignmentRows,
                ],
                [
                    'title' => 'Entregables y evidencias',
                    'headings' => ['Entregable', 'Ámbito', 'Obligatorio', 'Fecha límite', 'Estado', 'Enviado el', 'Motivo (si Exento)'],
                    'rows' => $evidenceRows,
                ],
            ],
            'summary' => self::complianceSummary($compliance),
        ];
    }

    /**
     * Revisión del estado Exento: a diferencia de teacher(), crossCutting()
     * y consolidated(), ninguna sección de este informe tiene una fila por
     * evidencia individual — "Cumplimiento por docente" es por docente
     * (agregado) y "Entregables de la actividad" es por entregable
     * (conteos). Agregar el motivo de exención aquí obligaría a rediseñar
     * el informe (una sección nueva, fila por evidencia, que hoy no
     * existe) — fuera del alcance de "agregar la razón solo donde ya
     * exista una fila por evidencia".
     */
    public static function activity(Activity $activity, AcademicPeriod $period, ?EvidenceStatus $statusFilter = null): array
    {
        $assignments = TeacherAssignment::where('activity_id', $activity->id)
            ->where('academic_period_id', $period->id)
            ->with(['user', 'programUnit'])
            ->get()
            ->unique('user_id');

        $deliverables = Deliverable::where('activity_id', $activity->id)
            ->where('academic_period_id', $period->id)
            ->where('status', DeliverableStatus::Published)
            ->get();

        $assignmentRows = $assignments->map(fn (TeacherAssignment $a) => [
            $a->user->name,
            self::formatHours($a->assigned_hours),
            $a->programUnit->name,
        ])->all();

        // 1 sola consulta para toda la actividad (antes era 1 por
        // entregable, dentro del map() de abajo) — agrupada por docente
        // para "Cumplimiento por docente" (sin filtro de estado: el % de
        // avance nunca debe depender de qué estado se esté mirando) y por
        // entregable para "Entregables de la actividad" (esa sí respeta el
        // filtro de estado, es un conteo visible, no una entrada a
        // ComplianceCalculator).
        $allEvidences = Evidence::whereIn('deliverable_id', $deliverables->pluck('id'))->get();
        $evidencesByUser = $allEvidences->groupBy('user_id');
        $displayEvidencesByDeliverable = ($statusFilter ? $allEvidences->where('status', $statusFilter) : $allEvidences)
            ->groupBy('deliverable_id');

        $complianceRows = $assignments->map(function (TeacherAssignment $a) use ($deliverables, $evidencesByUser) {
            $compliance = ComplianceCalculator::forUser($a->user, $deliverables, $evidencesByUser->get($a->user_id, collect()));

            return [
                $a->user->name,
                $compliance['total'],
                $compliance['approved'],
                self::percentageLabel($compliance['percentage']),
            ];
        })->all();

        $deliverableRows = $deliverables->map(function (Deliverable $d) use ($displayEvidencesByDeliverable) {
            $evidences = $displayEvidencesByDeliverable->get($d->id, collect());

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
            // Sin datos personales (nombre de componente/actividad, no de
            // ninguna persona) — el archivo puede describirse igual que el
            // título, sin necesidad de anonimizar.
            'file_identifier' => "Informe por actividad {$activity->component->name} {$activity->name} {$period->name}",
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

    public static function crossCutting(AcademicPeriod $period, ?CrossCuttingCommitment $commitment = null, ?EvidenceStatus $statusFilter = null): array
    {
        $deliverables = Deliverable::whereNotNull('cross_cutting_commitment_id')
            ->where('academic_period_id', $period->id)
            ->where('status', DeliverableStatus::Published)
            ->when($commitment, fn ($q) => $q->where('cross_cutting_commitment_id', $commitment->id))
            ->with('crossCuttingCommitment')
            ->get();

        // 1 sola consulta para todos los entregables transversales (antes
        // eran 2: una por entregable dentro del map() de abajo, más esta
        // misma whereIn aparte para $detailRows) — el filtro de estado solo
        // acota qué se lista, este informe no calcula ningún % de avance.
        $allEvidences = Evidence::whereIn('deliverable_id', $deliverables->pluck('id'))
            ->with(['user', 'deliverable.crossCuttingCommitment', 'currentVersion'])
            ->get();
        $displayEvidences = $statusFilter ? $allEvidences->where('status', $statusFilter) : $allEvidences;
        $displayEvidencesByDeliverable = $displayEvidences->groupBy('deliverable_id');

        $deliverableRows = $deliverables->map(function (Deliverable $d) use ($displayEvidencesByDeliverable) {
            $evidences = $displayEvidencesByDeliverable->get($d->id, collect());

            return [
                $d->crossCuttingCommitment->name,
                $d->name,
                $d->due_at->toReadable(),
                $evidences->count(),
                $evidences->where('status', EvidenceStatus::Submitted)->count(),
                $evidences->where('status', EvidenceStatus::Approved)->count(),
            ];
        })->all();

        $detailRows = $displayEvidences
            ->map(fn (Evidence $e) => [
                $e->deliverable->crossCuttingCommitment->name,
                $e->deliverable->name,
                $e->user->name,
                $e->status->label(),
                $e->currentVersion?->submitted_at?->toReadable() ?? '—',
                self::exemptionReasonColumn($e),
            ])->all();

        return [
            'title' => 'Informe de compromisos transversales — '.($commitment?->name ?? 'Todos').' ('.$period->name.')',
            // Sin datos personales (nombre del compromiso transversal, una
            // clasificación institucional, no de ninguna persona).
            'file_identifier' => 'Informe de compromisos transversales '.($commitment?->name ?? 'Todos').' '.$period->name,
            'sections' => [
                [
                    'title' => 'Entregables transversales',
                    'headings' => ['Compromiso', 'Entregable', 'Fecha límite', 'Destinatarios', 'Enviados', 'Aprobados'],
                    'rows' => $deliverableRows,
                ],
                [
                    'title' => 'Detalle por docente',
                    'headings' => ['Compromiso', 'Entregable', 'Docente', 'Estado', 'Enviado el', 'Motivo (si Exento)'],
                    'rows' => $detailRows,
                ],
            ],
        ];
    }

    public static function consolidated(
        AcademicPeriod $period,
        ?ProgramUnit $program = null,
        ?User $teacher = null,
        ?Activity $activity = null,
        ?User $leader = null,
        ?EvidenceStatus $statusFilter = null,
    ): array {
        $ledTeacherIds = $leader ? LeadershipScope::teacherIdsLedBy($leader, $period) : null;

        // programa/docente/actividad/líder acotan la POBLACIÓN (qué
        // evidencias entran aquí); el filtro de estado se aplica aparte
        // (ver $displayEvidences más abajo) para que nunca afecte el % de
        // avance de "Resumen por docente" — solo qué se lista.
        $evidences = Evidence::whereHas('deliverable', fn ($q) => $q
            ->where('academic_period_id', $period->id)
            ->when($activity, fn ($aq) => $aq->where('activity_id', $activity->id))
        )
            ->when($teacher, fn ($q) => $q->where('user_id', $teacher->id))
            ->when($program, fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('program_unit_id', $program->id)))
            ->when($ledTeacherIds !== null, fn ($q) => $q->whereIn('user_id', $ledTeacherIds))
            ->with(['user', 'deliverable.activity.component', 'deliverable.crossCuttingCommitment'])
            ->get();

        $displayEvidences = $statusFilter ? $evidences->where('status', $statusFilter) : $evidences;

        $consolidatedRows = $displayEvidences->map(function (Evidence $e) use ($period) {
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
                self::exemptionReasonColumn($e),
            ];
        })->all();

        $statusRows = collect(EvidenceStatus::cases())->map(fn ($status) => [
            $status->label(),
            $displayEvidences->where('status', $status)->count(),
        ])->all();

        // $evidences (sin filtrar por estado), nunca $displayEvidences: el
        // % de avance de cada docente no debe cambiar según qué estado se
        // esté mirando en las otras 2 secciones.
        $evidencesByUser = $evidences->groupBy('user_id');

        $teacherRows = $evidences->pluck('user')->unique('id')->map(function (User $teacher) use ($period, $evidencesByUser) {
            $deliverables = Deliverable::where('academic_period_id', $period->id)
                ->where('status', DeliverableStatus::Published)
                ->whereHas('recipients', fn ($q) => $q->where('user_id', $teacher->id))
                ->get();
            $compliance = ComplianceCalculator::forUser($teacher, $deliverables, $evidencesByUser->get($teacher->id, collect()));

            return [$teacher->name, $compliance['approved'], $compliance['total'], self::percentageLabel($compliance['percentage'])];
        })->all();

        return [
            'title' => "Consolidado del periodo {$period->name}",
            // Sin datos personales — nunca identifica a ningún docente.
            'file_identifier' => "Consolidado del periodo {$period->name}",
            'sections' => [
                [
                    'title' => 'Consolidado por docente / componente / actividad / líder / estado',
                    'headings' => ['Docente', 'Componente', 'Actividad / Transversal', 'Líder(es)', 'Entregable', 'Obligatorio', 'Estado', 'Motivo (si Exento)'],
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

    /**
     * Revisión del estado Exento: '—' para cualquier fila que no esté
     * Exenta (igual convención que el resto de columnas "no aplica" de
     * estos informes, ej. "Enviado el"), y el motivo real solo cuando sí
     * lo está. No se agrega a activity() — ver el comentario en ese
     * método — porque ninguna de sus secciones tiene una fila por
     * evidencia individual.
     */
    private static function exemptionReasonColumn(Evidence $e): string
    {
        return $e->status === EvidenceStatus::Exempt ? ($e->exemption_reason ?? '—') : '—';
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
