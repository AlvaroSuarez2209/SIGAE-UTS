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
    @php
        $isCoordination = auth()->check() && auth()->user()->hasAnyRole(['administrator', 'coordination']);
        $isAdmin = auth()->check() && auth()->user()->hasRole(\App\Enums\RoleName::Administrator);
        $isReviewer = auth()->check() && auth()->user()->hasAnyRole(['administrator', 'coordination', 'leader']);
        $canSeeReports = auth()->check() && auth()->user()->hasAnyRole(['administrator', 'coordination', 'auditor']);
    @endphp

    <nav class="bg-white shadow" x-data="{ mobileOpen: false, catalogsOpen: false, reportsOpen: false }">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
            <div class="flex items-center gap-6">
                <a href="{{ route('dashboard') }}" class="font-semibold text-gray-800">SIGAE-UTS</a>

                @auth
                    <div class="hidden items-center gap-5 md:flex">
                        <a href="{{ route('my-deliverables.index') }}" class="text-sm text-gray-600 hover:text-indigo-600">Mis entregables</a>

                        @if ($isReviewer)
                            <a href="{{ route('reviews.index') }}" class="text-sm text-gray-600 hover:text-indigo-600">Revisión</a>
                        @endif

                        @if ($isAdmin)
                            <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-600 hover:text-indigo-600">Usuarios</a>
                        @endif

                        @if ($isCoordination)
                            <a href="{{ route('periods.index') }}" class="text-sm text-gray-600 hover:text-indigo-600">Periodos</a>
                            <a href="{{ route('distribution.index') }}" class="text-sm text-gray-600 hover:text-indigo-600">Distribución</a>
                            <a href="{{ route('leaderships.index') }}" class="text-sm text-gray-600 hover:text-indigo-600">Líderes</a>
                            <a href="{{ route('deliverables.index') }}" class="text-sm text-gray-600 hover:text-indigo-600">Entregables</a>

                            <div class="relative" @click.outside="catalogsOpen = false">
                                <button type="button" @click="catalogsOpen = !catalogsOpen" class="flex items-center gap-1 text-sm text-gray-600 hover:text-indigo-600">
                                    Catálogos
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </button>
                                <div x-show="catalogsOpen" x-cloak class="absolute left-0 z-20 mt-2 w-56 rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5">
                                    <a href="{{ route('deliverable-templates.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Plantillas de entregables</a>
                                    <a href="{{ route('catalogs.components') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Componentes</a>
                                    <a href="{{ route('catalogs.subcomponents') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Subcomponentes</a>
                                    <a href="{{ route('catalogs.activities') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Actividades</a>
                                    <a href="{{ route('catalogs.program-units') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Programas</a>
                                    <a href="{{ route('catalogs.cross-cutting-commitments') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Compromisos transversales</a>
                                </div>
                            </div>
                        @endif

                        @if ($canSeeReports)
                            <div class="relative" @click.outside="reportsOpen = false">
                                <button type="button" @click="reportsOpen = !reportsOpen" class="flex items-center gap-1 text-sm text-gray-600 hover:text-indigo-600">
                                    Informes
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </button>
                                <div x-show="reportsOpen" x-cloak class="absolute left-0 z-20 mt-2 w-64 rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5">
                                    <a href="{{ route('reports.teacher') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Individual por docente</a>
                                    <a href="{{ route('reports.activity') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Por actividad</a>
                                    <a href="{{ route('reports.cross-cutting') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Compromisos transversales</a>
                                    <a href="{{ route('reports.consolidated') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Consolidado por periodo</a>
                                </div>
                            </div>
                        @endif
                    </div>
                @endauth
            </div>

            @auth
                <div class="flex items-center gap-4">
                    <div class="hidden items-center gap-4 text-sm text-gray-600 md:flex">
                        <span>{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-red-600 hover:underline">Cerrar sesión</button>
                        </form>
                    </div>

                    <button type="button" @click="mobileOpen = !mobileOpen" class="text-gray-600 md:hidden" aria-label="Abrir menú">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>
                </div>
            @endauth
        </div>

        @auth
            <div x-show="mobileOpen" x-cloak class="space-y-1 border-t border-gray-200 px-4 py-3 md:hidden">
                <a href="{{ route('my-deliverables.index') }}" class="block py-1 text-sm text-gray-600">Mis entregables</a>

                @if ($isReviewer)
                    <a href="{{ route('reviews.index') }}" class="block py-1 text-sm text-gray-600">Revisión</a>
                @endif

                @if ($isAdmin)
                    <a href="{{ route('admin.users.index') }}" class="block py-1 text-sm text-gray-600">Usuarios</a>
                @endif

                @if ($isCoordination)
                    <a href="{{ route('periods.index') }}" class="block py-1 text-sm text-gray-600">Periodos</a>
                    <a href="{{ route('distribution.index') }}" class="block py-1 text-sm text-gray-600">Distribución</a>
                    <a href="{{ route('leaderships.index') }}" class="block py-1 text-sm text-gray-600">Líderes</a>
                    <a href="{{ route('deliverables.index') }}" class="block py-1 text-sm text-gray-600">Entregables</a>
                    <a href="{{ route('deliverable-templates.index') }}" class="block py-1 text-sm text-gray-600">Plantillas de entregables</a>
                    <a href="{{ route('catalogs.components') }}" class="block py-1 text-sm text-gray-600">Componentes</a>
                    <a href="{{ route('catalogs.subcomponents') }}" class="block py-1 text-sm text-gray-600">Subcomponentes</a>
                    <a href="{{ route('catalogs.activities') }}" class="block py-1 text-sm text-gray-600">Actividades</a>
                    <a href="{{ route('catalogs.program-units') }}" class="block py-1 text-sm text-gray-600">Programas</a>
                    <a href="{{ route('catalogs.cross-cutting-commitments') }}" class="block py-1 text-sm text-gray-600">Compromisos transversales</a>
                @endif

                @if ($canSeeReports)
                    <a href="{{ route('reports.teacher') }}" class="block py-1 text-sm text-gray-600">Informe individual por docente</a>
                    <a href="{{ route('reports.activity') }}" class="block py-1 text-sm text-gray-600">Informe por actividad</a>
                    <a href="{{ route('reports.cross-cutting') }}" class="block py-1 text-sm text-gray-600">Informe de compromisos transversales</a>
                    <a href="{{ route('reports.consolidated') }}" class="block py-1 text-sm text-gray-600">Consolidado por periodo</a>
                @endif

                <div class="mt-2 border-t border-gray-200 pt-2 text-sm text-gray-600">
                    <span class="block py-1">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="py-1 text-red-600">Cerrar sesión</button>
                    </form>
                </div>
            </div>
        @endauth
    </nav>

    <main class="mx-auto max-w-6xl px-4 py-8">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
