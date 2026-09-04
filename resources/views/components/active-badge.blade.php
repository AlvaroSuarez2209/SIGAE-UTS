@props(['active'])

<span {{ $attributes->merge(['class' => $active ? 'badge bg-status-success-subtle text-status-success' : 'badge bg-surface-muted text-text-secondary']) }}>
    <x-icon :name="$active ? 'check-circle' : 'x-circle'" class="h-3.5 w-3.5" />
    {{ $active ? 'Activo' : 'Inactivo' }}
</span>
