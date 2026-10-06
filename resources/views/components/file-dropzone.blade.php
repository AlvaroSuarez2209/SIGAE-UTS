{{--
    Carga de archivos por arrastrar-y-soltar — extraído del markup que
    EvidenceWorkspace ya usaba para adjuntar evidencias, para reutilizarlo
    también en el asistente de importación de docentes (y cualquier
    subida futura) sin duplicar el patrón.

    Props:
    - wire-model (requerido): la propiedad Livewire a la que se enlaza
      (equivalente a wire:model en el <input> nativo).
    - multiple: true para selección múltiple (ver EvidenceWorkspace::$newFiles).
    - accept: lista de extensiones/tipos aceptados, ej. ".xlsx,.csv".
--}}
@props([
    'wireModel',
    'multiple' => false,
    'accept' => null,
])

@php
    $refName = \Illuminate\Support\Str::camel($wireModel).'Input';
    $inputId = \Illuminate\Support\Str::kebab($wireModel).'-dropzone';
    $noun = $multiple ? 'los archivos' : 'el archivo';
@endphp

<div
    x-data="{ isDragging: false, uploading: false, progress: 0 }"
    x-on:livewire-upload-start="uploading = true"
    x-on:livewire-upload-finish="uploading = false; progress = 0"
    x-on:livewire-upload-error="uploading = false"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
    @dragover.prevent="isDragging = true"
    @dragleave.prevent="isDragging = false"
    @drop.prevent="isDragging = false; $refs.{{ $refName }}.files = $event.dataTransfer.files; $refs.{{ $refName }}.dispatchEvent(new Event('change'))"
    :class="isDragging ? 'border-brand-primary bg-brand-primary-subtle' : 'border-border-subtle'"
    {{ $attributes->merge(['class' => 'rounded-md border-2 border-dashed p-6 text-center transition-colors']) }}
>
    <input
        type="file"
        x-ref="{{ $refName }}"
        wire:model="{{ $wireModel }}"
        @if ($multiple) multiple @endif
        @if ($accept) accept="{{ $accept }}" @endif
        id="{{ $inputId }}"
        class="sr-only"
    >
    <label for="{{ $inputId }}" class="flex cursor-pointer flex-col items-center gap-1.5">
        <x-icon name="paperclip" class="h-6 w-6 text-text-secondary" />
        <span class="text-sm text-text-secondary">
            Arrastra {{ $noun }} aquí o <span class="font-medium text-brand-primary">haz clic para seleccionar</span>
        </span>
    </label>

    <div x-show="uploading" x-cloak class="mx-auto mt-3 max-w-xs">
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-muted">
            <div class="h-full bg-brand-primary transition-all" :style="`width: ${progress}%`"></div>
        </div>
    </div>
</div>
