{{--
    Vista de paginación institucional de SIGAE-UTS — sobrescribe la vista
    por defecto de Livewire (livewire::tailwind) para TODA la app: basta
    con que este archivo exista en resources/views/vendor/livewire/ para
    que cualquier `{{ $paginador->links() }}` de un componente con
    `WithPagination` la use automáticamente, sin tocar cada vista una por
    una (ver docs/manual-diseno.md, sección "Paginación").

    OJO: no va en resources/views/vendor/pagination/ (la vista por
    defecto de Laravel, `pagination::tailwind`, con enlaces <a href> de
    navegación real) — Livewire pisa esa configuración en cada componente
    que usa WithPagination (SupportPagination::overrideDefaultPaginationViews(),
    en vendor/livewire/livewire/src/Features/SupportPagination/) y apunta
    en su lugar a `livewire::tailwind`, cuyos controles de página son
    <button wire:click="gotoPage(...)"> — sin eso, cada clic en un número
    de página recargaría la pantalla completa en vez de actualizar solo
    la tabla por AJAX. Como los 5 listados paginados de esta app son
    componentes Livewire, este es el único archivo que realmente hace
    efecto (comprobado: colocarlo en vendor/pagination/ no cambia nada).
    Cubre `paginate()` (el único método usado hoy); si algún componente
    futuro usa `simplePaginate()`, necesitaría además
    `simple-tailwind.blade.php` en esta misma carpeta, con el mismo ajuste.

    Traducida a español (antes: "Showing X to Y of Z results" /
    "Previous"/"Next" en inglés) y con los tokens de color/tipografía del
    sistema de diseño en vez del estilo gris genérico de Tailwind.
--}}
@php
    if (! isset($scrollTo)) {
        $scrollTo = 'body';
    }

    $scrollIntoViewJsSnippet = ($scrollTo !== false)
        ? "(\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()"
        : '';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Paginación" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

            {{-- Móvil: solo Anterior/Siguiente --}}
            <div class="flex items-center justify-between gap-2 sm:hidden">
                @if ($paginator->onFirstPage())
                    <span class="btn-secondary cursor-not-allowed opacity-50">Anterior</span>
                @else
                    <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" class="btn-secondary">Anterior</button>
                @endif

                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled" class="btn-secondary">Siguiente</button>
                @else
                    <span class="btn-secondary cursor-not-allowed opacity-50">Siguiente</span>
                @endif
            </div>

            {{-- Escritorio: resumen + números de página --}}
            <p class="hidden text-sm text-text-secondary sm:block">
                Mostrando
                @if ($paginator->firstItem())
                    <span class="font-medium text-text-primary">{{ $paginator->firstItem() }}</span>
                    a
                    <span class="font-medium text-text-primary">{{ $paginator->lastItem() }}</span>
                @else
                    {{ $paginator->count() }}
                @endif
                de
                <span class="font-medium text-text-primary">{{ $paginator->total() }}</span>
                resultados
            </p>

            <div class="hidden items-center gap-1 sm:flex">
                {{-- Anterior --}}
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border-subtle text-text-secondary opacity-50">
                        <x-icon name="chevron-left" class="h-4 w-4" />
                    </span>
                @else
                    <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" aria-label="{{ __('pagination.previous') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border-subtle text-text-secondary hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary">
                        <x-icon name="chevron-left" class="h-4 w-4" />
                    </button>
                @endif

                {{-- Números de página --}}
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-disabled="true" class="inline-flex h-9 w-9 items-center justify-center text-sm text-text-secondary">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page" class="inline-flex h-9 w-9 items-center justify-center rounded-md bg-brand-primary text-sm font-semibold text-white">{{ $page }}</span>
                                @else
                                    <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" aria-label="Ir a la página {{ $page }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border-subtle text-sm text-text-primary hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary">{{ $page }}</button>
                                @endif
                            </span>
                        @endforeach
                    @endif
                @endforeach

                {{-- Siguiente --}}
                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" aria-label="{{ __('pagination.next') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border-subtle text-text-secondary hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary">
                        <x-icon name="chevron-right" class="h-4 w-4" />
                    </button>
                @else
                    <span aria-disabled="true" aria-label="{{ __('pagination.next') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-border-subtle text-text-secondary opacity-50">
                        <x-icon name="chevron-right" class="h-4 w-4" />
                    </span>
                @endif
            </div>
        </nav>
    @endif
</div>
