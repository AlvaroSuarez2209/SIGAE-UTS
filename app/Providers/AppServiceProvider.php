<?php

namespace App\Providers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerReadableDateMacro();
    }

    /**
     * Punto único de formato para cualquier fecha mostrada en modo de solo
     * lectura (tablas, tarjetas, informes) — nunca formatear una fecha "a
     * mano" en una vista: usar $fecha->toReadable(). Los inputs de
     * formulario (datepicker, dd/mm/aaaa) no usan esto, siguen su propio
     * formato de edición.
     *
     * Regla: "20 ene 2026", y solo agrega la hora si no es medianoche
     * ("20 abr 2026, 5:00 p. m."). Ver docs/manual-tecnico.md.
     */
    private function registerReadableDateMacro(): void
    {
        $toReadable = function (): string {
            /** @var Carbon|CarbonImmutable $this */
            $date = str_replace('.', '', $this->locale('es')->isoFormat('D MMM YYYY'));

            if ($this->format('H:i') === '00:00') {
                return $date;
            }

            return $date.', '.$this->isoFormat('h:mm a');
        };

        Carbon::macro('toReadable', $toReadable);
        CarbonImmutable::macro('toReadable', $toReadable);
    }
}
