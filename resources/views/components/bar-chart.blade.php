{{--
    Gráfico de barras horizontal reutilizable, CSS puro — cada fila
    reutiliza <x-progress-bar> (mismo track/relleno que el resto del
    sistema) en vez de reinventar la barra. Pensado para "% de
    cumplimiento por X" (actividad, programa), pero genérico: solo recibe
    filas ya resueltas con etiqueta + porcentaje.

    Props:
    - bars: array de ['label' => string, 'percentage' => float|null, 'detail' => string|null,
      'url' => string|null]. percentage null significa "sin entregables obligatorios para medir"
      (no 0%, que sería "tiene obligatorios pero ninguno aprobado" — una barra vacía con una nota,
      no una barra en 0). 'url', si viene, hace de toda la fila un enlace de drill-down.
    - emptyLabel: texto cuando $bars está vacío.
--}}
@props([
    'bars' => [],
    'emptyLabel' => 'Sin datos todavía.',
])

<div {{ $attributes->merge(['class' => 'space-y-3']) }}>
    @forelse ($bars as $bar)
        @php $tag = empty($bar['url']) ? 'div' : 'a'; @endphp
        <{{ $tag }} @if (! empty($bar['url'])) href="{{ $bar['url'] }}" @endif class="block {{ empty($bar['url']) ? '' : 'rounded-md -mx-2 px-2 py-1 hover:bg-surface-muted' }}">
            <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                <span class="truncate font-medium {{ empty($bar['url']) ? 'text-text-primary' : 'text-brand-primary' }}">{{ $bar['label'] }}</span>
                <span class="shrink-0 text-text-secondary">
                    @if ($bar['percentage'] !== null)
                        {{ $bar['percentage'] }}%
                        @if (! empty($bar['detail']))
                            <span class="text-text-secondary">({{ $bar['detail'] }})</span>
                        @endif
                    @else
                        Sin obligatorios
                    @endif
                </span>
            </div>
            <x-progress-bar :percentage="$bar['percentage'] ?? 0" class="h-2.5 w-full" />
        </{{ $tag }}>
    @empty
        <p class="text-sm text-text-secondary">{{ $emptyLabel }}</p>
    @endforelse
</div>
