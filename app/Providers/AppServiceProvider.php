<?php

namespace App\Providers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Transport;

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
        $this->registerBrevoApiMailTransport();
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

    /**
     * Render bloquea el tráfico saliente por los puertos SMTP (25/465/587)
     * en su plan gratuito desde septiembre de 2025 — Brevo por SMTP nunca
     * va a funcionar ahí, sin importar la configuración; en local (sin
     * ese bloqueo) sigue andando bien, por eso el síntoma real era un
     * error 500 solo en Render. La API HTTP de Brevo corre por HTTPS
     * (443, no bloqueado), así que ese entorno usa MAIL_MAILER=brevo en
     * vez de smtp.
     *
     * symfony/brevo-mailer ya registra el esquema "brevo+api" dentro de
     * Symfony\Component\Mailer\Transport::FACTORY_CLASSES con solo
     * instalar el paquete (Transport la detecta vía class_exists(), sin
     * ningún registro manual) — este método solo conecta ese DSN con el
     * sistema de mailers de Laravel vía Mail::extend(), que
     * MailManager::createSymfonyTransport() consulta antes que sus
     * transportes nativos (smtp, ses, postmark...).
     *
     * Mailpit en local sigue por el mailer "smtp" de siempre, sin ningún
     * cambio. Ver "Despliegue en entorno de pruebas" en
     * docs/manual-tecnico.md.
     */
    private function registerBrevoApiMailTransport(): void
    {
        Mail::extend('brevo', function () {
            return Transport::fromDsn(sprintf(
                'brevo+api://%s@default',
                rawurlencode((string) config('services.brevo.key'))
            ));
        });
    }
}
