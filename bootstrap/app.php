<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Render (y plataformas equivalentes: Heroku, Fly.io) termina TLS en
        // su propio proxy y reenvía la petición al contenedor por HTTP
        // plano, informando el esquema real vía X-Forwarded-Proto. Sin
        // confiar en ese proxy, Laravel ve la conexión interna como HTTP y
        // genera con ese esquema toda URL absoluta (url(), asset(), @vite)
        // — de ahí que el navegador bloqueara los assets del login como
        // contenido mixto aunque APP_URL ya estuviera en https. 'at' => '*'
        // es lo estándar para este tipo de despliegue de un solo proxy
        // interno controlado por la plataforma (nunca el propio cliente
        // final, que solo le habla al proxy) — no cambia nada en local, que
        // no manda ningún header X-Forwarded-*. Ver "Despliegue en entorno
        // de pruebas" en docs/manual-tecnico.md.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'role' => EnsureHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
