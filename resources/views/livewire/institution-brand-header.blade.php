{{-- class="contents" (sin caja propia): Livewire exige un único elemento
     raíz por componente, pero el padre (layouts/app.blade.php) espera que
     el enlace/span y el <p> del nombre sean hijos directos de su propio
     contenedor flex — "contents" hace que este <div> no participe en el
     layout, preservando exactamente el mismo resultado visual que antes
     de extraer este bloque a un componente aparte. --}}
<div class="contents">
    @if ($clickable)
        <a href="{{ route('dashboard') }}" class="flex items-center {{ $gap }} text-lg font-semibold text-brand-primary">
            <x-logo-mark :class="$logoClass" />
            SIGAE-UTS
        </a>
    @else
        <span class="flex items-center {{ $gap }} text-lg font-semibold text-brand-primary">
            <x-logo-mark :class="$logoClass" />
            SIGAE-UTS
        </span>
    @endif

    @if ($withCaption)
        <p
            class="line-clamp-2 pl-12 text-xs leading-tight text-text-secondary"
            title="{{ $institution->name }}"
        >{{ $institution->name }}</p>
    @endif
</div>
