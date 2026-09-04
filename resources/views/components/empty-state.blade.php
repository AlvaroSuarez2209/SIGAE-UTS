@props(['icon' => 'inbox', 'title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-2 px-4 py-10 text-center']) }}>
    <x-icon :name="$icon" class="h-8 w-8 text-text-secondary" />
    <p class="text-sm font-medium text-text-primary">{{ $title }}</p>
    @if ($description)
        <p class="max-w-sm text-sm text-text-secondary">{{ $description }}</p>
    @endif
    @isset($action)
        <div class="mt-2">{{ $action }}</div>
    @endisset
</div>
