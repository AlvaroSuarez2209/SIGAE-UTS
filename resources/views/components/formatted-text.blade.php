{{--
    Texto largo de un entregable (descripción, instrucciones, criterio de
    cumplimiento) — compartido entre evidence-workspace.blade.php (docente)
    y review-show.blade.php (líder) para que ambos formateen igual.

    Solo se convierte en lista numerada <ol> cuando TODAS las líneas no
    vacías son pasos consecutivos desde 1 ("1. ...", "2. ...", sin saltos
    ni líneas sueltas) y hay al menos 2 — nunca por encontrar "1." en
    cualquier parte del texto (así "Vigente 2026. Debe incluir…" o "Ver
    punto 1.5 del formato" nunca se confunden con una lista). Si no aplica,
    se muestra el texto tal cual, respetando los saltos de línea.

    Props:
    - text: string
--}}
@props(['text'])

@php
    $lines = array_values(array_filter(
        array_map('trim', explode("\n", $text)),
        fn ($line) => $line !== ''
    ));

    $steps = null;

    if (count($lines) >= 2) {
        $candidateSteps = [];

        foreach ($lines as $index => $line) {
            if (! preg_match('/^'.($index + 1).'\.\s*(.+)$/', $line, $matches)) {
                $candidateSteps = null;
                break;
            }

            $candidateSteps[] = $matches[1];
        }

        $steps = $candidateSteps;
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
