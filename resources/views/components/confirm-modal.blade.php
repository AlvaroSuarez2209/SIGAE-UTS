{{--
    Modal de confirmación reutilizable para acciones críticas — una sola
    instancia global (incluida una vez en layouts/app.blade.php). Reemplaza
    el confirm() nativo del navegador en toda la aplicación.

    Cualquier botón, en cualquier componente Livewire, lo dispara así:

    <button type="button" @click="$dispatch('confirm-modal', {
        title: 'Título de la acción',
        body: 'Cuerpo del mensaje, puede incluir <strong>HTML simple</strong>.',
        confirmLabel: 'Texto del botón de confirmar',
        variant: 'warning', // primary | secondary | success | warning | danger
        action: () => $wire.metodo(argumentos),
    })">Disparador</button>

    Cuando `body` incluya datos dinámicos (nombres, etc.), escápalos con
    `e()` antes de interpolarlos — evita romper el literal de JavaScript
    si el dato contiene comillas, y sigue siendo seguro dentro de x-html.
    Ver los usos existentes en periods/period-index.blade.php.
--}}
<div
    x-data="{
        open: false,
        title: '',
        body: '',
        confirmLabel: 'Confirmar',
        variant: 'primary',
        action: null,
        show(detail) {
            this.title = detail.title;
            this.body = detail.body;
            this.confirmLabel = detail.confirmLabel ?? 'Confirmar';
            this.variant = detail.variant ?? 'primary';
            this.action = detail.action ?? null;
            this.open = true;
        },
        confirm() {
            const action = this.action;
            this.open = false;
            if (action) action();
        },
    }"
    x-on:confirm-modal.window="show($event.detail)"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-text-primary/40 px-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="confirm-modal-title"
>
    {{--
        x-trap (plugin de foco de Alpine, incluido en el bundle de Livewire —
        no requiere instalar ni configurar nada aparte): mientras "open" es
        true, atrapa el Tab dentro de este panel (antes se podía escapar
        hacia el fondo de la página), enfoca el botón [autofocus] al abrir, y
        al cerrar (Cancelar, Confirmar o Escape, los tres solo cambian
        "open") devuelve el foco solo al elemento que lo tenía antes de
        abrir el modal — el botón que lo disparó.
    --}}
    <div class="card w-full max-w-md p-6 shadow-lg" x-trap="open">
        <h2 id="confirm-modal-title" class="mb-2 text-base font-semibold text-text-primary" x-text="title"></h2>
        <p class="mb-6 text-sm text-text-secondary" x-html="body"></p>
        <div class="flex items-center justify-end gap-3">
            <button type="button" autofocus @click="open = false" class="btn-secondary">
                Cancelar
            </button>
            <button
                type="button"
                @click="confirm()"
                :class="{
                    'btn-primary': variant === 'primary',
                    'btn-secondary': variant === 'secondary',
                    'btn-success': variant === 'success',
                    'btn-warning': variant === 'warning',
                    'btn-danger': variant === 'danger',
                }"
                x-text="confirmLabel"
            ></button>
        </div>
    </div>
</div>
