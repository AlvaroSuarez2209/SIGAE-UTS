# Manual de diseño — SIGAE-UTS

## 1. Enfoque

SIGAE-UTS es una herramienta de trabajo diario, no un sitio de mercadeo: la
usan docentes, líderes, coordinación y administración de UTS para cargar,
revisar y consolidar evidencias varias veces por semana durante todo el
periodo académico. El diseño prioriza claridad, confianza institucional y
eficiencia operativa sobre el impacto visual.

Principios que guiaron cada decisión:

1. **Claridad antes que decoración.** Cada color, tipografía o componente
   existe para comunicar un estado o guiar una acción, nunca por estética
   sola. No hay gradientes, ilustraciones ni imágenes decorativas.
2. **Confianza institucional por sobriedad.** Una paleta azul/gris de baja
   saturación y tipografía neutra transmiten seriedad administrativa — el
   mismo registro visual que usaría un sistema bancario o de gobierno, no
   una landing page comercial.
3. **El estado nunca depende solo del color.** Los 7 estados de evidencia
   (RNF-012) y cualquier otro estado del sistema siempre llevan texto + ícono
   además del color, para usuarios con daltonismo y para lectura rápida en
   pantallas de bajo contraste.
4. **Densidad funcional.** Docentes y líderes revisan listas largas a diario;
   las tablas privilegian filas compactas y legibles (≥14px) sobre espaciado
   decorativo, y los formularios usan campos ≥16px para evitar zoom
   automático en iOS.
5. **Accesibilidad como línea base, no como extra.** Contraste AA verificado
   por cálculo (no a ojo), foco de teclado siempre visible, y respeto a
   `prefers-reduced-motion` en todas las transiciones.

## 2. Paleta de colores

Todos los colores están definidos como tokens semánticos de Tailwind 4 en
`resources/css/app.css` (bloque `@theme`), nunca como valores hexadecimales
sueltos en las vistas. Esto permite reemplazar la paleta institucional real
(o el logo) sin tocar ninguna plantilla Blade.

| Token Tailwind | Hex | Rol | Ejemplo de uso |
|---|---|---|---|
| `brand-primary` | `#00447e` | Color de marca principal (azul del logo real) | Botones primarios, enlaces, encabezado del sidebar |
| `brand-primary-dark` | `#002a4e` | Marca, variante oscura | Hover de botones primarios |
| `brand-primary-subtle` | `#ebf0f5` | Marca, fondo suave | Fondo de enlace activo en el sidebar, badges "primary" |
| `brand-secondary` | `#0a7a45` | Marca, segundo tono del logo (verde) | Línea "UTS" y extremo del degradado del botón en el login |
| `secondary` | `#4b5a6a` | Color secundario (azul grisáceo) | Botón secundario (hover), badges de estado "archivado" |
| `accent` | `#8a6516` | Acento (ámbar apagado) | Badge de evidencias "exento" |
| `accent-subtle` | `#f6efdf` | Acento, fondo suave | Fondo de los badges de acento |
| `category` | `#5b4b8a` | Clasificación neutra (no es un estado) | Badge "Transversal" en Entregables/Dashboard/Revisión |
| `category-subtle` | `#efeaf7` | Clasificación, fondo suave | Fondo del badge "Transversal" |
| `status-success` | `#1e7a4c` | Éxito / aprobado | Badge "Aprobado", botón "Aprobar" en revisión |
| `status-success-subtle` | `#e8f5ee` | Éxito, fondo suave | Fondo de mensajes de confirmación |
| `status-warning` | `#9a5b12` | Advertencia | Badge "Requiere ajustes" / "Cerrado", botón "Devolver"; botón de confirmación (`variant: warning`) de "Desactivar", "Cerrar periodo", "Finalizar liderazgo" |
| `status-warning-subtle` | `#fbf1e4` | Advertencia, fondo suave | Fondo de avisos (periodo bloqueado, etc.) |
| `status-error` | `#b3261e` | Error | Badge de estado "Vencido"; botón de confirmación (`variant: danger`) de una eliminación permanente — ver aclaración justo debajo de esta tabla |
| `status-error-subtle` | `#fbeceb` | Error, fondo suave | Fondo de mensajes de error |
| `surface` | `#ffffff` | Superficie base | Fondo de tarjetas, tablas, sidebar |
| `surface-muted` | `#f4f6f8` | Superficie secundaria | Fondo de página, filas al pasar el cursor |
| `border-subtle` | `#d8dee4` | Bordes | Bordes de tarjetas, tablas, inputs |
| `text-primary` | `#1a2530` | Texto principal | Títulos, texto de cuerpo |
| `text-secondary` | `#5b6b7a` | Texto secundario | Metadatos, ayudas de formulario, encabezados de tabla |

Todos los pares texto/fondo usados en componentes reales (texto principal
sobre `surface`, texto de badges sobre sus fondos "subtle", texto blanco
sobre `brand-primary`/`status-success`/`status-warning`/`status-error`) se
verificaron con la fórmula de luminancia relativa de WCAG 2.x y cumplen un
mínimo de 4.5:1 para texto normal y 3:1 para texto grande, cumpliendo AA.

**El rojo (`status-error`) tiene dos usos distintos e intencionales, que no
deben confundirse entre sí ni combinarse por analogía de color:**

1. **Rojo en un badge de estado → exclusivo para el estado "Vencido"**
   (`EvidenceStatus::Expired`, `resources/views/components/status-badge.blade.php`).
   Ningún otro estado del sistema usa este color; es la señal visual de
   "algo venció y ya requiere atención", no de "algo se puede perder".
2. **Rojo en un botón de confirmación (`variant: danger` de
   `<x-confirm-modal>`, clase `.btn-danger`) → exclusivo para acciones
   destructivas e irreversibles**: "Eliminar archivo" y "Eliminar enlace"
   de una evidencia (`evidence-file-row.blade.php`,
   `evidence-link-row.blade.php`) son los únicos casos actuales, y
   cualquier "Eliminar" permanente futuro debe seguir esta misma regla.

**El ámbar (`status-warning`, `variant: warning`, clase `.btn-warning`) es
el color de las acciones reversibles de impacto medio**: "Desactivar" (un
usuario, un catálogo, una plantilla), "Cerrar" (un periodo) y "Finalizar"
(un liderazgo) — todas se pueden deshacer con una acción equivalente
("Activar", reabrir el periodo, reasignar el liderazgo) y ninguna borra
datos. Por eso **"Desactivar" nunca lleva `variant: danger`**, aunque a
primera vista ambos colores parezcan intercambiables como "advertencia
genérica": el criterio no es "qué tan grave se ve la acción", sino **si es
reversible (ámbar) o irreversible (rojo)** — confundir uno con otro por
similitud visual llevaría, por ejemplo, a pintar "Desactivar" de rojo (como
ocurrió una vez en el módulo de Usuarios, corregido después) o a suavizar
una eliminación permanente a ámbar, comunicando mal su verdadero impacto.

**`category` es intencionalmente distinto de `accent`**, aunque ambos parten
de la misma familia de neutros apagados: el badge "Transversal" clasifica
un entregable (no advierte nada), mientras que `accent` sigue reservado
para "exento" y cualquier acento futuro cercano a la familia
ámbar/advertencia. Antes de este token, "Transversal" reutilizaba `accent`
y quedaba visualmente casi idéntico a `status-warning` — dos badges de
significado muy distinto (uno descriptivo, otro de alerta) con el mismo
tono, que diluía el ámbar como señal de atención real.

## 3. Tipografía

Una sola familia — **Inter**, de Google Fonts (licencia SIL Open Font
License, según RNF-018) — optimizada para interfaz y lectura de datos, usada
en toda la aplicación (formularios, tablas, navegación, botones). El acento
tipográfico "institucional" que antes llevaba una segunda familia serif ya
no hace falta: el logo real de UTS/Ingeniería de Sistemas es ahora quien
cumple ese papel en el login y el sidebar, así que se retiró Source Serif 4
para no cargar una fuente sin uso real.

| Uso | Tamaño (rem / px) | Peso | Interlineado | Ejemplo |
|---|---|---|---|---|
| Título de página (`.page-title`, h1) | 1.875rem / 30px | 600 (semibold) | 1.3 | "Componentes", "Mis entregables" |
| Subtítulo de sección (h2) | 1.25rem / 20px | 600 (semibold) | 1.3 | "Panel docente", "Panel de coordinación" |
| Título de tarjeta (login) | 1.125rem / 18px | 500 (medium) | 1.3 | "Iniciar sesión" |
| Nombre del sistema (login) | 1.5rem–1.875rem / 24–30px, responsivo | 600 (semibold) | 1.3 | "SIGAE-UTS" bajo el isotipo |
| Bajada del sistema (login) | 0.875rem–1rem / 14–16px, responsivo | 500 (medium) | 1.4 | "Sistema de Información para la Gestión de Actividades y Evidencias Docentes" |
| Número de tarjeta KPI | 1.875rem / 30px | 700 (bold) | 1.2 | "6" en la tarjeta "Pendiente" |
| Cuerpo / tablas (`.table-cell`) | 1rem / 16px | 400 (regular) | 1.5 | Celdas de tabla, texto de tarjetas |
| Etiquetas de formulario (`.field-label`) | 1rem / 16px | 500 (medium) | 1.4 | `<label>` de todos los campos |
| Campos de formulario (input) | 1rem / 16px | 400 (regular) | 1.5 | `<input>`, `<select>`, `<textarea>` — 16px evita el auto-zoom de iOS |
| Ítem de menú del sidebar | 1rem / 16px | 500 (medium) | 1.4 | "Mis entregables", "Periodos" |
| Ayuda / error de campo | 0.875rem / 14px | 400 (regular) | 1.3 | Texto bajo un input (`field-help`, `field-error`) |
| Encabezado de tabla | 0.75rem / 12px | 600 (semibold), mayúsculas | 1.2 | `<th>` de todas las tablas — deliberadamente más pequeño, es un rótulo de categoría, no contenido |
| Grupo de navegación / badge | 0.75rem / 12px | 600 (semibold), mayúsculas en grupos | 1.2 | "SEGUIMIENTO" en el sidebar, chips de estado |

**Nota sobre la escala (revisión posterior a la primera versión del sistema
de diseño):** la primera pasada de este documento fijaba el cuerpo de la
aplicación en 14px y los títulos de página en 24px — técnicamente cumplía
AA, pero con una sensación de interfaz más comprimida de lo esperado para
un sistema institucional. Se subió un escalón completo: 14→16px de cuerpo,
24→30px de título de página, iconografía de 16px→20-24px según el
contexto, y padding interno de tarjetas/filas de tabla aumentado
proporcionalmente. El cambio se hizo en `.page-title`, `.table-cell`,
`.field-label` y las demás clases de `resources/css/app.css`, y en
`<x-kpi-card>` — nunca archivo por archivo — para que se propagara
automáticamente a las ~30 pantallas que ya reutilizaban esas clases.
| Badge / etiqueta pequeña | 0.75rem / 12px | 600 (semibold) | 1 | Badges de estado |

**Nota — dos vistas se quedaron atrás en esa migración:** el detalle de
revisión (`review-show.blade.php`) y el detalle de "Mis entregables"
(`evidence-workspace.blade.php`) usaban `text-sm`/`text-xs uppercase` con
valores fijos en vez de las clases compartidas, así que no recibieron el
salto 14→16px cuando se aplicó al resto de la app. Corregido moviéndolas a
`.form-section-title` (para "Evidencia enviada", "Histórico de
revisiones", "Revisiones del líder", "Historial de versiones") y
`text-base` (para el texto de contenido). Al añadir cualquier vista nueva,
usar siempre las clases compartidas (`.form-section-title`, `.table-cell`,
`text-base` para cuerpo) en vez de utilidades de tamaño sueltas, para que
un futuro ajuste de escala se siga propagando solo.

## 4. Componentes reutilizables

Construidos como componentes Blade (`resources/views/components/`) y clases
de la capa `@layer components` de Tailwind (`resources/css/app.css`), usados
de forma consistente en los 10 módulos funcionales:

- **`<x-status-badge :status="...">`** — badge con ícono + texto + color para
  cualquier enum con métodos `label()`, `color()` e `icon()` (estados de
  evidencia, decisión de revisión, estado de periodo). Garantiza que el
  estado nunca se distinga solo por color.
- **`<x-active-badge :active="...">`** — variante simplificada para el
  campo booleano "activo/inactivo" de los catálogos administrables.
- **`<x-kpi-card :value :label :color>`** — tarjeta de indicador con acento
  de color en el borde izquierdo, usada en los paneles de docente y
  coordinación.
- **`<x-empty-state icon title description>`** — estado vacío con ícono,
  mensaje y sugerencia de próxima acción, en lugar de una fila de tabla en
  gris plano. Se usa en todas las listas del sistema.
- **`<x-icon name="...">`** — 17 íconos SVG de línea (sin dependencias
  externas, sin emojis) para acciones, estados y navegación.
- **Zona de carga de archivos con arrastrar y soltar** (dentro de
  `evidence-workspace.blade.php`) — dropzone con borde discontinuo, estado
  visual al arrastrar (`x-data` de Alpine), barra de progreso durante la
  subida (eventos `livewire-upload-*`) y mensajes de error persistentes bajo
  el campo.
- **Clases de botón** (`.btn-primary`, `.btn-secondary`, `.btn-danger`,
  `.btn-success`, `.btn-warning`, `.btn-text`) — un único vocabulario visual
  de acciones en todo el sistema, en vez de utilidades de Tailwind repetidas
  y potencialmente inconsistentes en cada vista. Regla del degradado
  azul→verde: se reserva exclusivamente para momentos de marca (login,
  correo de recuperación de contraseña); `.btn-primary` en el resto de la
  aplicación (crear, guardar, y cualquier acción cotidiana) es siempre el
  azul de marca sólido, para que el degradado siga funcionando como una
  señal reconocible de "esto es la marca hablando", no como decoración
  repetida en cada botón.
- **Clases de formulario** (`.field-label`, `.field-input`, `.field-help`,
  `.field-error`, `.field-checkbox`, `.field-radio`) — validación en línea
  siempre visible bajo el campo correspondiente, nunca solo en un resumen
  aparte.
- **`.form-section` / `.form-section-title`** — agrupa visualmente un
  formulario largo (5+ campos) en secciones con subtítulo (16px,
  semibold) y un separador sutil (`border-t` + espaciado) entre ellas, sin
  tocar el orden ni el comportamiento de los campos. La primera sección de
  un formulario no lleva separador (ya está al inicio de la tarjeta); cada
  sección siguiente usa `.form-section` en su contenedor. Ejemplo de
  referencia: `deliverable-form.blade.php` (Vinculación, Información
  general, Programación, Configuración de evidencia, Evaluación,
  Destinatarios). Aplica a cualquier formulario de página completa que
  crezca más allá de lo que se escanea de un vistazo — ver también la
  regla modal-vs-página-completa más abajo.
- **Clases de tabla** (`.table-shell`, `.table-header-cell`, `.table-cell`,
  `.table-row`) — tablas de seguimiento responsivas con scroll horizontal
  propio en pantallas angostas.
- **Anchos fijos de columna en listados agrupados en varias tablas**
  ("Mis entregables": una tabla `<table>` independiente por actividad) —
  cada `<table>` usa `w-full table-fixed` + un `<colgroup>` con los mismos
  porcentajes en cada grupo (40/12/16/16/16 para
  Entregable/Obligatorio/Fecha límite/Estado/Acción), para que las
  columnas queden alineadas verticalmente sin importar cuántos grupos
  haya ni qué tan largo sea el contenido de una fila. Con `min-w-full` en
  vez de `w-full`, el navegador deja crecer la tabla para acomodar texto
  largo y el ancho fijo deja de cumplirse — hay que usar `w-full`. La
  columna de texto libre (Entregable) trunca con `truncate` + `title` con
  el texto completo, en vez de dejar que la fila crezca. Esta técnica
  solo aplica cuando los grupos comparten las mismas columnas — los
  informes también agrupan varias tablas, pero cada sección tiene
  encabezados distintos por diseño, así que no sufren este problema y no
  llevan este mismo tratamiento.
- **Sidebar de navegación** (`layouts/app.blade.php`) — agrupado por rol
  (Seguimiento, Gestión académica, Catálogos, Informes, Administración),
  colapsable a un panel lateral en móvil mediante Alpine
  (`x-data="{ mobileOpen: false }"`), en vez de menús desplegables
  horizontales. Ancho fijo (`w-72 shrink-0`, nunca se encoge) frente al
  área de contenido (`flex-1 min-w-0`) — el `min-w-0` en `<main>` es el
  que de verdad importa: sin él, un hijo muy ancho (p. ej. la tabla de
  Auditoría, con una columna "Detalle" que vuelca JSON crudo) puede forzar
  a los ítems flex de todo el layout a crecer para acomodarlo, aunque el
  sidebar tenga un ancho fijo declarado. El scroll horizontal de una tabla
  ancha vive **dentro** de `.table-shell` (`overflow-x-auto` propio), no
  en la página completa.
- **`<x-confirm-modal>`** — modal de confirmación reutilizable (una sola
  instancia global, incluida en `layouts/app.blade.php`) que reemplaza el
  `confirm()` nativo del navegador en toda la aplicación (cerrar/activar/
  archivar/reabrir periodos, desactivar usuarios, eliminar archivos y
  enlaces de evidencia, aprobar/devolver/reabrir evidencia, finalizar un
  liderazgo). Cualquier botón lo dispara con
  `@click="$dispatch('confirm-modal', { title, body, confirmLabel, variant, action: () => $wire.metodo() })"`.
  Usa el mismo patrón visual que los modales de formulario existentes
  (overlay oscuro, tarjeta blanca centrada, sombra), el foco inicial va al
  botón "Cancelar" (no al de confirmar, para que un `Enter` accidental
  nunca ejecute la acción), y se cierra con `Escape` o clic fuera — ambos
  equivalentes a cancelar. Es accesible (`role="dialog"`,
  `aria-modal="true"`, `aria-labelledby` apuntando al título). El color del
  botón de confirmación (`variant`) comunica el impacto real de la acción:
  ámbar para acciones de alto impacto pero reversibles (cerrar un periodo),
  rojo solo para eliminaciones permanentes, verde para acciones que
  habilitan/aprueban — ver la aclaración completa de rojo-vs-ámbar en la
  sección 2 (Paleta de colores), justo debajo de la tabla de tokens.

  **Regla para pares Activar/Desactivar** (catálogos, plantillas,
  usuarios): solo **"Desactivar" pide confirmación** (variante ámbar,
  cuerpo explicando qué deja de estar disponible y qué NO se ve afectado
  retroactivamente). **"Activar" se ejecuta directo**, sin modal, porque
  vuelve el registro a un estado seguro y conocido, no oculta ni bloquea
  nada, y es trivialmente reversible con un segundo clic — pedir
  confirmación ahí solo añadiría fricción sin ganar seguridad real.
  Aplica a Componentes, Subcomponentes, Actividades, Programas,
  Compromisos transversales, Plantillas de entregables y Usuarios.

  **"Marcar como exento" (evidencia) sí exige confirmación con
  justificación obligatoria**, a diferencia del par Activar/Desactivar de
  arriba: no es un simple booleano reversible con un clic, saca al
  docente de su obligación de un entregable y afecta su % de avance —
  variante ámbar, igual que "Cerrar periodo". "Quitar exención" (su
  inverso) sí lleva confirmación pero sin exigir justificación, porque
  deshacer una excepción hacia el estado por defecto es de menor riesgo
  que aplicarla. Restringido a Administrador/Coordinación — un Líder no
  lo tiene: ver docs/manual-tecnico.md §5.7 para la justificación
  completa de por qué esta acción es administrativa y no de revisión de
  pares.
- **Formato de fecha de solo lectura — `$fecha->toReadable()`** — regla
  única para toda fecha mostrada fuera de un input de formulario (tablas,
  tarjetas, informes, badges): día + mes abreviado en texto + año, y solo
  agrega la hora si no es medianoche — `"20 ene 2026"`,
  `"20 abr 2026, 5:00 p. m."`. Los inputs de formulario (datepicker) siguen
  usando `dd/mm/aaaa`, sin cambios — la distinción es "¿el usuario está
  escribiendo/editando la fecha, o solo leyéndola?". El método vive como
  un macro de Carbon (`App\Providers\AppServiceProvider`, ver
  `docs/manual-tecnico.md`), nunca formateado a mano por vista, para que
  no se repita la inconsistencia que motivó esta regla (Periodos y
  Distribución mostraban `dd/mm/aaaa` en tablas mientras Entregables
  estaba por introducir mes-en-texto solo ahí).
- **`<x-search-input>`** — campo de texto con ícono de lupa fijo a la
  izquierda (`<x-icon name="search">`), para que un campo de filtrado se
  reconozca como tal sin depender solo del placeholder. Reenvía cualquier
  atributo (`wire:model[.mod]`, `placeholder`, `class`, etc.) al `<input>`
  real, igual que `<x-password-input>`. Se usa en todo campo de búsqueda de
  una lista (Distribución docente, Usuarios, y cualquier módulo futuro con
  un filtro de texto).
- **`<x-evidence-file-row>` / `<x-evidence-link-row>`** — la fila de un
  archivo o enlace adjunto a una evidencia: ícono (clip o enlace, para
  diferenciarlos) + nombre + tamaño (solo archivos) a la izquierda,
  "Descargar"/"Abrir" a la derecha, y "Quitar" opcional (`:removable`)
  cuando el estado de la evidencia permite edición. Antes, "Mis
  entregables" mostraba el archivo como un enlace de texto suelto sin
  borde mientras que "Revisión" ya usaba la fila con borde — un mismo
  tipo de contenido con dos tratamientos visuales distintos según quién
  lo mira. Ahora ambas vistas usan el mismo componente; cualquier ajuste
  futuro al tratamiento de un archivo/enlace adjunto se hace una sola vez.
- **Sección "Instrucciones"** en el detalle de una evidencia (`review-show`
  y `evidence-workspace`) — Descripción, Instrucciones y Criterio de
  cumplimiento del entregable (los tres opcionales al crearlo), cada uno
  con su propio label, en una tarjeta antes de la de "Fecha límite". Un
  campo vacío simplemente no aparece — nunca una línea con el label y
  nada después. Antes solo se mostraba en "Mis entregables" (parcial: sin
  título de sección) y no aparecía en absoluto en "Revisión".
- **`.section-subtitle`** — párrafo introductorio (16px,
  `text-tertiary` — un gris intermedio entre `text-primary` y
  `text-secondary`, definido a propósito para que se lea como contenido y
  no como metadato) entre el `<h1>` de una sección y su tabla/formulario.
  Se usa **solo** cuando el nombre de la sección no basta para entender
  qué es o cómo se comporta (ej. "¿qué es una plantilla?", "¿de dónde
  salen las horas de Distribución?", "¿por qué Auditoría no se puede
  editar?"). No se agrega a secciones autoexplicativas por su título
  (Periodos, Componentes, Actividades, Programas, Usuarios, Mis
  entregables, informes) — agregar un subtítulo a todo diluiría la señal
  de las secciones que sí lo necesitan.
- **`<x-progress-bar :percentage="...">`** — track + relleno de avance
  (antes solo en el Dashboard, "Consolidado por docente"). No trae alto/
  ancho por defecto: siempre se pasan por `class` (`class="h-1.5 w-20"`),
  para no depender de qué utilidad de Tailwind "gana" cuando dos clases
  de tamaño conviven en el mismo atributo tras un `$attributes->merge()`.
  Usada también en el resumen de "% de avance" de los informes en
  pantalla (`_sections.blade.php`), donde va más grande (`h-2.5 w-full
  max-w-xs`) junto al número en `text-4xl font-bold`, en vez de la caja
  de texto plano anterior — es el dato más importante del informe.
- **`<x-searchable-select>`** — combobox con búsqueda para listas que
  pueden crecer mucho en producción (el docente en "Informe individual
  por docente": todos los docentes activos del sistema, sin acotar por
  periodo). Filtra en el navegador sobre las opciones ya cargadas (no
  pagina contra el servidor); usa `$wire.entangle('propiedad').live`
  para el enlace de datos con Livewire en vez de un `<select>` nativo
  oculto, así el valor queda sincronizado en ambos sentidos (incluida
  la señal externa) sin depender de disparar eventos DOM a mano. Mismo
  ícono de lupa que `<x-search-input>`, más un botón de limpiar y
  navegación por teclado (flechas + Enter + Escape). No reemplaza los
  `<select>` nativos de listas cortas (Periodo, Actividad, Compromiso) —
  solo tiene sentido donde el catálogo puede volverse largo.

### Regla: modal vs. página completa para formularios CRUD

Para que esta decisión no se tome caso por caso en cada módulo nuevo:

- **4 campos o menos → modal** sobre la propia vista de listado (patrón de
  "Periodos académicos": overlay oscuro, tarjeta centrada, cierre con
  Cancelar). Mantiene al usuario en contexto para una edición rápida.
- **5 campos o más → página completa** con su propia ruta (patrón de
  "Distribución docente": "Nueva asignación"/"Editar asignación"). Un modal
  con seis o más campos obliga a hacer scroll dentro de una caja pequeña y
  se siente apretado; una página completa da espacio para agrupar campos,
  mostrar ayudas contextuales (`.field-help`) y, si aplica, un aviso de
  bloqueo como el de periodo cerrado en `assignment-form.blade.php`.

Esta regla ya se cumplía sin haber sido escrita (Periodos usa modal con 2
campos; Distribución usa página completa con 6). Aplica al construir
Líderes, Entregables, Plantillas de entregables y cualquier módulo futuro:
cuenta los campos del formulario antes de decidir el patrón.

### Tablas: alineación numérica y paginación

- Toda columna cuyo contenido sea un número que tenga sentido comparar en
  vertical (horas, porcentajes, conteos) se alinea a la **derecha** tanto en
  `<th>` como en `<td>` (`text-right` sobre `.table-header-cell`/
  `.table-cell`, que por defecto alinean a la izquierda). El resto de
  columnas — texto, fechas, badges — se mantiene alineado a la izquierda.
- **Umbral estándar de paginación — 25 registros por página.** Toda tabla
  de listado cuyo volumen de datos crece con el uso real de la institución
  usa `WithPagination` de Livewire y `->paginate(self::PER_PAGE)` desde el
  primer día, aunque con datos de prueba no se note ningún cambio visual —
  evita tener que retrabajar la vista de listado más adelante. El número
  (25) no se repite a mano en cada componente: vive una sola vez en el
  trait `App\Livewire\Concerns\HasStandardPagination`
  (`protected const PER_PAGE = 25;`), que cada componente paginado incluye
  junto con `WithPagination` (`use HasStandardPagination, WithPagination;`).
  Antes de esto cada módulo traía su propio número sin ningún criterio
  compartido (10, 15 o 25 según el componente) — ya unificados los cinco:
  Usuarios, Auditoría, Distribución docente, Líderes y Entregables.
  Cualquier listado nuevo de alto volumen debe sumar el trait en vez de
  escribir `->paginate(25)` (o cualquier otro número) directamente.
- **Catálogos pequeños: sin paginar, a propósito.** Componentes,
  Subcomponentes, Actividades, Programas, Compromisos transversales,
  Plantillas de entregables y Periodos académicos **no** usan el trait de
  arriba ni paginan — mismo criterio ya aplicado al decidir no agregarles
  `<x-search-input>` (ver más abajo): son catálogos de alto nivel que no
  se espera que superen unas pocas decenas de registros incluso en
  producción real. Si alguno de estos catálogos creciera de forma
  inesperada en el futuro, la salida es sumar `HasStandardPagination`
  (25) o, si el volumen se queda a medio camino, un umbral propio más
  alto (ej. 50) — nunca dejarlo sin paginar "porque siempre fue así".
- La vista de paginación es una sola, compartida por toda la app:
  `resources/views/vendor/livewire/tailwind.blade.php` sobrescribe la
  vista por defecto de **Livewire** (`livewire::tailwind`) — basta con que
  ese archivo exista ahí para que cualquier `{{ $paginador->links() }}`
  de un componente con `WithPagination` la use automáticamente, sin pasar
  un nombre de vista en cada llamada. Va en `vendor/livewire/`, no en
  `vendor/pagination/` (la vista por defecto de Laravel): Livewire
  reemplaza esa configuración en cada componente que usa
  `WithPagination` y apunta en su lugar a su propia vista — como los 5
  listados paginados de esta app son componentes Livewire, no vistas de
  paginación "pura" de Laravel, `vendor/pagination/` no tendría ningún
  efecto real (detalle completo en el comentario del propio archivo).
  Reemplaza el texto en inglés del tema por defecto ("Showing X to Y of Z
  results", "Previous"/"Next") por español ("Mostrando X a Y de Z
  resultados", "Anterior"/"Siguiente") y su estilo gris genérico por los
  tokens del sistema de diseño: página activa en `brand-primary` con
  texto blanco, resto de páginas y flechas con `border-border-subtle` y
  `hover:bg-surface-muted`, igual que cualquier otro control secundario
  de la app.

### Formularios de página completa: ancho centrado

Todo formulario de página completa (los que siguen la regla de "5 campos o
más" arriba, más vistas de detalle de una sola columna como la revisión de
evidencia) usa un contenedor `mx-auto max-w-xl` o `mx-auto max-w-2xl` según
la cantidad de campos, en vez de solo `max-w-*` sin centrar — así la tarjeta
no queda pegada al margen izquierdo dejando un vacío a la derecha en
monitores anchos.

## 5. Por qué esta paleta y tipografía

El azul de marca (`#00447e`) y el verde de marca (`#0a7a45`) no se inventaron:
se extrajeron por muestreo de píxeles del logo real de UTS/Ingeniería de
Sistemas, para que la interfaz se sienta una extensión del mismo sistema
visual, no una paleta genérica "azul corporativo" elegida sin relación con
la marca. El verde se oscureció respecto al tono más frecuente del logo
(`#00a859`) porque a esa luminosidad no cumplía el contraste AA mínimo como
texto o como fondo de botón con texto blanco; se buscó el punto más claro
posible sobre esa restricción, sin desviarse del matiz real. Los colores de
estado (verde de "aprobado", ámbar, rojo) se mantienen con una saturación
contenida similar a la de marca, de modo que ningún estado "grite" más que
otro — una evidencia vencida se nota por su ícono y su texto, no por ser el
color más agresivo de la pantalla. Los grises son fríos (con un ligero
matiz azulado) en vez de neutros puros, para que toda la interfaz —
superficies, bordes, texto secundario— se sienta parte de la misma familia
cromática que el azul de marca.

Vale la pena dejar constancia de una tensión que surgió al hacer esta
extracción: el verde real del logo, una vez oscurecido lo suficiente para
ser accesible, cae en un matiz casi idéntico al que el sistema ya usaba para
el estado "Aprobado" (`#1e7a4c`). Se decidió mantener el verde fiel al logo
en vez de forzarlo hacia un matiz más distintivo, porque ambos colores viven
en contextos visualmente distintos — uno es un gesto de marca en el login
(degradado de botón, una etiqueta de texto), el otro siempre aparece como
badge con ícono y palabra ("Aprobado") en el resto de la aplicación — así
que el riesgo real de que alguien confunda una cosa con la otra es bajo.

Inter se eligió porque es una tipografía diseñada específicamente para
interfaces de pantalla y datos tabulares: sus cifras tabulares y su
legibilidad a 14px la hacen apropiada para las tablas de seguimiento que
un líder o coordinador revisa docenas de veces por sesión. Con el logo real
ya integrado, ya no hace falta una segunda tipografía "de identidad" — el
logo mismo lleva el peso de la marca en el login y el sidebar, y el resto
de la interfaz puede permanecer enteramente funcional en una sola familia,
sin la carga (literal, de descarga; y de mantenimiento) de una fuente
adicional cuyo único uso era un título que ahora es una imagen.

## 6. Estado de la identidad visual

El logo institucional real (isotipo hexagonal azul/verde, sin wordmark) ya
está integrado:

- **Login** (`layouts/guest.blade.php`): usa `public/images/logo/logo-full.png`
  (isotipo, 1600×1600) como protagonista, grande y responsivo
  (112px de alto en móvil → 160px en escritorio), sin contenedor ni
  rotación. Como el archivo ya no trae wordmark ni bajada de texto
  incorporados, el nombre y la descripción del sistema se muestran como
  texto aparte debajo del isotipo — "SIGAE-UTS" (24–30px, semibold) y
  "Sistema de Información para la Gestión de Actividades y Evidencias
  Docentes" (14–16px, en `brand-secondary`) — ambos también escalando con
  el tamaño de pantalla para mantener protagonismo en cualquier
  dispositivo.
- **Sidebar / drawer móvil / barra superior móvil** (`layouts/app.blade.php`):
  usan `public/images/logo/logo-mark.png` (el mismo isotipo) junto al texto
  "SIGAE-UTS", a tamaño de ícono (28–32px).
- **Favicon**: generado en 5 tamaños (`public/favicon-{16,32,180,192,512}.png`)
  a partir de `public/images/logo/favicon-source.png`.

Ambos layouts detectan los archivos por `file_exists()` y prefieren `.svg`
sobre `.png` si algún día se entrega una versión vectorial con el mismo
nombre — no haría falta tocar las vistas otra vez.

Los tokens `brand-primary` (`#00447e`) y `brand-secondary` (`#0a7a45`) en
`resources/css/app.css` ya no son una aproximación: se extrajeron por
muestreo de píxeles de este logo (ver sección 5 para el detalle del ajuste
de contraste sobre el verde). El placeholder tipográfico anterior
("SIGAE-UTS" en Source Serif 4) se retiró junto con esa fuente, ya
innecesaria.

- **Informes exportables (PDF/Excel)**: también aplican esta identidad —
  letterhead con el mismo logo, colores de marca, colores de estado
  coherentes con `<x-status-badge>` y jerarquía tipográfica equivalente
  a la de la web. Al no poder usar Inter (DomPDF necesita archivos
  `.ttf` locales que este proyecto no distribuye) ni leer los tokens
  `@theme` de Tailwind (ni DomPDF ni PhpSpreadsheet entienden CSS), esa
  paleta y esa sustitución tipográfica (Helvetica) están duplicadas a
  propósito en `App\Services\Reports\ReportTheme` — detalle técnico
  completo en `manual-tecnico.md` §5.10.
