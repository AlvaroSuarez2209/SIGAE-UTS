<?php

namespace App\Http\Controllers\Reports;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Activity;
use App\Models\CrossCuttingCommitment;
use App\Models\User;
use App\Services\Reports\ReportBuilder;
use App\Services\Reports\ReportTheme;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ReportExportController extends Controller
{
    public function teacherPdf(Request $request)
    {
        $report = ReportBuilder::teacher(
            User::findOrFail($request->query('teacher')),
            AcademicPeriod::findOrFail($request->query('period'))
        );

        return $this->pdf($report);
    }

    public function teacherExcel(Request $request)
    {
        $report = ReportBuilder::teacher(
            User::findOrFail($request->query('teacher')),
            AcademicPeriod::findOrFail($request->query('period'))
        );

        return $this->excel($report);
    }

    public function activityPdf(Request $request)
    {
        $report = ReportBuilder::activity(
            Activity::with('component')->findOrFail($request->query('activity')),
            AcademicPeriod::findOrFail($request->query('period'))
        );

        return $this->pdf($report);
    }

    public function activityExcel(Request $request)
    {
        $report = ReportBuilder::activity(
            Activity::with('component')->findOrFail($request->query('activity')),
            AcademicPeriod::findOrFail($request->query('period'))
        );

        return $this->excel($report);
    }

    public function crossCuttingPdf(Request $request)
    {
        $report = ReportBuilder::crossCutting(
            AcademicPeriod::findOrFail($request->query('period')),
            $request->query('commitment') ? CrossCuttingCommitment::find($request->query('commitment')) : null
        );

        return $this->pdf($report);
    }

    public function crossCuttingExcel(Request $request)
    {
        $report = ReportBuilder::crossCutting(
            AcademicPeriod::findOrFail($request->query('period')),
            $request->query('commitment') ? CrossCuttingCommitment::find($request->query('commitment')) : null
        );

        return $this->excel($report);
    }

    public function consolidatedPdf(Request $request)
    {
        $report = ReportBuilder::consolidated(AcademicPeriod::findOrFail($request->query('period')));

        return $this->pdf($report);
    }

    public function consolidatedExcel(Request $request)
    {
        $report = ReportBuilder::consolidated(AcademicPeriod::findOrFail($request->query('period')));

        return $this->excel($report);
    }

    private function pdf(array $report)
    {
        $pdf = Pdf::loadView('reports.pdf.report', $report);

        // Numeración de páginas vía canvas nativo de DomPDF, no CSS — ver
        // ReportTheme::stampPageNumbers() para el motivo.
        ReportTheme::stampPageNumbers($pdf);

        return $pdf->download($this->fileName($report['title'], 'pdf'));
    }

    private function excel(array $report)
    {
        return Excel::download(new ReportExport($report), $this->fileName($report['title'], 'xlsx'));
    }

    private function fileName(string $title, string $extension): string
    {
        return Str::slug($title).'.'.$extension;
    }
}
