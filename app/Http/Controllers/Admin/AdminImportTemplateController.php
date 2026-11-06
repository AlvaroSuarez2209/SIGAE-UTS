<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AdminImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

class AdminImportTemplateController extends Controller
{
    public function __invoke()
    {
        Gate::authorize('importAdministratorAccounts', User::class);

        return Excel::download(new AdminImportTemplateExport, 'plantilla-importacion-administradores.xlsx');
    }
}
