{{--
    Texto de ayuda compartido por los 4 campos de solo lectura de "Mi
    perfil" (document_number, email, document_type, program_unit_id) —
    único lugar donde vive esa copia, en vez de repetirla en cada campo.
    Quien ve su PROPIO perfil siendo Administrador ya puede corregir estos
    datos desde "Usuarios", así que recibe un mensaje distinto al de
    cualquier otro rol (que de verdad depende de alguien más).
--}}
@props(['isAdministrator' => false])

<p class="field-help">
    @if ($isAdministrator)
        Puedes modificarlo desde Usuarios.
    @else
        Para actualizar este dato, contacta a un Administrador.
    @endif
</p>
