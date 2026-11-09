{{--
    Opciones agrupadas por componente para cualquier <select> de
    actividad (DeliverableForm, AssignmentForm, ActivityReport,
    ConsolidatedReport, Dashboard) — el <select> y su opción "en
    blanco" (placeholder) los sigue poniendo cada vista, esto solo
    centraliza el agrupado/etiqueta para que los 5 se vean idénticos.
    $activities debe venir ya ordenada con Activity::orderedByHierarchy().

    Uso:
        <select wire:model="activity_id" class="field-input">
            <option value="">Selecciona una actividad</option>
            <x-activity-select-options :activities="$activities" />
        </select>
--}}
@props(['activities'])

@foreach (\App\Services\ActivitySelectOptions::grouped($activities) as $group)
    <optgroup label="{{ $group['component'] }}">
        @foreach ($group['options'] as $option)
            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
        @endforeach
    </optgroup>
@endforeach
