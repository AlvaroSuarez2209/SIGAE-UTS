@props(['value', 'label', 'color' => 'neutral', 'icon' => null, 'url' => null])

@php
    $tones = [
        'neutral' => ['border' => 'border-l-border-subtle', 'bg' => 'bg-surface', 'icon' => 'text-text-secondary'],
        'primary' => ['border' => 'border-l-brand-primary', 'bg' => 'bg-brand-primary-subtle', 'icon' => 'text-brand-primary'],
        'secondary' => ['border' => 'border-l-secondary', 'bg' => 'bg-surface-muted', 'icon' => 'text-secondary'],
        'success' => ['border' => 'border-l-status-success', 'bg' => 'bg-status-success-subtle', 'icon' => 'text-status-success'],
        'warning' => ['border' => 'border-l-status-warning', 'bg' => 'bg-status-warning-subtle', 'icon' => 'text-status-warning'],
        'error' => ['border' => 'border-l-status-error', 'bg' => 'bg-status-error-subtle', 'icon' => 'text-status-error'],
        'accent' => ['border' => 'border-l-accent', 'bg' => 'bg-accent-subtle', 'icon' => 'text-accent'],
    ];

    $tone = $tones[$color] ?? $tones['neutral'];
    $tag = $url ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($url) href="{{ $url }}" @endif {{ $attributes->merge(['class' => "card card-accent {$tone['border']} {$tone['bg']} p-6 text-center".($url ? ' transition-shadow hover:shadow-md' : '')]) }}>
    @if ($icon)
        <x-icon :name="$icon" class="{{ $tone['icon'] }} mx-auto mb-2 h-6 w-6" />
    @endif
    <p class="text-3xl font-bold text-text-primary">{{ $value }}</p>
    <p class="text-sm text-text-secondary">{{ $label }}</p>
</{{ $tag }}>
