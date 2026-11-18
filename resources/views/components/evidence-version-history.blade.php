{{--
    Historial unificado de versiones de una evidencia — compartido entre
    evidence-workspace.blade.php (docente) y review-show.blade.php (líder).

    Ajuste al historial de versiones, punto 1: la versión actual ya se ve
    completa en "Entrega para revisar" (líder) o en "Tu entrega" (docente)
    — aquí solo lista las VERSIONES ANTERIORES (más reciente primero), más
    una línea compacta de contexto para la actual, con su número y estado.

    Excepción a "sin repetir archivos ni comentario" (punto 1): cuando la
    versión actual YA fue enviada pero no es un borrador en edición activa
    ($evidence->draftVersionOrNull() no es ella) — p. ej. recién devuelta o
    reabierta, antes de la primera edición real — "Tu entrega" (docente)
    todavía muestra el formulario vacío (solo repite el borrador cuando
    currentVersion->submitted_at es null), así que sus archivos/enlaces/
    comentario no se ven en NINGÚN otro lado. Para no perder ese dato
    (punto 6), la línea de contexto los incluye ahí, de forma compacta y
    sin tarjeta propia. Cuando si es un borrador en edición activa, o
    cuando review-show ya la muestra siempre sin condición alguna en
    "Entrega para revisar", se omiten aquí para no duplicarlos.

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

    $currentVersion = $evidence->versions->firstWhere('id', $evidence->current_version_id);
    $currentReview = $currentVersion ? $reviewsByVersion->get($currentVersion->id)?->first() : null;
    $currentIsLiveDraft = $currentVersion && $currentVersion->id === $evidence->draftVersionOrNull()?->id;

    $previousVersions = $sortedVersions->reject(fn ($v) => $v->id === $evidence->current_version_id);

    // Insignia de "pendiente de revisión": mismo componente x-status-badge
    // que el resto, con un objeto mínimo que expone label()/color()/icon()
    // — no hay un valor de ReviewDecision para "aún sin decidir".
    $pendingReviewStatus = $pendingReviewLabel ? new class($pendingReviewLabel)
    {
        public function __construct(private string $label) {}

        public function label(): string { return $this->label; }

        public function color(): string { return 'warning'; }

        public function icon(): string { return 'clock'; }
    } : null;
@endphp

@if ($hasAnySubmittedVersion)
    @if ($currentVersion)
        <p class="mb-3 flex flex-wrap items-center gap-2 text-sm text-text-secondary">
            <span class="font-medium text-text-primary">
                Versión {{ $currentVersion->version_number }} (actual)
            </span>
            @if ($currentIsLiveDraft)
                — en edición
            @else
                — {{ $currentVersion->submitted_at ? 'enviada el '.$currentVersion->submitted_at->toReadable() : 'en edición' }}
                @if ($currentReview)
                    <x-status-badge :status="$currentReview->decision" class="text-sm" />
                @elseif ($pendingReviewStatus)
                    <x-status-badge :status="$pendingReviewStatus" class="text-sm" />
                @endif
            @endif
        </p>

        @if (! $currentIsLiveDraft)
            @php
                $currentFiles = $currentVersion->files;
                $currentLinks = $currentVersion->links;
            @endphp
            @if ($currentFiles->isNotEmpty() || $currentLinks->isNotEmpty() || $currentVersion->submission_comment)
                <div class="mb-3 space-y-1 text-sm text-text-secondary">
                    @foreach ($currentFiles as $file)
                        <x-evidence-file-row :file="$file" :available="$file->existsOnDisk()" />
                    @endforeach
                    @foreach ($currentLinks as $link)
                        <x-evidence-link-row :link="$link" />
                    @endforeach
                    @if ($currentVersion->submission_comment)
                        <p><span class="font-medium text-text-primary">Comentario del docente:</span> {{ $currentVersion->submission_comment }}</p>
                    @endif
                </div>
            @endif
        @endif
    @endif

    @if ($previousVersions->isEmpty())
        <p class="text-sm text-text-secondary">Esta es la primera versión.</p>
    @else
        <ul class="space-y-3">
            @foreach ($previousVersions as $historyVersion)
                @php $review = $reviewsByVersion->get($historyVersion->id)?->first(); @endphp
                <li class="card space-y-2 p-4 text-base">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-medium text-text-primary">
                            Versión {{ $historyVersion->version_number }} —
                            {{ $historyVersion->submitted_at ? 'enviada el '.$historyVersion->submitted_at->toReadable() : 'en edición' }}
                        </p>
                        @if ($review)
                            <x-status-badge :status="$review->decision" class="shrink-0 text-sm" />
                        @endif
                    </div>

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
                            <span class="font-medium text-text-primary">Comentario del docente:</span> {{ $historyVersion->submission_comment }}
                        </p>
                    @endif

                    @if ($review)
                        <div class="border-t border-border-subtle pt-2 space-y-1">
                            <p class="text-sm text-text-secondary">
                                {{ $review->reviewer->name }} · {{ $review->decided_at->toReadable() }}
                            </p>
                            @foreach ($review->observations as $observation)
                                <p class="text-base text-text-primary">
                                    <span class="font-medium">Observación de {{ $review->reviewer->name }}:</span> {{ $observation->body }}
                                </p>
                            @endforeach
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
@endif
