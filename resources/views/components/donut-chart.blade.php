{{--
    Gráfico de dona reutilizable, SVG puro (sin librería JS nueva) — mismo
    criterio que <x-progress-bar>. Pensado para distribuciones por estado
    (ej. evidencias del docente por EvidenceStatus), pero genérico: no
    conoce ningún enum concreto, solo recibe segmentos ya resueltos.

    Props:
    - segments: array de ['label' => string, 'value' => int, 'color' => css color real
      (hex o var(--token), nunca una clase Tailwind — un atributo SVG stroke/fill
      no puede resolver una clase de utilidad), 'url' => string|null (opcional
      — si viene, la fila de la leyenda de ese segmento es un enlace de
      drill-down; el arco del SVG en sí nunca es clickeable, solo la leyenda)].
    - size: diámetro del SVG en px (default 140).
    - thickness: grosor del anillo en px (default 18).
    - emptyLabel: texto cuando la suma de valores es 0.
--}}
@props([
    'segments' => [],
    'size' => 140,
    'thickness' => 18,
    'emptyLabel' => 'Sin datos todavía.',
])

@php
    $total = collect($segments)->sum('value');
    $radius = ($size - $thickness) / 2;
    $circumference = 2 * M_PI * $radius;
    $center = $size / 2;
    $offset = 0;
    $arcs = [];

    foreach ($segments as $segment) {
        if (($segment['value'] ?? 0) <= 0) {
            continue;
        }

        $length = $total > 0 ? ($segment['value'] / $total) * $circumference : 0;
        $arcs[] = [
            'color' => $segment['color'],
            'label' => $segment['label'],
            'value' => $segment['value'],
            'url' => $segment['url'] ?? null,
            'length' => $length,
            'offset' => $offset,
        ];
        $offset += $length;
    }
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-5']) }}>
    @if ($total > 0)
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}" class="shrink-0" role="img" aria-label="Distribución: {{ collect($arcs)->map(fn ($a) => "{$a['label']} {$a['value']}")->join(', ') }}">
            <g transform="rotate(-90 {{ $center }} {{ $center }})">
                <circle cx="{{ $center }}" cy="{{ $center }}" r="{{ $radius }}" fill="none" stroke="var(--color-surface-muted)" stroke-width="{{ $thickness }}" />
                @foreach ($arcs as $arc)
                    <circle
                        cx="{{ $center }}" cy="{{ $center }}" r="{{ $radius }}"
                        fill="none"
                        stroke="{{ $arc['color'] }}"
                        stroke-width="{{ $thickness }}"
                        stroke-dasharray="{{ $arc['length'] }} {{ max($circumference - $arc['length'], 0) }}"
                        stroke-dashoffset="{{ -$arc['offset'] }}"
                    />
                @endforeach
            </g>
            <text x="{{ $center }}" y="{{ $center }}" text-anchor="middle" dominant-baseline="central" fill="var(--color-text-primary)" style="font-size: {{ max($size * 0.16, 14) }}px; font-weight: 600;">
                {{ $total }}
            </text>
        </svg>
        <ul class="space-y-1.5 text-sm">
            @foreach ($arcs as $arc)
                <li>
                    @if ($arc['url'])
                        <a href="{{ $arc['url'] }}" class="flex items-center gap-2 hover:underline">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $arc['color'] }}"></span>
                            <span class="text-text-secondary">{{ $arc['label'] }}</span>
                            <span class="font-medium text-text-primary">{{ $arc['value'] }}</span>
                        </a>
                    @else
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $arc['color'] }}"></span>
                            <span class="text-text-secondary">{{ $arc['label'] }}</span>
                            <span class="font-medium text-text-primary">{{ $arc['value'] }}</span>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @else
        <div class="flex items-center justify-center rounded-full bg-surface-muted text-center text-sm text-text-secondary" style="width: {{ $size }}px; height: {{ $size }}px;">
            {{ $emptyLabel }}
        </div>
    @endif
</div>
