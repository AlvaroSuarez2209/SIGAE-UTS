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
<body class="min-h-screen bg-surface-muted font-sans text-text-primary antialiased">
    @php
        // Isotipo pequeño: usa una versión propia (crisp) para tamaño de ícono si existe,
        // el logo-mark original si no, o nada si aún no se ha colocado el logo real.
        $logoMarkPath = collect([
            'images/logo/logo-mark-icon.svg',
            'images/logo/logo-mark-icon.png',
            'images/logo/logo-mark.svg',
            'images/logo/logo-mark.png',
        ])->first(fn ($path) => file_exists(public_path($path)));

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
                    ['route' => 'distribution.index', 'label' => 'Distribución', 'icon' => 'link'],
                    ['route' => 'leaderships.index', 'label' => 'Líderes', 'icon' => 'shield-check'],
                    ['route' => 'deliverables.index', 'label' => 'Entregables', 'icon' => 'paperclip'],
                    ['route' => 'deliverable-templates.index', 'label' => 'Plantillas de entregables', 'icon' => 'document'],
                ]),
            ] : null,
            $isCoordination ? [
                'title' => 'Catálogos',
                'links' => collect([
                    ['route' => 'catalogs.components', 'label' => 'Componentes', 'icon' => 'list'],
                    ['route' => 'catalogs.subcomponents', 'label' => 'Subcomponentes', 'icon' => 'list'],
                    ['route' => 'catalogs.activities', 'label' => 'Actividades', 'icon' => 'list'],
                    ['route' => 'catalogs.program-units', 'label' => 'Programas', 'icon' => 'list'],
                    ['route' => 'catalogs.cross-cutting-commitments', 'label' => 'Compromisos transversales', 'icon' => 'list'],
                ]),
            ] : null,
            $canSeeReports ? [
                'title' => 'Informes',
                'links' => collect([
                    ['route' => 'reports.teacher', 'label' => 'Individual por docente', 'icon' => 'chart-bar'],
                    ['route' => 'reports.activity', 'label' => 'Por actividad', 'icon' => 'chart-bar'],
                    ['route' => 'reports.cross-cutting', 'label' => 'Compromisos transversales', 'icon' => 'chart-bar'],
                    ['route' => 'reports.consolidated', 'label' => 'Consolidado por periodo', 'icon' => 'chart-bar'],
                ]),
            ] : null,
            $isAdmin ? [
                'title' => 'Administración',
                'links' => collect([
                    ['route' => 'admin.users.index', 'label' => 'Usuarios', 'icon' => 'users'],
                    ['route' => 'admin.audit-logs.index', 'label' => 'Auditoría', 'icon' => 'shield-check'],
                ]),
            ] : null,
        ])->filter();
    @endphp

    @auth
    <div x-data="{ mobileOpen: false }" class="flex min-h-screen">
        <!-- Sidebar (escritorio) -->
        <aside class="hidden w-72 shrink-0 flex-col border-r border-border-subtle bg-surface md:flex">
            <div class="flex h-20 items-center gap-3 border-b border-border-subtle px-6">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 text-lg font-semibold text-brand-primary">
                    @if ($logoMarkPath)
                        <img src="{{ asset($logoMarkPath) }}" alt="" class="h-9 w-9">
                    @endif
                    SIGAE-UTS
                </a>
            </div>

            <nav class="flex-1 space-y-8 overflow-y-auto px-4 py-6">
                @foreach ($navGroups as $group)
                    <div>
                        <p class="px-2 text-xs font-semibold uppercase tracking-wide text-text-secondary">{{ $group['title'] }}</p>
                        <div class="mt-2 space-y-1">
                            @foreach ($group['links'] as $link)
                                @php $active = request()->routeIs($link['route'].'*'); @endphp
                                <a
                                    href="{{ route($link['route']) }}"
                                    @class([
                                        'flex items-center gap-3 rounded-md border-l-2 px-3 py-2.5 text-base font-medium',
                                        'border-l-brand-primary bg-brand-primary-subtle text-brand-primary' => $active,
                                        'border-l-transparent text-text-primary hover:bg-surface-muted' => ! $active,
                                    ])
                                >
                                    <x-icon :name="$link['icon']" class="h-5 w-5 shrink-0" />
                                    {{ $link['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>

            <div class="border-t border-border-subtle p-5">
                <p class="truncate text-base font-medium text-text-primary">{{ $user->name }}</p>
                <p class="mb-3 truncate text-sm text-text-secondary">{{ $user->roles->pluck('label')->join(', ') }}</p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-2 text-base font-medium text-status-error hover:underline">
                        <x-icon name="logout" class="h-5 w-5" />
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </aside>

        <!-- Drawer móvil -->
        <div x-show="mobileOpen" x-cloak class="fixed inset-0 z-40 md:hidden">
            <div class="fixed inset-0 bg-text-primary/40" @click="mobileOpen = false"></div>
            <aside class="fixed inset-y-0 left-0 flex w-72 flex-col bg-surface shadow-xl">
                <div class="flex h-20 items-center justify-between border-b border-border-subtle px-6">
                    <span class="flex items-center gap-3 text-lg font-semibold text-brand-primary">
                        @if ($logoMarkPath)
                            <img src="{{ asset($logoMarkPath) }}" alt="" class="h-9 w-9">
                        @endif
                        SIGAE-UTS
                    </span>
                    <button type="button" @click="mobileOpen = false" aria-label="Cerrar menú">
                        <x-icon name="x" class="h-6 w-6 text-text-secondary" />
                    </button>
                </div>
                <nav class="flex-1 space-y-8 overflow-y-auto px-4 py-6">
                    @foreach ($navGroups as $group)
                        <div>
                            <p class="px-2 text-xs font-semibold uppercase tracking-wide text-text-secondary">{{ $group['title'] }}</p>
                            <div class="mt-2 space-y-1">
                                @foreach ($group['links'] as $link)
                                    @php $active = request()->routeIs($link['route'].'*'); @endphp
                                    <a
                                        href="{{ route($link['route']) }}"
                                        @class([
                                            'flex items-center gap-3 rounded-md border-l-2 px-3 py-2.5 text-base font-medium',
                                            'border-l-brand-primary bg-brand-primary-subtle text-brand-primary' => $active,
                                            'border-l-transparent text-text-primary hover:bg-surface-muted' => ! $active,
                                        ])
                                    >
                                        <x-icon :name="$link['icon']" class="h-5 w-5 shrink-0" />
                                        {{ $link['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </nav>
                <div class="border-t border-border-subtle p-5">
                    <p class="truncate text-base font-medium text-text-primary">{{ $user->name }}</p>
                    <form method="POST" action="{{ route('logout') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="flex items-center gap-2 text-base font-medium text-status-error hover:underline">
                            <x-icon name="logout" class="h-5 w-5" />
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </aside>
        </div>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-18 items-center gap-4 border-b border-border-subtle bg-surface px-5 md:hidden">
                <button type="button" @click="mobileOpen = true" aria-label="Abrir menú">
                    <x-icon name="menu" class="h-7 w-7 text-text-secondary" />
                </button>
                <span class="flex items-center gap-2 text-lg font-semibold text-brand-primary">
                    @if ($logoMarkPath)
                        <img src="{{ asset($logoMarkPath) }}" alt="" class="h-8 w-8">
                    @endif
                    SIGAE-UTS
                </span>
            </header>

            <main class="min-w-0 flex-1 px-6 py-8 sm:px-8 lg:px-10">
                {{ $slot }}
            </main>
        </div>
    </div>
    @else
        <main class="mx-auto max-w-6xl px-4 py-8">
            {{ $slot }}
        </main>
    @endauth

    <x-confirm-modal />

    @livewireScripts
</body>
</html>
