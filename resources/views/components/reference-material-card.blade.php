{{--
    Punto D de las correcciones post-prueba: tarjeta "Material de
    referencia" para quien ya tiene acceso al entregable (docente
    asignado, líder en su ámbito, Coordinación, Administrador) —
    compartida entre EvidenceWorkspace (docente) y ReviewShow
    (revisor/líder) para que se vea y se comporte igual en los dos
    lugares. No aparece en absoluto si no hay ni enlace ni archivos.

    Props:
    - deliverable: Deliverable (para su reference_url)
    - referenceFiles: Collection<DeliverableReferenceFile> ya cargada
      por el componente padre (nunca $deliverable->activeReferenceFiles
      directo aquí: dispararía una carga perezosa nueva con
      Model::preventLazyLoading() activo en local).
--}}
@props(['deliverable', 'referenceFiles'])

@if ($deliverable->reference_url || $referenceFiles->isNotEmpty())
    <div class="card mb-6 space-y-3 p-4">
        <h2 class="form-section-title">Material de referencia</h2>

        @if ($deliverable->reference_url)
            <a
                href="{{ $deliverable->reference_url }}"
                target="_blank"
                rel="noopener noreferrer"
                class="btn-secondary inline-flex w-fit items-center gap-2"
            >
                <x-icon name="link" class="h-4 w-4" />
                {{ parse_url($deliverable->reference_url, PHP_URL_HOST) ?: $deliverable->reference_url }}
            </a>
        @endif

        @foreach ($referenceFiles as $file)
            <div class="flex items-center justify-between gap-3 rounded-md border border-border-subtle px-3 py-2">
                <div class="flex min-w-0 items-center gap-2">
                    <x-icon name="paperclip" class="h-4 w-4 shrink-0 text-text-secondary" />
                    <span class="truncate text-base text-text-primary">{{ $file->original_name }}</span>
                    <span class="shrink-0 text-sm text-text-secondary">({{ $file->readable_size }})</span>
                </div>
                @if ($file->existsOnDisk())
                    <a href="{{ route('deliverable-reference-files.download', $file) }}" class="btn-text shrink-0">Descargar</a>
                @else
                    <span class="shrink-0 text-sm font-medium text-status-error">Archivo no disponible</span>
                @endif
            </div>
        @endforeach
    </div>
@endif
