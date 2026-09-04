@props(['value', 'label', 'color' => 'neutral'])

@php
    $accents = [
        'neutral' => 'border-l-border-subtle',
        'primary' => 'border-l-brand-primary',
        'secondary' => 'border-l-secondary',
        'success' => 'border-l-status-success',
        'warning' => 'border-l-status-warning',
        'error' => 'border-l-status-error',
        'accent' => 'border-l-accent',
    ];

    $accentClass = $accents[$color] ?? $accents['neutral'];
@endphp

<div {{ $attributes->merge(['class' => "card card-accent $accentClass p-4 text-center"]) }}>
    <p class="text-2xl font-bold text-text-primary">{{ $value }}</p>
    <p class="text-xs text-text-secondary">{{ $label }}</p>
</div>
