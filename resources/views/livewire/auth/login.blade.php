<div class="w-full max-w-sm rounded-lg bg-white p-8 shadow">
    <div class="mb-6 text-center">
        <h1 class="text-xl font-semibold text-gray-800">SIGAE-UTS</h1>
        <p class="text-sm text-gray-500">Sistema de Gestión de Actividades y Evidencias Docentes</p>
    </div>

    <form wire:submit="login" class="space-y-4">
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Correo electrónico</label>
            <input
                type="email"
                id="email"
                wire:model="email"
                autofocus
                autocomplete="username"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
            <input
                type="password"
                id="password"
                wire:model="password"
                autocomplete="current-password"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center">
            <input type="checkbox" id="remember" wire:model="remember" class="rounded border-gray-300">
            <label for="remember" class="ml-2 text-sm text-gray-600">Recordarme</label>
        </div>

        <button
            type="submit"
            class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove wire:target="login">Iniciar sesión</span>
            <span wire:loading wire:target="login">Verificando...</span>
        </button>
    </form>
</div>
