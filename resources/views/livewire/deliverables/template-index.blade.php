<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-gray-800">Plantillas de entregables</h1>
        <a href="{{ route('deliverable-templates.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            Nueva plantilla
        </a>
    </div>

    <p class="mb-4 text-sm text-gray-500">
        Una plantilla es una configuración reutilizable (reglas, tipos de evidencia, criterios) que puedes usar como punto
        de partida al crear varios entregables concretos con fechas distintas.
    </p>

    <div class="overflow-hidden rounded-lg bg-white shadow">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Nombre</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Periodicidad</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Obligatorio</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Estado</th>
                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($templates as $template)
                    <tr wire:key="template-{{ $template->id }}">
                        <td class="px-4 py-3 text-sm text-gray-800">{{ $template->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $template->periodicity_type->label() }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $template->is_mandatory ? 'Sí' : 'No' }}</td>
                        <td class="px-4 py-3 text-sm">
                            @if ($template->is_active)
                                <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">Activa</span>
                            @else
                                <span class="rounded-full bg-gray-200 px-2 py-1 text-xs font-medium text-gray-600">Inactiva</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <a href="{{ route('deliverable-templates.edit', $template) }}" class="text-indigo-600 hover:underline">Editar</a>
                            <button type="button" wire:click="toggleActive({{ $template->id }})" class="ml-3 text-gray-600 hover:underline">
                                {{ $template->is_active ? 'Desactivar' : 'Activar' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No hay plantillas registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
