<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Evidence\EvidenceFileDownloadController;
use App\Http\Controllers\Reports\ReportExportController;
use App\Livewire\Admin\Users\UserForm;
use App\Livewire\Admin\Users\UserIndex;
use App\Livewire\Audit\AuditLogIndex;
use App\Livewire\Auth\Login;
use App\Livewire\Catalogs\ActivityIndex;
use App\Livewire\Catalogs\ComponentIndex;
use App\Livewire\Catalogs\CrossCuttingCommitmentIndex;
use App\Livewire\Catalogs\ProgramUnitIndex;
use App\Livewire\Catalogs\SubcomponentIndex;
use App\Livewire\Dashboard;
use App\Livewire\Deliverables\DeliverableForm;
use App\Livewire\Deliverables\DeliverableIndex;
use App\Livewire\Deliverables\TemplateForm;
use App\Livewire\Deliverables\TemplateIndex;
use App\Livewire\Distribution\AssignmentForm;
use App\Livewire\Distribution\AssignmentIndex;
use App\Livewire\Evidence\EvidenceWorkspace;
use App\Livewire\Evidence\MyDeliverables;
use App\Livewire\Leaderships\LeadershipForm;
use App\Livewire\Leaderships\LeadershipIndex;
use App\Livewire\Periods\PeriodIndex;
use App\Livewire\Reports\ActivityReport;
use App\Livewire\Reports\ConsolidatedReport;
use App\Livewire\Reports\CrossCuttingReport;
use App\Livewire\Reports\TeacherReport;
use App\Livewire\Reviews\ReviewInbox;
use App\Livewire\Reviews\ReviewShow;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::post('/logout', LogoutController::class)->name('logout')->middleware('auth');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::prefix('my-deliverables')->name('my-deliverables.')->group(function () {
        Route::get('/', MyDeliverables::class)->name('index');
        Route::get('/{evidence}', EvidenceWorkspace::class)->name('show');
    });

    Route::get('/evidence-files/{evidenceFile}/download', EvidenceFileDownloadController::class)->name('evidence-files.download');

    Route::middleware('role:administrator,coordination,leader')->prefix('reviews')->name('reviews.')->group(function () {
        Route::get('/', ReviewInbox::class)->name('index');
        Route::get('/{evidence}', ReviewShow::class)->name('show');
    });

    Route::middleware('role:administrator')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', UserIndex::class)->name('users.index');
        Route::get('/users/create', UserForm::class)->name('users.create');
        Route::get('/users/{user}/edit', UserForm::class)->name('users.edit');
        Route::get('/audit-logs', AuditLogIndex::class)->name('audit-logs.index');
    });

    Route::middleware('role:administrator,coordination')->group(function () {
        Route::get('/periods', PeriodIndex::class)->name('periods.index');

        Route::prefix('catalogs')->name('catalogs.')->group(function () {
            Route::get('/program-units', ProgramUnitIndex::class)->name('program-units');
            Route::get('/components', ComponentIndex::class)->name('components');
            Route::get('/subcomponents', SubcomponentIndex::class)->name('subcomponents');
            Route::get('/activities', ActivityIndex::class)->name('activities');
            Route::get('/cross-cutting-commitments', CrossCuttingCommitmentIndex::class)->name('cross-cutting-commitments');
        });

        Route::prefix('distribution')->name('distribution.')->group(function () {
            Route::get('/', AssignmentIndex::class)->name('index');
            Route::get('/create', AssignmentForm::class)->name('create');
            Route::get('/{teacherAssignment}/edit', AssignmentForm::class)->name('edit');
        });

        Route::prefix('leaderships')->name('leaderships.')->group(function () {
            Route::get('/', LeadershipIndex::class)->name('index');
            Route::get('/create', LeadershipForm::class)->name('create');
            Route::get('/{leadership}/edit', LeadershipForm::class)->name('edit');
        });

        Route::prefix('deliverable-templates')->name('deliverable-templates.')->group(function () {
            Route::get('/', TemplateIndex::class)->name('index');
            Route::get('/create', TemplateForm::class)->name('create');
            Route::get('/{deliverableTemplate}/edit', TemplateForm::class)->name('edit');
        });

        Route::prefix('deliverables')->name('deliverables.')->group(function () {
            Route::get('/', DeliverableIndex::class)->name('index');
            Route::get('/create', DeliverableForm::class)->name('create');
            Route::get('/{deliverable}/edit', DeliverableForm::class)->name('edit');
        });
    });

    Route::middleware('role:administrator,coordination,auditor')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/teacher', TeacherReport::class)->name('teacher');
        Route::get('/teacher/pdf', [ReportExportController::class, 'teacherPdf'])->name('teacher.pdf');
        Route::get('/teacher/excel', [ReportExportController::class, 'teacherExcel'])->name('teacher.excel');

        Route::get('/activity', ActivityReport::class)->name('activity');
        Route::get('/activity/pdf', [ReportExportController::class, 'activityPdf'])->name('activity.pdf');
        Route::get('/activity/excel', [ReportExportController::class, 'activityExcel'])->name('activity.excel');

        Route::get('/cross-cutting', CrossCuttingReport::class)->name('cross-cutting');
        Route::get('/cross-cutting/pdf', [ReportExportController::class, 'crossCuttingPdf'])->name('cross-cutting.pdf');
        Route::get('/cross-cutting/excel', [ReportExportController::class, 'crossCuttingExcel'])->name('cross-cutting.excel');

        Route::get('/consolidated', ConsolidatedReport::class)->name('consolidated');
        Route::get('/consolidated/pdf', [ReportExportController::class, 'consolidatedPdf'])->name('consolidated.pdf');
        Route::get('/consolidated/excel', [ReportExportController::class, 'consolidatedExcel'])->name('consolidated.excel');
    });
});
