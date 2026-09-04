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
<body class="min-h-screen bg-surface-muted font-sans text-text-primary antialiased">
    @php
        $user = auth()->user();
        $isAdmin = $user?->hasRole(\App\Enums\RoleName::Administrator) ?? false;
        $isCoordination = $user?->hasAnyRole(['administrator', 'coordination']) ?? false;
        $isReviewer = $user?->hasAnyRole(['administrator', 'coordination', 'leader']) ?? false;
        $canSeeReports = $user?->hasAnyRole(['administrator', 'coordination', 'auditor']) ?? false;

        $navGroups = collect([
            [
                'title' => 'Seguimiento',
                'links' => collect([
                    ['route' => 'my-deliverables.index', 'label' => 'Mis entregables', 'icon' => 'document'],
                    $isReviewer ? ['route' => 'reviews.index', 'label' => 'Revisión', 'icon' => 'inbox'] : null,
                ])->filter(),
            ],
            $isCoordination ? [
                'title' => 'Gestión académica',
                'links' => collect([
                    ['route' => 'periods.index', 'label' => 'Periodos', 'icon' => 'clock'],
                    ['route' => 'distribution.index', 'label' => 'Distribución', 'icon' => 'document'],
                    ['route' => 'leaderships.index', 'label' => 'Líderes', 'icon' => 'shield-check'],
                    ['route' => 'deliverables.index', 'label' => 'Entregables', 'icon' => 'paperclip'],
                    ['route' => 'deliverable-templates.index', 'label' => 'Plantillas de entregables', 'icon' => 'document'],
                ]),
            ] : null,
            $isCoordination ? [
                'title' => 'Catálogos',
                'links' => collect([
                    ['route' => 'catalogs.components', 'label' => 'Componentes', 'icon' => 'document'],
                    ['route' => 'catalogs.subcomponents', 'label' => 'Subcomponentes', 'icon' => 'document'],
                    ['route' => 'catalogs.activities', 'label' => 'Actividades', 'icon' => 'document'],
                    ['route' => 'catalogs.program-units', 'label' => 'Programas', 'icon' => 'document'],
                    ['route' => 'catalogs.cross-cutting-commitments', 'label' => 'Compromisos transversales', 'icon' => 'document'],
                ]),
            ] : null,
            $canSeeReports ? [
                'title' => 'Informes',
                'links' => collect([
                    ['route' => 'reports.teacher', 'label' => 'Individual por docente', 'icon' => 'document'],
                    ['route' => 'reports.activity', 'label' => 'Por actividad', 'icon' => 'document'],
                    ['route' => 'reports.cross-cutting', 'label' => 'Compromisos transversales', 'icon' => 'document'],
                    ['route' => 'reports.consolidated', 'label' => 'Consolidado por periodo', 'icon' => 'document'],
                ]),
            ] : null,
            $isAdmin ? [
                'title' => 'Administración',
                'links' => collect([
                    ['route' => 'admin.users.index', 'label' => 'Usuarios', 'icon' => 'document'],
                    ['route' => 'admin.audit-logs.index', 'label' => 'Auditoría', 'icon' => 'document'],
                ]),
            ] : null,
        ])->filter();
    @endphp

    @auth
    <div x-data="{ mobileOpen: false }" class="flex min-h-screen">
        <!-- Sidebar (escritorio) -->
        <aside class="hidden w-64 shrink-0 flex-col border-r border-border-subtle bg-surface md:flex">
            <div class="flex h-16 items-center gap-2 border-b border-border-subtle px-5">
                <a href="{{ route('dashboard') }}" class="text-base font-semibold text-brand-primary">SIGAE-UTS</a>
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
                @foreach ($navGroups as $group)
                    <div>
                        <p class="px-2 text-xs font-semibold uppercase tracking-wide text-text-secondary">{{ $group['title'] }}</p>
                        <div class="mt-1 space-y-0.5">
                            @foreach ($group['links'] as $link)
                                @php $active = request()->routeIs($link['route'].'*'); @endphp
                                <a
                                    href="{{ route($link['route']) }}"
                                    @class([
                                        'flex items-center gap-2 rounded-md px-2 py-1.5 text-sm font-medium',
                                        'bg-brand-primary-subtle text-brand-primary-dark' => $active,
                                        'text-text-primary hover:bg-surface-muted' => ! $active,
                                    ])
                                >
                                    {{ $link['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>

            <div class="border-t border-border-subtle p-4">
                <p class="truncate text-sm font-medium text-text-primary">{{ $user->name }}</p>
                <p class="mb-2 truncate text-xs text-text-secondary">{{ $user->roles->pluck('label')->join(', ') }}</p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 text-sm font-medium text-status-error hover:underline">
                        <x-icon name="logout" class="h-4 w-4" />
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </aside>

        <!-- Drawer móvil -->
        <div x-show="mobileOpen" x-cloak class="fixed inset-0 z-40 md:hidden">
            <div class="fixed inset-0 bg-text-primary/40" @click="mobileOpen = false"></div>
            <aside class="fixed inset-y-0 left-0 flex w-72 flex-col bg-surface shadow-xl">
                <div class="flex h-16 items-center justify-between border-b border-border-subtle px-5">
                    <span class="text-base font-semibold text-brand-primary">SIGAE-UTS</span>
                    <button type="button" @click="mobileOpen = false" aria-label="Cerrar menú">
                        <x-icon name="x" class="h-5 w-5 text-text-secondary" />
                    </button>
                </div>
                <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
                    @foreach ($navGroups as $group)
                        <div>
                            <p class="px-2 text-xs font-semibold uppercase tracking-wide text-text-secondary">{{ $group['title'] }}</p>
                            <div class="mt-1 space-y-0.5">
                                @foreach ($group['links'] as $link)
                                    <a href="{{ route($link['route']) }}" class="block rounded-md px-2 py-1.5 text-sm font-medium text-text-primary hover:bg-surface-muted">
                                        {{ $link['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </nav>
                <div class="border-t border-border-subtle p-4">
                    <p class="truncate text-sm font-medium text-text-primary">{{ $user->name }}</p>
                    <form method="POST" action="{{ route('logout') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="flex items-center gap-1.5 text-sm font-medium text-status-error hover:underline">
                            <x-icon name="logout" class="h-4 w-4" />
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </aside>
        </div>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-16 items-center gap-4 border-b border-border-subtle bg-surface px-4 md:hidden">
                <button type="button" @click="mobileOpen = true" aria-label="Abrir menú">
                    <x-icon name="menu" class="h-6 w-6 text-text-secondary" />
                </button>
                <span class="text-base font-semibold text-brand-primary">SIGAE-UTS</span>
            </header>

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>
        </div>
    </div>
    @else
        <main class="mx-auto max-w-6xl px-4 py-8">
            {{ $slot }}
        </main>
    @endauth

    @livewireScripts
</body>
</html>
