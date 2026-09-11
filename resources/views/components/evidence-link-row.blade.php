{{--
    Fila de un enlace de una evidencia — mismo criterio que
    <x-evidence-file-row>: usada en la bandeja de revisión y en "Mis
    entregables" para que se vea igual en los dos lugares.

    Props:
    - link: EvidenceLink
    - removable (bool, default false): muestra "Quitar" y despacha
      confirm-modal hacia $wire.removeLink($link->id).
--}}
@props(['link', 'removable' => false])

<li class="flex items-center justify-between gap-3 rounded-md border border-border-subtle px-3 py-2">
    <div class="flex min-w-0 items-center gap-2">
        <x-icon name="link" class="h-4 w-4 shrink-0 text-text-secondary" />
        <span class="truncate text-base text-text-primary">{{ $link->label ?: $link->url }}</span>
    </div>
    <div class="flex shrink-0 items-center gap-3">
        <a href="{{ $link->url }}" target="_blank" rel="noopener" class="btn-text">Abrir</a>
        @if ($removable)
            <button
                type="button"
                class="flex items-center gap-1 text-sm font-medium text-status-error hover:underline"
                @click="$dispatch('confirm-modal', {
                    title: 'Eliminar enlace',
                    body: '¿Eliminar el enlace <strong>{{ e($link->label ?: $link->url) }}</strong>? Esta acción no se puede deshacer.',
                    confirmLabel: 'Eliminar enlace',
                    variant: 'danger',
                    action: () => $wire.removeLink({{ $link->id }}),
                })"
            >
                <x-icon name="trash" class="h-4 w-4" />
                Quitar
            </button>
        @endif
    </div>
</li>
