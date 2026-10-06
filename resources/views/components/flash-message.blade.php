{{--
    Notificación de éxito/error compartida — único punto de este mensaje en
    toda la app. Reemplaza los bloques `@if (session('status'))...@endif`
    que antes se repetían a mano en Login, Evidencia, Revisión e Identidad
    institucional, y corrige que guardar un Entregable/Plantilla/Usuario/
    Liderazgo/Asignación o aprobar/devolver una evidencia dejara el mensaje
    flasheado sin mostrar nunca: esas 6 acciones SÍ hacían
    `session()->flash('status', ...)`, pero redirigían a una pantalla que
    nunca tenía este bloque. Por eso este componente vive una vez en cada
    layout (`layouts/app.blade.php` y `layouts/guest.blade.php` — Login/
    ForgotPassword/ResetPassword usan el layout de invitado, sin sidebar),
    para que cualquier redirect tras un guardado lo muestre automáticamente
    sin que la pantalla de destino tenga que acordarse de nada.

    Importante para quien toque una pantalla que NO redirige (modales de
    catálogo, "Mi perfil", Identidad institucional, reabrir una evidencia,
    guardar borrador/enviar/eximir evidencia): una actualización Livewire
    sin redirect solo reemplaza el fragmento del propio componente, nunca
    vuelve a renderizar el layout que lo rodea — así que esas pantallas
    necesitan ADEMÁS su propia llamada a `<x-flash-message />` dentro de su
    propia vista (ver evidence-workspace.blade.php, review-show.blade.php,
    institution-settings-form.blade.php, profile.blade.php y los 5
    índices de catálogo) para que el mensaje aparezca sin recargar la
    página. La copia del layout y la copia local nunca compiten: la del
    layout solo se ve en una carga de página completa (p. ej. al llegar
    por un redirect), la local solo en una actualización Livewire en la
    misma pantalla.

    'status' = éxito (verde), 'error' = error (rojo) — mismos dos tonos que
    ya usaban por separado los bloques que reemplaza (el de error ya lo
    usan los informes del módulo 9 cuando falla una exportación).
--}}
@if (session('status'))
    <div class="mb-4 flex items-center gap-2 rounded-md bg-status-success-subtle p-3 text-sm text-status-success">
        <x-icon name="check-circle" class="h-4 w-4 shrink-0" />
        {{ session('status') }}
    </div>
@endif

@if (session('error'))
    <div class="mb-4 flex items-center gap-2 rounded-md bg-status-error-subtle p-3 text-sm text-status-error">
        <x-icon name="alert-circle" class="h-4 w-4 shrink-0" />
        {{ session('error') }}
    </div>
@endif
