{{--
    Contenido plegable reutilizable — <details>/<summary> nativo, sin
    Alpine ni JS: accesible por teclado y lector de pantalla por defecto.
    Usado por evidence-workspace.blade.php (detalle largo de "no se puede
    eximir") y review-show.blade.php ("Instrucciones" plegada).

    Props:
    - summary: string — texto siempre visible, con el que se abre/cierra.
--}}
@props(['summary'])

<details class="group rounded-md border border-border-subtle">
    <summary class="flex cursor-pointer select-none items-center gap-2 p-3 text-sm font-medium text-text-primary marker:hidden [&::-webkit-details-marker]:hidden">
        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 transition-transform group-open:rotate-90" />
        {{ $summary }}
    </summary>
    <div class="border-t border-border-subtle p-3 text-sm text-text-secondary">
        {{ $slot }}
    </div>
</details>
