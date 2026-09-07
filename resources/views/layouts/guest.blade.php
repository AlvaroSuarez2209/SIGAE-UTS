<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIGAE-UTS</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon-180.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="flex min-h-screen flex-col items-center justify-center bg-surface-muted px-4 py-10 font-sans">
    <div class="flex w-full max-w-sm flex-col items-center">
        {{-- Isotipo: usa el logo real si ya se colocó en public/images/logo/logo-full.png o .svg;
             mientras tanto, placeholder rotado con las iniciales (ver public/images/logo/README.md).
             Protagonista y grande en todos los tamaños de pantalla. --}}
        @if (file_exists(public_path('images/logo/logo-full.svg')))
            <img src="{{ asset('images/logo/logo-full.svg') }}" alt="SIGAE-UTS" class="h-28 w-auto max-w-full sm:h-32 md:h-40">
        @elseif (file_exists(public_path('images/logo/logo-full.png')))
            <img src="{{ asset('images/logo/logo-full.png') }}" alt="SIGAE-UTS" class="h-28 w-auto max-w-full sm:h-32 md:h-40">
        @else
            <div class="flex h-16 w-16 -rotate-3 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-primary to-brand-secondary text-xl font-semibold text-white shadow-sm">
                S
            </div>
        @endif

        <div class="mt-5 max-w-xs text-center sm:max-w-sm">
            <p class="text-2xl font-semibold text-text-primary sm:text-3xl">SIGAE-UTS</p>
            <p class="mt-1 text-sm font-medium tracking-wide text-brand-secondary sm:text-base">
                Sistema de Información para la Gestión de Actividades y Evidencias Docentes
            </p>
        </div>

        <div class="mt-6 w-full">
            {{ $slot }}
        </div>

        <p class="mt-6 text-xs text-text-secondary">Unidades Tecnológicas de Santander</p>
    </div>

    @livewireScripts
</body>
</html>
