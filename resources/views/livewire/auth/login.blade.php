<div class="rounded-xl border border-border-subtle bg-surface p-6 shadow-sm">
    <h1 class="mb-5 text-lg font-medium text-text-primary">Iniciar sesión</h1>

    <form wire:submit="login" class="space-y-4">
        <div>
            <label for="email" class="field-label">Correo institucional</label>
            <input
                type="email"
                id="email"
                wire:model="email"
                autofocus
                autocomplete="username"
                placeholder="nombre@uts.edu.co"
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
                placeholder="••••••••"
                class="field-input"
            >
            @error('password')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-2 text-[0.8125rem] sm:flex-row sm:items-center sm:justify-between">
            <label class="flex items-center gap-2.5 text-text-secondary">
                <input type="checkbox" wire:model="remember" class="field-checkbox">
                Mantener sesión activa
            </label>
            <a href="#" class="font-medium text-brand-primary hover:underline">¿Olvidó su contraseña?</a>
        </div>

        <button
            type="submit"
            class="inline-flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary px-4 py-2.5 text-sm font-medium text-white transition-opacity hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-brand-primary disabled:cursor-not-allowed disabled:opacity-60"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove wire:target="login">Iniciar sesión</span>
            <span wire:loading wire:target="login">Verificando...</span>
        </button>
    </form>
</div>
