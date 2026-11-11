<?php

namespace App\Providers;

use Aws\CommandInterface;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
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
        $this->registerAccentInsensitiveSearchMacro();
        $this->registerAccentInsensitiveCollectionSortMacro();
        $this->stripAclFromNeonUploads();

        // Red de seguridad preventiva contra N+1 nuevos: lanza una excepción
        // en vez de disparar una consulta silenciosa al acceder a una
        // relación no cargada. Solo en local (nunca en producción/testing):
        // el objetivo es que aparezca en el desarrollo diario, no que un
        // caso límite no previsto tumbe una request real en Render.
        Model::preventLazyLoading($this->app->isLocal());
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

    /**
     * Punto 1 (almacenamiento persistente): Neon Object Storage no
     * implementa ACL — cualquier subida que lleve la cabecera
     * "x-amz-acl" la rechaza. Quitar 'visibility' de config/filesystems.php
     * NO alcanza por sí solo: verificado con un cliente S3 real (no solo
     * Storage::fake) que, sin ningún 'visibility' configurado, el SDK de
     * AWS igual manda "x-amz-acl: private" por defecto en cada
     * PutObject/multipart — y que fijar 'options' => ['ACL' => ''] en el
     * disco tampoco sirve: el SDK manda la cabecera igual, solo con el
     * valor vacío ("x-amz-acl: "), que Neon rechaza lo mismo.
     *
     * Lo que sí funciona, verificado con ese mismo cliente real: un
     * middleware en el paso "build" del propio Aws\S3\S3Client (antes de
     * que el comando se serialice a una petición HTTP) que quita el
     * parámetro ACL del comando — ahí la cabecera nunca llega a
     * generarse, sin importar qué pida Flysystem por encima.
     * prependBuild() (no appendBuild()): tiene que correr ANTES del paso
     * de serialización interno del SDK, o ya es tarde — con
     * appendBuild() el comando queda limpio pero la petición ya se
     * había construido con la cabecera puesta.
     *
     * Solo se activa si el disco de soportes configurado
     * (config('filesystems.attachments_disk')) es "s3" — en local/
     * pruebas (disco "local") nunca hace falta, así que nunca se
     * resuelve un S3Client real ahí.
     *
     * Público (no privado) a propósito: Tests\Feature\Storage\
     * NeonAclStrippingTest necesita volver a invocarlo después de
     * sobreescribir config('filesystems.attachments_disk') a "s3" en su
     * propio setUp() — boot() ya corrió antes con el valor real del
     * entorno de pruebas ("local"), que no habría registrado nada.
     */
    public function stripAclFromNeonUploads(): void
    {
        if (config('filesystems.attachments_disk') !== 's3') {
            return;
        }

        Storage::disk('s3')->getClient()->getHandlerList()->prependBuild(
            function (callable $handler) {
                return function (CommandInterface $command, ?RequestInterface $request = null) use ($handler) {
                    unset($command['ACL']);

                    return $handler($command, $request);
                };
            },
            'strip-acl-for-neon'
        );
    }

    /**
     * $query->whereAccentInsensitive('name', $this->search) — para que
     * buscar "distribucion" también encuentre "Distribución". A
     * diferencia de App\Rules\CaseAccentInsensitiveUnique (que compara en
     * PHP, porque los catálogos que valida son minúsculos), esto se
     * resuelve en SQL: los buscadores con <x-search-input> viven
     * justamente en las tablas de mayor volumen (Usuarios, Liderazgos,
     * Distribución docente, Actividades) — traer todas las filas a PHP
     * para filtrar anularía la paginación que ya tienen. translate() es
     * una función nativa de PostgreSQL (sin la extensión unaccent, así
     * que no hace falta instalarla ni en Neon ni en local — mismo motivo
     * por el que CaseAccentInsensitiveUnique tampoco depende de ella):
     * reemplaza cada vocal acentuada/ñ/ü por su equivalente sin tilde,
     * carácter por carácter, sin afectar los comodines `%` de LIKE.
     */
    private function registerAccentInsensitiveSearchMacro(): void
    {
        Builder::macro('whereAccentInsensitive', function (string $column, string $value) {
            /** @var Builder $this */
            return $this->whereRaw(
                "translate(lower({$column}), 'áéíóúñü', 'aeiounu') like translate(lower(?), 'áéíóúñü', 'aeiounu')",
                ["%{$value}%"]
            );
        });

        // Mismo translate() de arriba, para ORDER BY en vez de WHERE — punto
        // 12 de la revisión de la directora (autoselección en informes "el
        // primero en orden alfabético"). Se reutilizará en el punto 6
        // (orden A-Z de los selects) cuando se apruebe ese lote.
        Builder::macro('orderByAccentInsensitive', function (string $column, string $direction = 'asc') {
            /** @var Builder $this */
            $direction = $direction === 'desc' ? 'desc' : 'asc';

            return $this->orderByRaw("translate(lower({$column}), 'áéíóúñü', 'aeiounu') {$direction}");
        });
    }

    /**
     * Equivalente de orderByAccentInsensitive() para Collection en vez de
     * Builder — para los pocos listados que ordenan en PHP (porque la
     * clave de orden es una relación ya cargada, no una columna de la
     * tabla principal) y no pueden resolverlo con un orderByRaw en SQL.
     * Punto 6 de la revisión de la directora.
     */
    private function registerAccentInsensitiveCollectionSortMacro(): void
    {
        Collection::macro('sortByAccentInsensitive', function (callable|string $keyBy) {
            /** @var Collection $this */
            $resolver = is_string($keyBy) ? fn ($item) => data_get($item, $keyBy) : $keyBy;

            return $this->sortBy(fn ($item) => Str::of((string) $resolver($item))->lower()->ascii()->toString());
        });
    }
}
