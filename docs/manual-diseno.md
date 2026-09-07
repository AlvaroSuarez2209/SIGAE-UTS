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
| `brand-primary` | `#1d4e89` | Color de marca principal | Botones primarios, enlaces, encabezado del sidebar, fondo del login |
| `brand-primary-dark` | `#123252` | Marca, variante oscura | Hover de botones primarios, fondo del panel de login |
| `brand-primary-subtle` | `#eaf1f8` | Marca, fondo suave | Fondo de enlace activo en el sidebar, badges "primary" |
| `brand-secondary` | `#0e7a5f` | Marca, segundo tono del logo (verde) | Línea "UTS" y extremo del degradado del botón en el login |
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

Dos familias, ambas de Google Fonts (licencia SIL Open Font License, según
RNF-018), optimizadas para interfaz y lectura de datos:

- **Inter** — familia principal de toda la interfaz (formularios, tablas,
  navegación, botones).
- **Source Serif 4** — únicamente para el título "SIGAE-UTS" en la pantalla
  de login y el encabezado del sidebar, como único acento tipográfico que
  distingue la identidad institucional del resto de la interfaz funcional.

| Uso | Tamaño (rem / px) | Peso | Interlineado | Ejemplo |
|---|---|---|---|---|
| Título de página (h1) | 1.5rem / 24px | 600 (semibold) | 1.3 | "Componentes", "Mis entregables" |
| Subtítulo de sección (h2) | 1.125rem / 18px | 600 (semibold) | 1.3 | "Panel docente", "Histórico de revisiones" |
| Título de login/marca | 1.875rem / 30px | 600 (semibold, Source Serif 4) | 1.2 | "SIGAE-UTS" en login |
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

La paleta se construyó alrededor de un azul institucional (`#1d4e89`) de
saturación media-baja porque es el registro cromático que los usuarios
colombianos asocian con instituciones educativas y entidades públicas
serias, sin caer en el azul corporativo genérico de plantillas SaaS. Los
colores de estado (verde, ámbar, rojo) se eligieron con la misma saturación
contenida que el azul de marca, de modo que ningún estado "grite" más que
otro — una evidencia vencida se nota por su ícono y su texto, no por ser el
color más agresivo de la pantalla. Los grises son fríos (con un ligero
matiz azulado) en vez de neutros puros, para que toda la interfaz —
superficies, bordes, texto secundario— se sienta parte de la misma familia
cromática que el azul de marca.

Inter se eligió porque es una tipografía diseñada específicamente para
interfaces de pantalla y datos tabulares: sus cifras tabulares y su
legibilidad a 14px la hacen apropiada para las tablas de seguimiento que
un líder o coordinador revisa docenas de veces por sesión. Source Serif 4
se reserva exclusivamente para el nombre del sistema en el login y el
sidebar — una serif seria y editorial, no decorativa — como el único gesto
tipográfico "institucional" en un sistema que, por lo demás, es
deliberadamente funcional. Esta separación (una tipografía para identidad,
otra para trabajo) refuerza la seriedad de la marca sin sacrificar la
velocidad de lectura en el uso diario, que es, en última instancia, el
criterio que más importa para un sistema que un docente debe poder usar
sin fricción entre clase y clase.

## 6. Estado de la identidad visual

Al momento de escribir este manual, el sistema usa un isotipo placeholder
(cuadrado con degradado `brand-primary` → `brand-secondary`, levemente
rotado) en lugar del logo institucional real, tanto en el login como en el
sidebar. La carpeta `public/images/logo/` está preparada para recibir
`logo-full.svg` (login) y `logo-mark.svg` (sidebar) — deben ir en `public/`,
no en `resources/`, porque son archivos estáticos que el navegador pide
directamente por URL y Vite solo procesa `app.css`/`app.js`. El login ya
detecta automáticamente si `logo-full.svg` existe y lo muestra en su lugar.
`public/favicon.svg` es un monograma provisional en `brand-primary` que se
reemplazará por los tamaños generados a partir de
`public/images/logo/favicon-source.png` cuando UTS entregue el logo
oficial. Ver `public/images/logo/README.md` para el detalle de formatos y
dimensiones esperadas.

`brand-secondary` (`#0e7a5f`, verde) es, junto con `brand-primary`, una
aproximación provisional de los dos tonos del logo real; ambos quedan como
tokens de Tailwind reutilizables en `resources/css/app.css` para que, al
recibir el logo, solo haga falta ajustar estos dos valores hexadecimales.
