<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- $title llega vía Livewire (#[Title('Sección')] en el componente de
         página, ver Livewire\Features\SupportPageComponents), nunca se
         concatena "- SIGAE-UTS" a mano en cada componente. --}}
    <title>{{ ($title ?? null) ? "{$title} - SIGAE-UTS" : 'SIGAE-UTS' }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon-180.png') }}">
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
                    ['route' => 'distribution.index', 'label' => 'Distribución', 'icon' => 'link'],
                    ['route' => 'leaderships.index', 'label' => 'Líderes', 'icon' => 'shield-check'],
                    ['route' => 'deliverables.index', 'label' => 'Entregables', 'icon' => 'paperclip'],
                    ['route' => 'deliverable-templates.index', 'label' => 'Plantillas de entregables', 'icon' => 'document'],
                ]),
            ] : null,
            $isCoordination ? [
                'title' => 'Catálogos',
                'links' => collect([
                    ['route' => 'catalogs.hierarchy', 'label' => 'Vista jerárquica', 'icon' => 'chart-bar'],
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
                    ['route' => 'admin.settings.institution', 'label' => 'Identidad institucional', 'icon' => 'pencil'],
                ]),
            ] : null,
        ])->filter();
    @endphp

    @auth
    <div x-data="{ mobileOpen: false }" class="flex h-screen overflow-hidden">
        <!-- Sidebar (escritorio): h-screen, nunca la altura del contenido de
             la página — su propio nav hace scroll interno si hace falta, y
             la tarjeta de usuario queda anclada al fondo (mt-auto), no al
             fondo del documento. Ver docs/manual-diseno.md. -->
        <aside class="hidden h-screen w-72 shrink-0 flex-col border-r border-border-subtle bg-surface md:flex">
            {{-- Único de los 3 encabezados con logo (los otros 2 son barras
                 móviles compactas de una sola línea) con espacio natural
                 para el nombre institucional debajo — Prioridad 3.
                 min-h-20 (no h-20 fijo): un nombre largo envuelve a 2
                 líneas (ver InstitutionBrandHeader) en vez de recortarse a
                 1 con "..." — el contenedor crece lo que haga falta.
                 <livewire:institution-brand-header>, no Blade estático:
                 así un guardado en "Identidad institucional" se refleja al
                 instante vía el evento 'institution-settings-updated', sin
                 recargar la página. --}}
            <div class="flex min-h-20 shrink-0 flex-col justify-center gap-0.5 border-b border-border-subtle px-6 py-3">
                <livewire:institution-brand-header key="brand-sidebar-desktop" :with-caption="true" />
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
                                        'flex items-center gap-3 rounded-md border-l-4 px-3 py-2.5 text-base font-medium',
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

            <div class="mt-auto shrink-0 border-t border-border-subtle p-5">
                <livewire:user-name key="sidebar-desktop-user-name" />
                <span class="badge mb-3 whitespace-normal bg-brand-primary-subtle text-brand-primary">
                    {{ $user->roles->pluck('label')->join(', ') }}
                </span>
                <div class="space-y-1">
                    <a href="{{ route('profile') }}" class="-mx-2 flex items-center gap-2 rounded-md px-2 py-1.5 text-base font-medium text-text-secondary transition-colors hover:bg-surface-muted hover:text-brand-primary focus-visible:bg-surface-muted focus-visible:text-brand-primary">
                        <x-icon name="user" class="h-5 w-5" />
                        Mi perfil
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="-mx-2 flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-base font-medium text-text-secondary transition-colors hover:bg-surface-muted hover:text-brand-primary focus-visible:bg-surface-muted focus-visible:text-brand-primary">
                            <x-icon name="logout" class="h-5 w-5" />
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Drawer móvil -->
        <div x-show="mobileOpen" x-cloak class="fixed inset-0 z-40 md:hidden">
            <div class="fixed inset-0 bg-text-primary/40" @click="mobileOpen = false"></div>
            <aside class="fixed inset-y-0 left-0 flex w-72 flex-col bg-surface shadow-xl">
                <div class="flex h-20 items-center justify-between border-b border-border-subtle px-6">
                    <livewire:institution-brand-header key="brand-sidebar-mobile-drawer" :clickable="false" />
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
                                            'flex items-center gap-3 rounded-md border-l-4 px-3 py-2.5 text-base font-medium',
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
                <div class="mt-auto border-t border-border-subtle p-5">
                    <livewire:user-name key="sidebar-mobile-user-name" />
                    <span class="badge mb-3 whitespace-normal bg-brand-primary-subtle text-brand-primary">
                        {{ $user->roles->pluck('label')->join(', ') }}
                    </span>
                    <div class="space-y-1">
                        <a href="{{ route('profile') }}" class="-mx-2 flex items-center gap-2 rounded-md px-2 py-1.5 text-base font-medium text-text-secondary transition-colors hover:bg-surface-muted hover:text-brand-primary focus-visible:bg-surface-muted focus-visible:text-brand-primary">
                            <x-icon name="user" class="h-5 w-5" />
                            Mi perfil
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="-mx-2 flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-base font-medium text-text-secondary transition-colors hover:bg-surface-muted hover:text-brand-primary focus-visible:bg-surface-muted focus-visible:text-brand-primary">
                                <x-icon name="logout" class="h-5 w-5" />
                                Cerrar sesión
                            </button>
                        </form>
                    </div>
                </div>
            </aside>
        </div>

        {{-- Columna de contenido: altura de viewport propia, con su propio
             scroll vertical en <main> — completamente independiente del
             sidebar (que nunca se mueve ni cambia de tamaño con esto). El
             scroll horizontal de una tabla ancha se queda dentro de su
             propio .table-shell (overflow-x-auto), nunca aquí. --}}
        <div class="flex h-screen min-w-0 flex-1 flex-col overflow-hidden">
            <header class="flex h-18 shrink-0 items-center gap-4 border-b border-border-subtle bg-surface px-5 md:hidden">
                <button type="button" @click="mobileOpen = true" aria-label="Abrir menú">
                    <x-icon name="menu" class="h-7 w-7 text-text-secondary" />
                </button>
                <livewire:institution-brand-header key="brand-sidebar-mobile-topbar" :clickable="false" logo-class="h-8 w-8" gap="gap-2" />
            </header>

            <main class="min-w-0 flex-1 overflow-y-auto px-6 py-8 sm:px-8 lg:px-10">
                <x-flash-message />
                {{ $slot }}
            </main>
        </div>
    </div>
    @else
        <main class="mx-auto max-w-6xl px-4 py-8">
            <x-flash-message />
            {{ $slot }}
        </main>
    @endauth

    <x-confirm-modal />

    @livewireScripts
</body>
</html>
