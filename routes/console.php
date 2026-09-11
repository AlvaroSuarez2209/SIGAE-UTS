<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Vencimiento automático de evidencias — ver App\Console\Commands\MarkOverdueEvidences
// y la sección "Vencimiento automático" del manual técnico (requiere el cron de
// Laravel configurado en el servidor para que esto realmente se ejecute).
Schedule::command('evidences:mark-overdue')->dailyAt('01:00');
