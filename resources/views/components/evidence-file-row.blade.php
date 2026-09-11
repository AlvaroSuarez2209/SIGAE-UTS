{{--
    Fila de un archivo adjunto de una evidencia — usada tanto en la bandeja
    de revisión como en "Mis entregables" para que un mismo tipo de
    contenido (archivo ya cargado) se vea igual en los dos lugares: ícono +
    nombre + tamaño a la izquierda, acciones alineadas a la derecha.

    Props:
    - file: EvidenceFile
    - removable (bool, default false): muestra "Quitar" y despacha
      confirm-modal hacia $wire.removeFile($file->id) — el llamador debe
      exponer ese método (p. ej. EvidenceWorkspace). La bandeja de
      revisión, de solo lectura, simplemente no pasa esta prop.
--}}
@props(['file', 'removable' => false])

<li class="flex items-center justify-between gap-3 rounded-md border border-border-subtle px-3 py-2">
    <div class="flex min-w-0 items-center gap-2">
        <x-icon name="paperclip" class="h-4 w-4 shrink-0 text-text-secondary" />
        <span class="truncate text-base text-text-primary">{{ $file->original_name }}</span>
        <span class="shrink-0 text-sm text-text-secondary">({{ $file->readable_size }})</span>
    </div>
    <div class="flex shrink-0 items-center gap-3">
        <a href="{{ route('evidence-files.download', $file) }}" class="btn-text">Descargar</a>
        @if ($removable)
            <button
                type="button"
                class="flex items-center gap-1 text-sm font-medium text-status-error hover:underline"
                @click="$dispatch('confirm-modal', {
                    title: 'Eliminar archivo',
                    body: '¿Eliminar el archivo <strong>{{ e($file->original_name) }}</strong>? Esta acción no se puede deshacer.',
                    confirmLabel: 'Eliminar archivo',
                    variant: 'danger',
                    action: () => $wire.removeFile({{ $file->id }}),
                })"
            >
                <x-icon name="trash" class="h-4 w-4" />
                Quitar
            </button>
        @endif
    </div>
</li>
