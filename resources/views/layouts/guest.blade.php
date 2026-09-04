<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIGAE-UTS</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="flex min-h-screen items-center justify-center bg-brand-primary-dark px-4 font-sans">
    <div class="w-full max-w-sm">
        <div class="mb-8 text-center">
            <h1 class="font-display text-3xl font-semibold text-white">SIGAE-UTS</h1>
            <p class="mt-2 text-sm text-brand-primary-subtle">
                Sistema de Gestión de Actividades y Evidencias Docentes
            </p>
        </div>

        {{ $slot }}
    </div>

    @livewireScripts
</body>
</html>
