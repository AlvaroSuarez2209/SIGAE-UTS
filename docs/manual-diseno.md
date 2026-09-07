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
| `accent` | `#8a6516` | Acento (ámbar apagado) | Badge de evidencias "exento", distintivo de entregables transversales |
| `accent-subtle` | `#f6efdf` | Acento, fondo suave | Fondo de los badges de acento |
| `status-success` | `#1e7a4c` | Éxito / aprobado | Badge "Aprobado", botón "Aprobar" en revisión |
| `status-success-subtle` | `#e8f5ee` | Éxito, fondo suave | Fondo de mensajes de confirmación |
| `status-warning` | `#9a5b12` | Advertencia | Badge "Requiere ajustes" / "Cerrado", botón "Devolver" |
| `status-warning-subtle` | `#fbf1e4` | Advertencia, fondo suave | Fondo de avisos (periodo bloqueado, etc.) |
| `status-error` | `#b3261e` | Error / vencido | Badge "Vencido", botones destructivos |
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
| Título de página (h1) | 1.5rem / 24px | 600 (semibold) | 1.3 | "Componentes", "Mis entregables" |
| Subtítulo de sección (h2) | 1.125rem / 18px | 600 (semibold) | 1.3 | "Panel docente", "Histórico de revisiones" |
| Título de tarjeta (login) | 1.125rem / 18px | 500 (medium) | 1.3 | "Iniciar sesión" |
| Nombre del sistema (login) | 1.5rem–1.875rem / 24–30px, responsivo | 600 (semibold) | 1.3 | "SIGAE-UTS" bajo el isotipo |
| Bajada del sistema (login) | 0.875rem–1rem / 14–16px, responsivo | 500 (medium) | 1.4 | "Sistema de Información para la Gestión de Actividades y Evidencias Docentes" |
| Cuerpo / tablas | 0.875rem / 14px | 400 (regular) | 1.5 | Celdas de tabla, texto de tarjetas |
| Etiquetas de formulario | 0.875rem / 14px | 500 (medium) | 1.4 | `<label>` de todos los campos |
| Campos de formulario (input) | 1rem / 16px | 400 (regular) | 1.5 | `<input>`, `<select>`, `<textarea>` — 16px evita el auto-zoom de iOS |
| Ayuda / error de campo | 0.8125rem / 13px | 400 (regular) | 1.3 | Texto bajo un input (`field-help`, `field-error`) |
| Encabezado de tabla | 0.75rem / 12px | 600 (semibold), mayúsculas | 1.2 | `<th>` de todas las tablas |
| Badge / etiqueta pequeña | 0.75rem / 12px | 600 (semibold) | 1 | Badges de estado |

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
  y potencialmente inconsistentes en cada vista.
- **Clases de formulario** (`.field-label`, `.field-input`, `.field-help`,
  `.field-error`, `.field-checkbox`, `.field-radio`) — validación en línea
  siempre visible bajo el campo correspondiente, nunca solo en un resumen
  aparte.
- **Clases de tabla** (`.table-shell`, `.table-header-cell`, `.table-cell`,
  `.table-row`) — tablas de seguimiento responsivas con scroll horizontal
  propio en pantallas angostas.
- **Sidebar de navegación** (`layouts/app.blade.php`) — agrupado por rol
  (Seguimiento, Gestión académica, Catálogos, Informes, Administración),
  colapsable a un panel lateral en móvil mediante Alpine
  (`x-data="{ mobileOpen: false }"`), en vez de menús desplegables
  horizontales.

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
