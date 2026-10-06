<div class="mx-auto max-w-xl">
    <h1 class="mb-6 page-title">Mi perfil</h1>

    <x-flash-message />

    {{-- Información de perfil --}}
    <div class="card mb-6 p-6">
        <h2 class="text-lg font-semibold text-text-primary">Información de perfil</h2>
        <p class="section-subtitle">Actualiza tu información de cuenta.</p>

        <form wire:submit="saveProfile" class="space-y-4">
            <div>
                <label class="field-label">Nombre completo</label>
                <input type="text" wire:model="name" class="field-input">
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label">Tipo de documento</label>
                    <input type="text" value="{{ $document_type ?? '—' }}" disabled class="field-input">
                    <x-readonly-field-help :is-administrator="$isAdministrator" />
                </div>

                <div>
                    <label class="field-label">Número de documento</label>
                    <input type="text" wire:model="document_number" disabled class="field-input">
                    <x-readonly-field-help :is-administrator="$isAdministrator" />
                </div>
            </div>

            <div>
                <label class="field-label">Correo electrónico</label>
                <input type="email" wire:model="email" disabled class="field-input">
                <x-readonly-field-help :is-administrator="$isAdministrator" />
            </div>

            <div>
                <label class="field-label">Programa de adscripción</label>
                <input type="text" value="{{ $programUnitName ?? '—' }}" disabled class="field-input">
                <x-readonly-field-help :is-administrator="$isAdministrator" />
            </div>

            <div class="pt-2">
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="saveProfile">
                    <span wire:loading.remove wire:target="saveProfile">Guardar</span>
                    <span wire:loading wire:target="saveProfile">Guardando...</span>
                </button>
            </div>
        </form>
    </div>

    {{-- Actualizar contraseña --}}
    <div class="card p-6">
        <h2 class="text-lg font-semibold text-text-primary">Actualizar contraseña</h2>
        <p class="section-subtitle">Usa una contraseña que no utilices en ningún otro sitio.</p>

        <form wire:submit="savePassword" class="space-y-4">
            <div>
                <label class="field-label">Contraseña actual</label>
                <x-password-input wire:model="current_password" autocomplete="current-password" />
                @error('current_password') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="field-label">Contraseña nueva</label>
                <x-password-input wire:model="password" autocomplete="new-password" />
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="field-label">Confirmar contraseña</label>
                <x-password-input wire:model="password_confirmation" autocomplete="new-password" />
                @error('password_confirmation') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="pt-2">
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="savePassword">
                    <span wire:loading.remove wire:target="savePassword">Guardar</span>
                    <span wire:loading wire:target="savePassword">Guardando...</span>
                </button>
            </div>
        </form>
    </div>
</div>
