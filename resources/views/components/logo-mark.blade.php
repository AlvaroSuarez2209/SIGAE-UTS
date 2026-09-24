@props(['class' => 'h-9 w-9'])

@php
    // Misma resolución de archivo que antes vivía inline en layouts/app.blade.php:
    // preferir un .svg "crisp" para tamaño de ícono si existe, el logo-mark
    // original si no, o nada si aún no se ha colocado el logo real.
    $logoMarkPath = collect([
        'images/logo/logo-mark-icon.svg',
        'images/logo/logo-mark-icon.png',
        'images/logo/logo-mark.svg',
        'images/logo/logo-mark.png',
    ])->first(fn ($path) => file_exists(public_path($path)));
@endphp

@if ($logoMarkPath)
    {{-- El archivo entregado no trae el degradado de marca aplicado; se
         recorta su silueta (canal alfa) como máscara CSS y se pinta detrás
         el mismo degradado azul→verde del isotipo de login — reservado a
         estos momentos "de marca", ver docs/manual-diseno.md. Funciona
         igual si el archivo es PNG o SVG. --}}
    <span
        {{ $attributes->merge(['class' => $class.' inline-block shrink-0 bg-gradient-to-br from-brand-primary to-brand-secondary']) }}
        style="-webkit-mask-image: url('{{ asset($logoMarkPath) }}'); mask-image: url('{{ asset($logoMarkPath) }}'); -webkit-mask-size: contain; mask-size: contain; -webkit-mask-repeat: no-repeat; mask-repeat: no-repeat; -webkit-mask-position: center; mask-position: center;"
        aria-hidden="true"
    ></span>
@endif
