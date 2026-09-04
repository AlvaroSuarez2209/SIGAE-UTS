<div class="card p-8">
    <form wire:submit="login" class="space-y-4">
        <div>
            <label for="email" class="field-label">Correo electrónico</label>
            <input
                type="email"
                id="email"
                wire:model="email"
                autofocus
                autocomplete="username"
                class="field-input"
            >
            @error('email')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="field-label">Contraseña</label>
            <input
                type="password"
                id="password"
                wire:model="password"
                autocomplete="current-password"
                class="field-input"
            >
            @error('password')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center">
            <input type="checkbox" id="remember" wire:model="remember" class="field-checkbox">
            <label for="remember" class="ml-2 text-sm text-text-secondary">Recordarme</label>
        </div>

        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">Iniciar sesión</span>
            <span wire:loading wire:target="login">Verificando...</span>
        </button>
    </form>
</div>
