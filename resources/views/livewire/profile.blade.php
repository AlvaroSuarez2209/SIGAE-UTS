<div class="mx-auto max-w-xl">
    <h1 class="mb-6 page-title">Mi perfil</h1>

    {{-- Información de perfil --}}
    <div class="card mb-6 p-6">
        <h2 class="text-lg font-semibold text-text-primary">Información de perfil</h2>
        <p class="section-subtitle">Actualiza tu información de cuenta.</p>

        @if (session('profileStatus'))
            <div class="mb-4 flex items-center gap-2 rounded-md bg-status-success-subtle p-3 text-sm text-status-success">
                <x-icon name="check-circle" class="h-4 w-4 shrink-0" />
                {{ session('profileStatus') }}
            </div>
        @endif

        <form wire:submit="saveProfile" class="space-y-4">
            <div>
                <label class="field-label">Nombre completo</label>
                <input type="text" wire:model="name" class="field-input">
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="field-label">Número de documento</label>
                <input type="text" wire:model="document_number" class="field-input">
                @error('document_number') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="field-label">Correo electrónico</label>
                <input type="email" wire:model="email" class="field-input">
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="pt-2">
                <button type="submit" class="btn-primary">Guardar</button>
            </div>
        </form>
    </div>

    {{-- Actualizar contraseña --}}
    <div class="card p-6">
        <h2 class="text-lg font-semibold text-text-primary">Actualizar contraseña</h2>
        <p class="section-subtitle">Usa una contraseña que no utilices en ningún otro sitio.</p>

        @if (session('passwordStatus'))
            <div class="mb-4 flex items-center gap-2 rounded-md bg-status-success-subtle p-3 text-sm text-status-success">
                <x-icon name="check-circle" class="h-4 w-4 shrink-0" />
                {{ session('passwordStatus') }}
            </div>
        @endif

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
                <button type="submit" class="btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>
