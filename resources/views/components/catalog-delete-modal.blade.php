{{--
    Modal de borrado real compartido por los 7 listados de catálogo
    (punto 2 + su ajuste). Siempre se abre al hacer clic en
    <x-delete-button> — nunca se calculan dependencias al pintar la
    lista, se calculan en el servidor justo al abrir este modal (ver
    openDeleteConfirmation() en cada Index), y se repiten dentro de la
    transacción al confirmar (CatalogDeletionService). Dos variantes,
    decididas aquí según $blockers/$protectedMessage:

    1. Sin bloqueo: confirmación con "Eliminar definitivamente". Para
       periodos (requiresCode) exige escribir el código antes de
       habilitar ese botón.
    2. Con bloqueo (dependientes o periodo protegido): "No se puede
       eliminar", el detalle, y "Desactivar" (solo si $deactivateAction
       y el elemento está activo) + "Cerrar". Nunca hay botón de
       eliminar en esta variante.

    Mismo criterio que <x-confirm-modal> y el resto de modales del
    proyecto (ver docs/manual-tecnico.md §5.8): Escape y el botón
    cierran, nunca un clic fuera — a propósito, para no cancelar una
    acción por accidente. El foco entra al modal (al botón
    Cancelar/Cerrar, igual que <x-confirm-modal>) y vuelve al botón que
    lo abrió gracias a window.__sigaeDeleteTrigger (ver
    <x-delete-button>).
--}}
@props([
    'model' => null,
    'typeLabel',
    'blockers' => [],
    'suggestion' => null,
    'protectedMessage' => null,
    'error' => '',
    'deleteAction',
    'deactivateAction' => null,
    'requiresCode' => false,
])

@if ($model)
    @php $isBlocked = $protectedMessage !== null || $blockers !== []; @endphp
    <div
        x-data="{ typedCode: '' }"
        x-on:keydown.escape.window="$wire.closeDeleteConfirmation(); window.__sigaeDeleteTrigger?.focus()"
        class="fixed inset-0 z-10 flex items-center justify-center bg-text-primary/40 px-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="delete-modal-title"
    >
        {{--
            x-trap.noreturn.noautofocus (plugin de foco de Alpine, incluido
            en el bundle de Livewire): solo agrega la contención del Tab
            dentro del panel (antes se podía escapar hacia el fondo de la
            página) — .noautofocus y .noreturn dejan el foco inicial y el
            retorno de foco al disparador exactamente como ya estaban
            (x-init de abajo y window.__sigaeDeleteTrigger en cada botón),
            que ya funcionan bien con el remontaje de Livewire al abrir y
            cerrar este modal (a diferencia de <x-confirm-modal>, que nunca
            se remonta — solo se oculta/muestra — y por eso ahí x-trap sí
            puede manejar el foco inicial y el retorno por su cuenta).
        --}}
        <div
            class="card w-full max-w-md p-6 shadow-lg"
            x-trap.noreturn.noautofocus="true"
            x-init="$nextTick(() => $refs.deleteModalInitialFocus?.focus())"
        >
            @if ($isBlocked)
                <h2 id="delete-modal-title" class="mb-2 text-base font-semibold text-text-primary">No se puede eliminar</h2>
                <p class="mb-4 text-sm text-text-primary"><strong>{{ $model->name }}</strong></p>

                @if ($protectedMessage)
                    <p class="mb-6 text-sm text-status-error">{{ $protectedMessage }}</p>
                @else
                    <p class="mb-2 text-sm text-text-secondary">Tiene {{ implode(' y ', $blockers) }}.</p>
                    @if ($suggestion)
                        <p class="mb-6 text-sm text-text-secondary">{{ $suggestion }}</p>
                    @endif
                @endif

                <div class="flex items-center justify-end gap-3">
                    @if ($deactivateAction && ! $protectedMessage && ($model->is_active ?? false))
                        <button
                            type="button"
                            wire:click="{{ $deactivateAction }}({{ $model->id }})"
                            x-on:click="window.__sigaeDeleteTrigger?.focus()"
                            class="btn-warning"
                        >Desactivar</button>
                    @endif
                    <button
                        type="button"
                        x-ref="deleteModalInitialFocus"
                        wire:click="closeDeleteConfirmation"
                        x-on:click="window.__sigaeDeleteTrigger?.focus()"
                        class="btn-secondary"
                    >Cerrar</button>
                </div>
            @else
                <h2 id="delete-modal-title" class="mb-2 text-base font-semibold text-text-primary">Eliminar {{ $typeLabel }}</h2>
                <p class="mb-2 text-sm text-text-primary"><strong>{{ $model->name }}</strong></p>
                <p class="mb-4 text-sm text-text-secondary">Esta acción no se puede deshacer.</p>

                @if ($error)
                    <div class="mb-4 flex items-center gap-2 rounded-md bg-status-error-subtle p-4 text-sm text-status-error">
                        <x-icon name="alert-triangle" class="h-4 w-4 shrink-0" />
                        {{ $error }}
                    </div>
                @endif

                @if ($requiresCode)
                    <div class="mb-4">
                        <label class="field-label">Código del periodo</label>
                        <input type="text" x-model="typedCode" class="field-input" autocomplete="off">
                        <p class="field-help">Escribe <strong>{{ $model->name }}</strong> para habilitar "Eliminar definitivamente".</p>
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        x-ref="deleteModalInitialFocus"
                        wire:click="closeDeleteConfirmation"
                        x-on:click="window.__sigaeDeleteTrigger?.focus()"
                        class="btn-secondary"
                    >Cancelar</button>

                    @if ($requiresCode)
                        <button
                            type="button"
                            class="btn-danger"
                            x-bind:disabled="typedCode !== @js($model->name)"
                            x-on:click="$wire.{{ $deleteAction }}(typedCode)"
                        >Eliminar definitivamente</button>
                    @else
                        <button type="button" wire:click="{{ $deleteAction }}" class="btn-danger">
                            Eliminar definitivamente
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endif
