@props(['field'])

{{-- Siempre se renderiza (con altura mínima reservada) para que el mensaje no
     empuje el layout al aparecer, y para que el id exista siempre para aria-describedby. --}}
<p id="{{ $field }}-error" class="field-error flex min-h-[1.125rem] items-center gap-1" role="alert">
    @error($field)
        <x-icon name="alert-circle" class="h-3.5 w-3.5 shrink-0" />
        {{ $message }}
    @enderror
</p>
