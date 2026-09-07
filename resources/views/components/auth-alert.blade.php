@props(['error', 'wireProperty', 'hint' => null, 'hintUrl' => null])

<div
    x-data
    x-show="$wire.{{ $wireProperty }}"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 -translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-end="opacity-0"
    x-cloak
    class="mb-4 flex items-start gap-2 rounded-md bg-status-error-subtle p-3 text-sm text-status-error"
    role="alert"
>
    <x-icon name="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
    <div>
        <p>{{ $error }}</p>
        @if ($hint && $hintUrl)
            <p class="mt-1">
                <a href="{{ $hintUrl }}" class="font-medium underline">{{ $hint }}</a>
            </p>
        @endif
    </div>
</div>
