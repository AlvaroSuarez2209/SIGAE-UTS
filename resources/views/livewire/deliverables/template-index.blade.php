<div>
    <div class="mb-6 flex items-center justify-between">
        <h1 class="page-title">Plantillas de entregables</h1>
        <a href="{{ route('deliverable-templates.create') }}" class="btn-primary">
            Nueva plantilla
        </a>
    </div>

    <p class="section-subtitle">
        Una plantilla es una configuración reutilizable (reglas, tipos de evidencia, criterios) que puedes usar como punto
        de partida al crear varios entregables concretos con fechas distintas.
    </p>

    <div class="table-shell">
        <table class="min-w-full divide-y divide-border-subtle">
            <thead>
                <tr>
                    <th class="table-header-cell">Nombre</th>
                    <th class="table-header-cell">Periodicidad</th>
                    <th class="table-header-cell">Obligatorio</th>
                    <th class="table-header-cell">Estado</th>
                    <th class="table-header-cell text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @forelse ($templates as $template)
                    <tr wire:key="template-{{ $template->id }}" class="table-row">
                        <td class="table-cell">{{ $template->name }}</td>
                        <td class="table-cell text-text-secondary">{{ $template->periodicity_type->label() }}</td>
                        <td class="table-cell text-text-secondary">{{ $template->is_mandatory ? 'Sí' : 'No' }}</td>
                        <td class="table-cell">
                            <x-active-badge :active="$template->is_active" />
                        </td>
                        <td class="table-cell text-right">
                            <a href="{{ route('deliverable-templates.edit', $template) }}" class="btn-text">Editar</a>
                            @if ($template->is_active)
                                <button
                                    type="button"
                                    class="btn-text ml-4 text-text-secondary"
                                    @click="$dispatch('confirm-modal', {
                                        title: 'Desactivar plantilla',
                                        body: '¿Desactivar la plantilla <strong>{{ e($template->name) }}</strong>? Ya no estará disponible para crear nuevos entregables a partir de ella, pero los entregables ya creados no se ven afectados.',
                                        confirmLabel: 'Desactivar',
                                        variant: 'warning',
                                        action: () => $wire.toggleActive({{ $template->id }}),
                                    })"
                                >Desactivar</button>
                            @else
                                <button type="button" wire:click="toggleActive({{ $template->id }})" class="btn-text ml-4 text-text-secondary">
                                    Activar
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-empty-state icon="document" title="No hay plantillas registradas" description='Usa el botón "Nueva plantilla" para crear la primera.' />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
