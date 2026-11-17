{{--
    Texto largo de un entregable (descripción, instrucciones, criterio de
    cumplimiento) — compartido entre evidence-workspace.blade.php (docente)
    y review-show.blade.php (líder) para que ambos formateen igual.

    Solo se convierte en lista numerada <ol> cuando el texto tiene al menos
    2 marcadores "N. " consecutivos desde 1 (punto SEGUIDO DE ESPACIO, para
    no confundir "1.5" con un marcador), con nada más que espacios antes
    del primero — nunca por encontrar "1." en cualquier parte del texto, y
    sin exigir saltos de línea reales entre los pasos: ajustes al rediseño
    visual, punto 8, encontraron un criterio de cumplimiento real escrito
    como un párrafo corrido ("1. La actividad... 2. Se describe...", sin
    pulsar Enter entre cada paso) que la versión anterior (solo por líneas)
    nunca detectaba, porque un párrafo sin saltos de línea nunca llega a
    tener 2 "líneas" que comparar. Al buscar los marcadores directamente
    sobre todo el texto (en vez de partir primero por línea), el mismo
    criterio funciona para texto en una sola línea o en varias, y sigue
    rechazando "Vigente 2026. Debe incluir…" (2026 no es "1.") y "Ver
    punto 1.5 del formato" (el punto de "1.5" no va seguido de espacio).
    Si no aplica, se muestra el texto tal cual, respetando los saltos de
    línea.

    Props:
    - text: string
--}}
@props(['text'])

@php
    $steps = null;

    if (preg_match_all('/(?:^|\s)(\d+)\.\s+/u', $text, $markers, PREG_OFFSET_CAPTURE) && count($markers[0]) >= 2) {
        $isSequential = true;

        foreach ($markers[1] as $index => [$number, $offset]) {
            if ((int) $number !== $index + 1) {
                $isSequential = false;
                break;
            }
        }

        $onlyWhitespaceBeforeFirstMarker = trim(substr($text, 0, $markers[0][0][1])) === '';

        if ($isSequential && $onlyWhitespaceBeforeFirstMarker) {
            $markerCount = count($markers[0]);
            $candidateSteps = [];

            foreach ($markers[0] as $index => [$fullMatch, $startOffset]) {
                $contentStart = $startOffset + strlen($fullMatch);
                $contentEnd = $index + 1 < $markerCount ? $markers[0][$index + 1][1] : strlen($text);
                $candidateSteps[] = trim(substr($text, $contentStart, $contentEnd - $contentStart));
            }

            $steps = $candidateSteps;
        }
    }
@endphp

@if ($steps !== null)
    <ol class="list-decimal space-y-1 pl-5">
        @foreach ($steps as $step)
            <li>{{ $step }}</li>
        @endforeach
    </ol>
@else
    <p class="whitespace-pre-line">{{ $text }}</p>
@endif
