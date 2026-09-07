<div class="rounded-xl border border-border-subtle bg-surface p-6 shadow-sm">
    <h1 class="mb-2 text-lg font-medium text-text-primary">Crear nueva contraseña</h1>
    <p class="mb-5 text-sm text-text-secondary">
        Restableciendo la contraseña de <span class="font-medium text-text-primary">{{ $email }}</span>.
    </p>

    <x-auth-alert :error="$genericError" wire-property="genericError" />

    <form wire:submit="resetPassword" class="space-y-4" novalidate>
        <div>
            <label for="password" class="field-label">Nueva contraseña</label>
            <x-password-input
                id="password"
                wire:model.live="password"
                autofocus
                autocomplete="new-password"
                placeholder="••••••••"
                :class="$submitAttempted && ! $this->meetsAllRequirements() ? 'border-status-error' : ''"
                aria-invalid="{{ $submitAttempted && ! $this->meetsAllRequirements() ? 'true' : 'false' }}"
                aria-describedby="password-requirements"
            />

            {{-- Checklist de requisitos: neutro (pero legible) mientras se escribe,
                 verde al cumplirse, y solo se pone rojo tras un intento de envío fallido. --}}
            <div id="password-requirements" class="mt-2 rounded-lg bg-surface-muted p-3">
                <p class="mb-2 text-xs font-medium text-text-secondary">Tu contraseña debe tener:</p>
                <ul class="space-y-1">
                    @foreach ($this->passwordRequirements() as $requirement)
                        <li @class([
                            'flex items-center gap-1.5 text-xs',
                            'text-status-success' => $requirement['met'],
                            'text-status-error' => ! $requirement['met'] && $submitAttempted,
                            'text-text-primary' => ! $requirement['met'] && ! $submitAttempted,
                        ])>
                            @if ($requirement['met'])
                                <x-icon name="check-circle" class="h-3.5 w-3.5 shrink-0" />
                            @elseif ($submitAttempted)
                                <x-icon name="alert-circle" class="h-3.5 w-3.5 shrink-0" />
                            @else
                                <span class="flex h-3.5 w-3.5 shrink-0 items-center justify-center">
                                    <span class="h-2 w-2 rounded-full border border-text-secondary"></span>
                                </span>
                            @endif
                            {{ $requirement['label'] }}
                        </li>
                    @endforeach
                </ul>

                @if ($password !== '')
                    @php $level = $this->passwordStrengthLevel(); @endphp
                    <div class="mt-3 flex items-center gap-2">
                        <div class="flex flex-1 gap-1">
                            @for ($i = 1; $i <= 3; $i++)
                                <span @class([
                                    'h-1 flex-1 rounded-full',
                                    'bg-status-error' => $level === 1 && $i === 1,
                                    'bg-status-warning' => $level === 2 && $i <= 2,
                                    'bg-status-success' => $level === 3,
                                    'bg-border-subtle' => ($level === 1 && $i > 1) || ($level === 2 && $i > 2),
                                ])></span>
                            @endfor
                        </div>
                        <span class="text-xs font-medium text-text-secondary">{{ $this->passwordStrengthLabel() }}</span>
                    </div>
                @endif
            </div>

            @if ($submitAttempted && ! $this->meetsAllRequirements())
                <p class="field-error flex items-center gap-1">
                    <x-icon name="alert-circle" class="h-3.5 w-3.5 shrink-0" />
                    Tu contraseña no cumple con todos los requisitos.
                </p>
            @endif
        </div>

        <div>
            <label for="password_confirmation" class="field-label">Confirmar contraseña</label>
            <x-password-input
                id="password_confirmation"
                wire:model.live.blur="password_confirmation"
                autocomplete="new-password"
                placeholder="••••••••"
                :class="$this->confirmationError() ? 'border-status-error' : ''"
                aria-invalid="{{ $this->confirmationError() ? 'true' : 'false' }}"
                aria-describedby="password_confirmation-error"
            />
            <p id="password_confirmation-error" class="field-error flex min-h-[1.125rem] items-center gap-1" role="alert">
                @if ($this->confirmationError())
                    <x-icon name="alert-circle" class="h-3.5 w-3.5 shrink-0" />
                    {{ $this->confirmationError() }}
                @endif
            </p>
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
