<div class="rounded-xl border border-border-subtle bg-surface p-6 shadow-sm">
    <h1 class="mb-2 text-lg font-medium text-text-primary">Crear nueva contraseña</h1>
    <p class="mb-5 text-sm text-text-secondary">
        Elige una nueva contraseña para tu cuenta.
    </p>

    <form wire:submit="resetPassword" class="space-y-4">
        <div>
            <label for="email" class="field-label">Correo institucional</label>
            <input
                type="email"
                id="email"
                wire:model="email"
                autocomplete="username"
                placeholder="nombre@uts.edu.co"
                class="field-input"
            >
            @error('email')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="field-label">Nueva contraseña</label>
            <input
                type="password"
                id="password"
                wire:model="password"
                autofocus
                autocomplete="new-password"
                placeholder="••••••••"
                class="field-input"
            >
            <p class="field-help">Mínimo 8 caracteres.</p>
            @error('password')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="field-label">Confirmar contraseña</label>
            <input
                type="password"
                id="password_confirmation"
                wire:model="password_confirmation"
                autocomplete="new-password"
                placeholder="••••••••"
                class="field-input"
            >
        </div>

        <button
            type="submit"
            class="inline-flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary px-4 py-2.5 text-sm font-medium text-white transition-opacity hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-brand-primary disabled:cursor-not-allowed disabled:opacity-60"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove wire:target="resetPassword">Guardar nueva contraseña</span>
            <span wire:loading wire:target="resetPassword">Guardando...</span>
        </button>
    </form>
</div>
