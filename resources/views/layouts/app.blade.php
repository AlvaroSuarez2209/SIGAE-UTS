<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIGAE-UTS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-100">
    <nav class="bg-white shadow">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <div class="flex items-center gap-6">
                <a href="{{ route('dashboard') }}" class="font-semibold text-gray-800">SIGAE-UTS</a>

                @auth
                    @if (auth()->user()->hasRole(\App\Enums\RoleName::Administrator))
                        <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-600 hover:text-indigo-600">Usuarios</a>
                    @endif
                @endauth
            </div>

            @auth
                <div class="flex items-center gap-4 text-sm text-gray-600">
                    <span>{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-red-600 hover:underline">Cerrar sesión</button>
                    </form>
                </div>
            @endauth
        </div>
    </nav>

    <main class="mx-auto max-w-6xl px-4 py-8">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
