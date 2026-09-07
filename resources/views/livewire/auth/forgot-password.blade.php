<div class="rounded-xl border border-border-subtle bg-surface p-6 shadow-sm">
    <h1 class="mb-2 text-lg font-medium text-text-primary">Restablecer contraseña</h1>
    <p class="mb-5 text-sm text-text-secondary">
        Escribe tu correo institucional y te enviaremos un enlace para crear una nueva contraseña.
    </p>

    @if ($status)
        <div class="mb-4 flex items-center gap-2 rounded-md bg-status-success-subtle p-3 text-sm text-status-success">
            <x-icon name="check-circle" class="h-4 w-4 shrink-0" />
            {{ $status }}
        </div>
    @endif

    <form wire:submit="sendResetLink" class="space-y-4">
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

        <button
            type="submit"
            class="inline-flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-brand-primary to-brand-secondary px-4 py-2.5 text-sm font-medium text-white transition-opacity hover:opacity-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-brand-primary disabled:cursor-not-allowed disabled:opacity-60"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove wire:target="sendResetLink">Enviar enlace</span>
            <span wire:loading wire:target="sendResetLink">Enviando...</span>
        </button>

        <a href="{{ route('login') }}" class="btn-text block text-center text-text-secondary">Volver a iniciar sesión</a>
    </form>
</div>
