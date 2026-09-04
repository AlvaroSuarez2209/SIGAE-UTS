@props(['status'])

@php
    $tones = [
        'neutral' => 'bg-surface-muted text-text-secondary',
        'secondary' => 'bg-surface-muted text-secondary',
        'primary' => 'bg-brand-primary-subtle text-brand-primary-dark',
        'warning' => 'bg-status-warning-subtle text-status-warning',
        'success' => 'bg-status-success-subtle text-status-success',
        'error' => 'bg-status-error-subtle text-status-error',
        'accent' => 'bg-accent-subtle text-accent',
    ];

    $toneClasses = $tones[$status->color()] ?? $tones['neutral'];
@endphp

<span {{ $attributes->merge(['class' => "badge $toneClasses"]) }}>
    <x-icon :name="$status->icon()" class="h-3.5 w-3.5" />
    {{ $status->label() }}
</span>
