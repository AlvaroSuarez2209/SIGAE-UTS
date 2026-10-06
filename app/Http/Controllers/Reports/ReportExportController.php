<?php

namespace App\Http\Controllers\Reports;

use App\Enums\EvidenceStatus;
use App\Http\Controllers\Concerns\ExportsTabularReport;
use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\CrossCuttingCommitment;
use App\Models\ProgramUnit;
use App\Models\User;
use App\Services\Reports\ReportBuilder;
use Illuminate\Http\Request;

class ReportExportController extends Controller
{
    use ExportsTabularReport;

    private function statusFromQuery(Request $request): ?EvidenceStatus
    {
        return $request->query('status') ? EvidenceStatus::from($request->query('status')) : null;
    }

    public function teacherPdf(Request $request)
    {
        $teacher = User::findOrFail($request->query('teacher'));
        $period = AcademicPeriod::findOrFail($request->query('period'));

        return $this->safeExport('reports.teacher', $request->query(), fn () => $this->pdf(
            ReportBuilder::teacher($teacher, $period, $this->statusFromQuery($request)),
            $this->filtersSummary(['Periodo' => $period->name, 'Estado' => $this->statusFromQuery($request)?->label()]),
        ));
    }

    public function teacherExcel(Request $request)
    {
        $teacher = User::findOrFail($request->query('teacher'));
        $period = AcademicPeriod::findOrFail($request->query('period'));

        return $this->safeExport('reports.teacher', $request->query(), fn () => $this->excel(
            ReportBuilder::teacher($teacher, $period, $this->statusFromQuery($request)),
        ));
    }

    public function activityPdf(Request $request)
    {
        $activity = Activity::with('component')->findOrFail($request->query('activity'));
        $period = AcademicPeriod::findOrFail($request->query('period'));

        return $this->safeExport('reports.activity', $request->query(), fn () => $this->pdf(
            ReportBuilder::activity($activity, $period, $this->statusFromQuery($request)),
            $this->filtersSummary(['Periodo' => $period->name, 'Estado' => $this->statusFromQuery($request)?->label()]),
        ));
    }

    public function activityExcel(Request $request)
    {
        $activity = Activity::with('component')->findOrFail($request->query('activity'));
        $period = AcademicPeriod::findOrFail($request->query('period'));

        return $this->safeExport('reports.activity', $request->query(), fn () => $this->excel(
            ReportBuilder::activity($activity, $period, $this->statusFromQuery($request)),
        ));
    }

    public function crossCuttingPdf(Request $request)
    {
        $period = AcademicPeriod::findOrFail($request->query('period'));
        $commitment = $request->query('commitment') ? CrossCuttingCommitment::find($request->query('commitment')) : null;

        return $this->safeExport('reports.cross-cutting', $request->query(), fn () => $this->pdf(
            ReportBuilder::crossCutting($period, $commitment, $this->statusFromQuery($request)),
            $this->filtersSummary([
                'Periodo' => $period->name,
                'Compromiso' => $commitment?->name,
                'Estado' => $this->statusFromQuery($request)?->label(),
            ]),
        ));
    }

    public function crossCuttingExcel(Request $request)
    {
        $period = AcademicPeriod::findOrFail($request->query('period'));
        $commitment = $request->query('commitment') ? CrossCuttingCommitment::find($request->query('commitment')) : null;

        return $this->safeExport('reports.cross-cutting', $request->query(), fn () => $this->excel(
            ReportBuilder::crossCutting($period, $commitment, $this->statusFromQuery($request)),
        ));
    }

    public function consolidatedPdf(Request $request)
    {
        $period = AcademicPeriod::findOrFail($request->query('period'));
        $program = $request->query('program') ? ProgramUnit::find($request->query('program')) : null;
        $teacher = $request->query('teacher') ? User::find($request->query('teacher')) : null;
        $activity = $request->query('activity') ? Activity::with('component')->find($request->query('activity')) : null;
        $leader = $request->query('leader') ? User::find($request->query('leader')) : null;
        $statusFilter = $this->statusFromQuery($request);

        return $this->safeExport('reports.consolidated', $request->query(), fn () => $this->pdf(
            ReportBuilder::consolidated($period, $program, $teacher, $activity, $leader, $statusFilter),
            $this->filtersSummary([
                'Periodo' => $period->name,
                'Programa' => $program?->name,
                'Docente' => $teacher?->name,
                'Actividad' => $activity ? "{$activity->component->name} — {$activity->name}" : null,
                'Líder' => $leader?->name,
                'Estado' => $statusFilter?->label(),
            ]),
        ));
    }

    public function consolidatedExcel(Request $request)
    {
        $period = AcademicPeriod::findOrFail($request->query('period'));
        $program = $request->query('program') ? ProgramUnit::find($request->query('program')) : null;
        $teacher = $request->query('teacher') ? User::find($request->query('teacher')) : null;
        $activity = $request->query('activity') ? Activity::with('component')->find($request->query('activity')) : null;
        $leader = $request->query('leader') ? User::find($request->query('leader')) : null;

        return $this->safeExport('reports.consolidated', $request->query(), fn () => $this->excel(
            ReportBuilder::consolidated($period, $program, $teacher, $activity, $leader, $this->statusFromQuery($request)),
        ));
    }
}
