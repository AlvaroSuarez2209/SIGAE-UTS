<div>
    <div class="rounded-lg bg-white p-6 shadow">
        <h1 class="text-lg font-semibold text-gray-800">Bienvenido, {{ auth()->user()->name }}</h1>
        <p class="mt-2 text-sm text-gray-600">
            Tus roles: {{ auth()->user()->roles->pluck('label')->join(', ') ?: 'Sin roles asignados' }}
        </p>
        <p class="mt-4 text-sm text-gray-500">
            Los paneles de seguimiento por rol (docente, líder, coordinación) se implementarán en un módulo posterior.
        </p>
    </div>
</div>
