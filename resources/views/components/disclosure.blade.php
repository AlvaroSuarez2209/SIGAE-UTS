{{--
    Contenido plegable reutilizable — <details>/<summary> nativo, sin
    Alpine salvo cuando defaultOpenOnDesktop lo pide: accesible por
    teclado y lector de pantalla por defecto. Usado por
    evidence-workspace.blade.php ("¿No puedes realizar esta entrega?",
    cerrado siempre por defecto) y review-show.blade.php ("Instrucciones",
    abierto por defecto solo en escritorio).

    Props:
    - summary: string — texto siempre visible, con el que se abre/cierra.
    - defaultOpenOnDesktop (bool, default false): arranca abierto desde
      el breakpoint lg: (1024px) hacia arriba, cerrado por debajo — el
      atributo HTML `open` es estático (no responsive por sí solo), así
      que esto necesita el único x-data de este componente: la decisión
      "¿pantalla ancha?" se toma una sola vez al cargar (x-init), y de
      ahí en adelante el toggle nativo del usuario (evento "toggle") es
      la única cosa que cambia el estado — nunca se vuelve a evaluar el
      ancho de pantalla, así que sigue siendo "plegable" de verdad en
      escritorio, no un contenido forzado siempre visible.
--}}
@props(['summary', 'defaultOpenOnDesktop' => false])

<details
    @if ($defaultOpenOnDesktop)
        x-data="{ open: false }"
        x-init="open = window.matchMedia('(min-width: 1024px)').matches"
        :open="open"
        @toggle="open = $el.open"
    @endif
    class="group rounded-md border border-border-subtle"
>
    <summary class="flex cursor-pointer select-none items-center gap-2 p-3 text-base font-semibold text-text-primary marker:hidden [&::-webkit-details-marker]:hidden">
        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 transition-transform group-open:rotate-90" />
        {{ $summary }}
    </summary>
    <div class="border-t border-border-subtle p-3 text-base text-text-secondary">
        {{ $slot }}
    </div>
</details>
