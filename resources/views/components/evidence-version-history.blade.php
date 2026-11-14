{{--
    Historial unificado de versiones de una evidencia — compartido entre
    evidence-workspace.blade.php (docente) y review-show.blade.php (líder).
    Reemplaza, en las dos pantallas, lo que antes vivía por separado:
    "Historial de versiones" (archivos/comentario) y "Revisiones del
    líder"/"Histórico de revisiones" (decisión/revisor/observación) — cada
    fila de aquí ya trae ambas cosas juntas, para la misma versión.

    Más reciente primero. No se muestra nada si ninguna versión se ha
    enviado todavía (un borrador nunca enviado no tiene nada que mostrar
    aquí — se ve en su propia sección de edición).

    Props:
    - evidence: Evidence, con versions.files, versions.links y
      reviews.reviewer/observations ya cargados por el componente padre
      (nunca lazy aquí, con preventLazyLoading() activo en local).
    - pendingReviewLabel (string|null, default null): texto para la
      versión actual cuando todavía no tiene revisión — solo lo pasa
      review-show.blade.php ("Pendiente de tu revisión"); el docente no
      necesita este marcador, ya ve su propio estado en la insignia de
      arriba.
--}}
@props(['evidence', 'pendingReviewLabel' => null])

@php
    $hasAnySubmittedVersion = $evidence->versions->contains(fn ($v) => $v->submitted_at !== null);
    $sortedVersions = $evidence->versions->sortByDesc('version_number');
    $reviewsByVersion = $evidence->reviews->groupBy('evidence_version_id');
@endphp

@if ($hasAnySubmittedVersion)
    <ul class="space-y-3">
        @foreach ($sortedVersions as $historyVersion)
            @php $review = $reviewsByVersion->get($historyVersion->id)?->first(); @endphp
            <li class="card space-y-2 p-4 text-base">
                <p class="font-medium text-text-primary">
                    Versión {{ $historyVersion->version_number }} —
                    {{ $historyVersion->submitted_at ? 'enviada el '.$historyVersion->submitted_at->toReadable() : 'en edición' }}
                    @if ($historyVersion->id === $evidence->current_version_id)
                        (actual)
                    @endif
                </p>

                @if ($historyVersion->description)
                    <p class="whitespace-pre-line text-text-secondary">{{ $historyVersion->description }}</p>
                @endif

                @if ($historyVersion->files->isEmpty())
                    <p class="text-sm text-text-secondary">Sin archivos adjuntos.</p>
                @else
                    <ul class="space-y-1">
                        @foreach ($historyVersion->files as $file)
                            <x-evidence-file-row :file="$file" :available="$file->existsOnDisk()" />
                        @endforeach
                    </ul>
                @endif

                @if ($historyVersion->links->isNotEmpty())
                    <ul class="space-y-1">
                        @foreach ($historyVersion->links as $link)
                            <x-evidence-link-row :link="$link" />
                        @endforeach
                    </ul>
                @endif

                @if ($historyVersion->submission_comment)
                    <p class="text-sm text-text-secondary">
                        <span class="font-medium text-text-primary">Comentario:</span> {{ $historyVersion->submission_comment }}
                    </p>
                @endif

                @if ($review)
                    <div class="border-t border-border-subtle pt-2">
                        <x-status-badge :status="$review->decision" class="mb-1" />
                        <p class="text-sm text-text-secondary">
                            {{ $review->reviewer->name }} · {{ $review->decided_at->toReadable() }}
                        </p>
                        @foreach ($review->observations as $observation)
                            <p class="mt-1 text-sm text-text-secondary">{{ $observation->body }}</p>
                        @endforeach
                    </div>
                @elseif ($pendingReviewLabel && $historyVersion->id === $evidence->current_version_id)
                    <p class="border-t border-border-subtle pt-2 text-sm font-medium text-status-warning">
                        {{ $pendingReviewLabel }}
                    </p>
                @endif
            </li>
        @endforeach
    </ul>
@endif
