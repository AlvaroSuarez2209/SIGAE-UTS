# Manual de usuario — SIGAE-UTS

SIGAE-UTS organiza lo que cada persona ve y puede hacer según su(s)
**rol(es)**. Una misma cuenta puede tener varios roles a la vez (por
ejemplo, un líder que también es docente ve ambos paneles).

Para entrar al sistema, abre la aplicación en el navegador e ingresa tu
correo institucional y contraseña en la pantalla de inicio de sesión.
Si tu cuenta fue desactivada por un administrador, el sistema te lo
indicará y no podrás continuar, aunque la contraseña sea correcta.

**"Mi perfil"** (enlace en la parte inferior del menú lateral, junto a
"Cerrar sesión") está disponible para cualquier persona autenticada,
sin importar su rol: permite actualizar tu nombre, y por separado — con
su propio botón "Guardar" — cambiar tu contraseña pidiendo primero la
actual. El número de documento y el correo se muestran de solo
lectura (fondo gris, no editables): si necesitas corregir alguno de
los dos, debe hacerlo un Administrador desde "Usuarios". Tampoco
incluye tus roles ni el estado de tu cuenta (activa/inactiva): eso
también es exclusivo de un Administrador.

---

## Administrador

Tiene acceso a todo el sistema. Además de todo lo que puede hacer
Coordinación, exclusivamente el Administrador puede:

- **Usuarios** (menú "Usuarios"): crear cuentas, asignar uno o varios
  roles, y activar/desactivar cuentas sin borrar su historial. No puedes
  desactivar tu propia cuenta.
- **Auditoría** (menú "Auditoría"): consultar la bitácora de accesos,
  cargas, envíos, revisiones, aprobaciones, devoluciones y cambios
  administrativos, con filtros por usuario, acción y rango de fechas.
  Es de solo lectura: ningún usuario puede editar o borrar estos
  registros desde la aplicación.
- **Reabrir una evidencia aprobada**: dentro del detalle de una revisión
  ya decidida, el botón "Reabrir (permiso especial)" permite que el
  docente vuelva a ajustarla. Es una acción excepcional que queda
  registrada en la auditoría.

## Coordinación

Administra la configuración académica del periodo y consolida el
seguimiento. Desde el menú puede acceder a:

- **Periodos**: crear periodos académicos y avanzar su estado
  (Planeación → Activo → Cerrado → Archivado). Las fechas de un periodo
  solo se pueden editar mientras está en planeación. Cerrar un periodo
  bloquea la creación y edición ordinaria de distribución, líderes y
  entregables asociados a él; reabrirlo desde "Cerrado" es una
  excepción que también queda auditada.
- **Catálogos** (menú desplegable "Catálogos"): Componentes,
  Subcomponentes, Actividades, Programas y Compromisos transversales.
  Todo se activa/desactiva, nunca se borra, para no alterar el
  histórico de periodos ya cerrados.
- **Distribución**: registrar qué actividad tiene asignada cada docente
  en cada periodo, con sus horas (informativas — no determinan la
  cantidad de entregables).
- **Líderes**: asignar uno o varios líderes a una actividad puntual o a
  todo un programa, con fecha de inicio y fin de su vigencia.
- **Entregables** y **Plantillas de entregables**: crear entregables
  ligados a una actividad (para todos los docentes de esa actividad o
  para un subconjunto elegido) o como compromiso transversal
  (independiente de la distribución). Una plantilla permite reutilizar
  la misma configuración (tipos de evidencia, formatos, criterio de
  cumplimiento) en varios entregables sin volver a escribirla.
- **Informes** (menú desplegable "Informes"): los cuatro informes
  mínimos — individual por docente, por actividad, de compromisos
  transversales y consolidado por periodo — cada uno exportable a PDF
  y Excel.
- **Revisión**: aunque el rol pensado para revisar evidencias es Líder,
  Coordinación también puede aprobar o devolver cualquier evidencia
  enviada (respaldo cuando una actividad no tiene líder asignado, o para
  compromisos transversales, que no tienen un líder propio).
- **Panel de coordinación** (en el Dashboard): conteo de evidencias por
  estado y el % de avance de cada docente en el periodo seleccionado.

## Líder

Ve y gestiona únicamente lo que le corresponde según sus liderazgos
vigentes (toda una actividad, o todas las actividades de un programa
completo, para un periodo dado).

- **Revisión** (menú "Revisión"): bandeja de evidencias enviadas por los
  docentes de su ámbito, pendientes de decisión. Al abrir una, puede ver
  la descripción del entregable, los archivos/enlaces adjuntos y:
  - **Aprobar** (la observación es opcional).
  - **Devolver** (la observación es **obligatoria** — debe explicar qué
    debe ajustar el docente).
  Cada decisión queda en el histórico de revisiones de esa evidencia,
  visible tanto para el líder como para el propio docente.
- **Panel líder** (en el Dashboard): cuántas evidencias tiene pendientes
  de revisar, y una tabla de cumplimiento por actividad — para cada
  docente de su ámbito, cuántos entregables obligatorios tiene
  aprobados sobre el total.
- Un líder **nunca puede revisar su propia evidencia**, aunque también
  tenga rol de docente sobre esa misma actividad.

## Docente

- **Mis entregables** (menú "Mis entregables"): lista de todo lo que
  tiene asignado, agrupada por actividad (o "Compromisos transversales"
  para lo que no depende de una actividad), con su fecha límite y
  estado (Pendiente, Borrador, Enviado, Requiere ajustes, Aprobado,
  Vencido, Exento).
- Al entrar a un entregable puede:
  - Adjuntar lo que el entregable permita: archivo(s), texto y/o
    enlace(s), según su configuración.
  - **Guardar borrador** cuantas veces quiera mientras no lo haya
    enviado.
  - **Enviar evidencia**: acción explícita que exige confirmación. El
    sistema exige al menos un tipo de evidencia con contenido antes de
    permitir el envío.
  - Una vez enviada, la evidencia queda protegida: no se puede editar
    directamente. Si el líder la devuelve, el docente puede volver a
    editarla — al reenviarla se crea una **versión nueva**, sin borrar
    la anterior. El docente ve el motivo de la devolución (la
    observación del líder) directamente en la pantalla del entregable.
- **Panel docente** (en el Dashboard): conteo de sus entregables por
  estado, su % de avance (solo sobre los obligatorios), y sus próximos
  vencimientos de los siguientes 14 días.

## Auditor

Rol de solo consulta, pensado para revisión externa o institucional:

- **Informes** (menú "Informes"): acceso a los cuatro informes mínimos
  y sus exportaciones a PDF/Excel, igual que Coordinación.
- **Panel de coordinación** (en el Dashboard): el mismo consolidado por
  estado y por docente que ve Coordinación.
- Puede consultar el detalle de cualquier evidencia entrando a su URL
  directa (por ejemplo, desde un enlace compartido en un informe), pero
  **no puede aprobarla, devolverla ni editarla** — solo verla.
- No tiene acceso a los formularios de gestión (usuarios, catálogos,
  distribución, líderes, entregables) ni a la bitácora de auditoría.

---

## Preguntas frecuentes

**¿Por qué no puedo iniciar sesión?**
Verifica que el correo y la contraseña sean correctos. Si el sistema
indica que tu cuenta fue desactivada, contacta a un Administrador — solo
él puede reactivarla.

**Cargué un archivo y no puedo reemplazarlo.**
Antes de enviar la evidencia sí puedes quitar y volver a adjuntar
archivos ("Quitar" junto a cada archivo). Una vez que le das "Enviar
evidencia", queda protegida; si necesitas corregir algo después de
enviarla, debes esperar a que el líder la revise y, si aplica, la
devuelva para que puedas ajustarla.

**¿Las horas que me asignaron determinan cuántos entregables tengo?**
No. Las horas asignadas en la distribución docente son solo información
de dedicación — la cantidad y tipo de entregables de una actividad la
define quien la crea (Coordinación), independientemente de las horas.

**¿Por qué mi % de avance no sube aunque ya envié todo?**
El % de avance solo cuenta entregables **obligatorios** que ya fueron
**aprobados** por el líder — un entregable enviado pero aún no revisado,
o un entregable opcional, no suma al porcentaje.
