{{--
    Barra de progreso reutilizable — mismo track/relleno usados antes solo
    en el Dashboard ("Consolidado por docente"). El alto/ancho NO trae
    default: siempre se pasan por class (p. ej. class="h-1.5 w-20"), para
    no depender de que Tailwind resuelva a favor de la clase "correcta"
    cuando dos utilidades de tamaño conviven en el mismo atributo tras un
    $attributes->merge().
--}}
@props(['percentage'])

@php
    $pct = max(0, min(100, (float) $percentage));
@endphp

{{-- Cuadro de seguimiento, punto 23: sin role/aria-value*, un lector de
     pantalla no tenía forma de anunciar el progreso — las 3 pantallas que
     usan este componente ya muestran el % como texto al lado, pero el
     propio control no lo comunicaba. --}}
<div
    {{ $attributes->merge(['class' => 'shrink-0 overflow-hidden rounded-full bg-surface-muted']) }}
    role="progressbar"
    aria-valuenow="{{ $pct }}"
    aria-valuemin="0"
    aria-valuemax="100"
>
    <div class="h-full rounded-full bg-brand-primary" style="width: {{ $pct }}%"></div>
</div>
