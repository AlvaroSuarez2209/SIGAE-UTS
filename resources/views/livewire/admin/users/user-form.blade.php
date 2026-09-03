<div class="max-w-xl">
    <h1 class="mb-6 text-lg font-semibold text-gray-800">
        {{ $user ? 'Editar usuario' : 'Nuevo usuario' }}
    </h1>

    <form wire:submit="save" class="space-y-4 rounded-lg bg-white p-6 shadow">
        <div>
            <label class="block text-sm font-medium text-gray-700">Nombre completo</label>
            <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Número de documento</label>
            <input type="text" wire:model="document_number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('document_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Correo electrónico</label>
            <input type="email" wire:model="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">
                Contraseña
                @if ($user) <span class="font-normal text-gray-400">(dejar en blanco para no cambiarla)</span> @endif
            </label>
            <input type="password" wire:model="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <span class="block text-sm font-medium text-gray-700">Roles</span>
            <div class="mt-2 space-y-1">
                @foreach ($roles as $role)
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="selectedRoles" value="{{ $role->name }}" class="rounded border-gray-300">
                        {{ $role->label }}
                    </label>
                @endforeach
            </div>
            @error('selectedRoles') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" wire:model="is_active" id="is_active" class="rounded border-gray-300">
            <label for="is_active" class="text-sm text-gray-700">Cuenta activa</label>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Guardar
            </button>
            <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-600 hover:underline">Cancelar</a>
        </div>
    </form>
</div>
