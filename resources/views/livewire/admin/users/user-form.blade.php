<div class="max-w-xl">
    <h1 class="mb-6 text-2xl font-semibold text-text-primary">
        {{ $user ? 'Editar usuario' : 'Nuevo usuario' }}
    </h1>

    <form wire:submit="save" class="card space-y-4 p-6">
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

        <div>
            <label class="field-label">
                Contraseña
                @if ($user) <span class="font-normal text-text-secondary">(dejar en blanco para no cambiarla)</span> @endif
            </label>
            <input type="password" wire:model="password" class="field-input">
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <span class="field-label">Roles</span>
            <div class="mt-2 space-y-1">
                @foreach ($roles as $role)
                    <label class="flex items-center gap-2 text-sm text-text-secondary">
                        <input type="checkbox" wire:model="selectedRoles" value="{{ $role->name }}" class="field-checkbox">
                        {{ $role->label }}
                    </label>
                @endforeach
            </div>
            @error('selectedRoles') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" wire:model="is_active" id="is_active" class="field-checkbox">
            <label for="is_active" class="text-sm text-text-secondary">Cuenta activa</label>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="btn-primary">
                Guardar
            </button>
            <a href="{{ route('admin.users.index') }}" class="btn-text text-text-secondary">Cancelar</a>
        </div>
    </form>
</div>
