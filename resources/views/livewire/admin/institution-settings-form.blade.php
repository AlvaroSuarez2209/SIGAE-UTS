<div class="mx-auto max-w-4xl">
    <h1 class="mb-2 page-title">Identidad institucional</h1>
    <p class="section-subtitle">
        El nombre y los logos configurados aquí se usan en el login, el menú lateral y el encabezado de los informes
        PDF y los correos del sistema.
    </p>

    <x-flash-message />

    <form wire:submit="save" class="card space-y-6 p-6">
        <div>
            <label class="field-label">Nombre de la institución</label>
            <input type="text" wire:model="name" class="field-input" maxlength="255">
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <label class="field-label">Logo del login</label>
                <x-file-dropzone wire-model="logoLogin" accept=".png,.jpg,.jpeg,.svg" class="mt-2" />
                @error('logoLogin') <p class="field-error">{{ $message }}</p> @enderror
                <p class="field-help">PNG, JPG o SVG. Máximo 2MB. Apaisado — es el protagonista grande de la pantalla de login.</p>

                @if ($settings->logo_login_path && ! $logoLogin)
                    <p class="field-help flex items-center gap-1.5">
                        <x-icon name="paperclip" class="h-3.5 w-3.5 shrink-0" />
                        Logo del login personalizado configurado actualmente.
                    </p>
                @endif
            </div>

            <div>
                <label class="field-label">Logo de marca</label>
                <x-file-dropzone wire-model="logoMark" accept=".png,.jpg,.jpeg,.svg" class="mt-2" />
                @error('logoMark') <p class="field-error">{{ $message }}</p> @enderror
                <p class="field-help">PNG, JPG o SVG. Máximo 2MB. Cuadrado — se usa en el menú lateral, los informes PDF y los correos.</p>

                @if ($settings->logo_mark_path && ! $logoMark)
                    <p class="field-help flex items-center gap-1.5">
                        <x-icon name="paperclip" class="h-3.5 w-3.5 shrink-0" />
                        Logo de marca personalizado configurado actualmente.
                    </p>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save,logoLogin,logoMark">
                <span wire:loading.remove wire:target="save">Guardar</span>
                <span wire:loading wire:target="save">Guardando...</span>
            </button>

            <button
                type="button"
                class="btn-text text-text-secondary"
                @click="$dispatch('confirm-modal', {
                    title: 'Restablecer a valores por defecto',
                    body: 'Esto vuelve el nombre y AMBOS logos a los originales del sistema (<strong>Institución Universitaria Tecnológica de Santander</strong> y los logos estáticos por defecto). Se borran los 2 logos personalizados actuales.',
                    confirmLabel: 'Restablecer',
                    variant: 'warning',
                    action: () => $wire.resetToDefaults(),
                })"
            >
                Restablecer a valores por defecto
            </button>
        </div>
    </form>

    {{-- Vista previa en vivo — no el preview nativo del file input: ambas
         reproducen el ancho y el markup REALES de cada superficie (mismo
         w-72 y mismas clases que layouts/app.blade.php para el sidebar),
         no un contenedor más ancho que habría ocultado el truncamiento que
         se reportó como bug real. --}}
    <div class="mt-8 grid gap-6 sm:grid-cols-2">
        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-text-secondary">Vista previa — Login</p>
            <div class="card flex flex-col items-center gap-2 bg-surface-muted p-6 text-center">
                @if ($this->previewLoginLogoUrl)
                    <img src="{{ $this->previewLoginLogoUrl }}" alt="{{ $name }}" class="h-16 w-auto max-w-full object-contain">
                @else
                    <div class="flex h-12 w-12 -rotate-3 items-center justify-center rounded-xl bg-gradient-to-br from-brand-primary to-brand-secondary text-base font-semibold text-white">
                        S
                    </div>
                @endif
                <p class="text-lg font-semibold text-text-primary">SIGAE-UTS</p>
                <p class="text-xs text-text-secondary">{{ $name ?: 'Nombre de la institución' }}</p>
            </div>
        </div>

        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-text-secondary">Vista previa — Menú lateral (ancho real: 18rem)</p>
            {{-- w-72 + las mismas clases exactas del encabezado real en
                 layouts/app.blade.php: si el nombre no cabe aquí, tampoco
                 cabrá en producción — a diferencia de la versión anterior
                 de este preview, que no reproducía el ancho real. --}}
            <div class="w-72 overflow-hidden rounded-lg border border-border-subtle bg-surface">
                <div class="flex min-h-20 flex-col justify-center gap-0.5 border-b border-border-subtle px-6 py-3">
                    <span class="flex items-center gap-3 text-lg font-semibold text-brand-primary">
                        @if ($this->previewMarkLogoUrl)
                            <img src="{{ $this->previewMarkLogoUrl }}" alt="{{ $name }}" class="h-9 w-9 shrink-0 object-contain">
                        @else
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-brand-primary to-brand-secondary text-sm font-semibold text-white">S</span>
                        @endif
                        SIGAE-UTS
                    </span>
                    <p
                        class="line-clamp-2 pl-12 text-xs leading-tight text-text-secondary"
                        title="{{ $name }}"
                    >{{ $name ?: 'Nombre de la institución' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
