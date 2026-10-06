<?php

namespace App\Http\Controllers\Admin;

use App\Exports\TeacherImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

class TeacherImportTemplateController extends Controller
{
    public function __invoke()
    {
        Gate::authorize('create', User::class);

        return Excel::download(new TeacherImportTemplateExport, 'plantilla-importacion-docentes.xlsx');
    }
}
