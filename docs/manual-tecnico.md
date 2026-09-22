# Manual técnico — SIGAE-UTS

## 1. Resumen del stack

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.2+ (desarrollado y probado con 8.3) + Laravel 12 |
| Frontend | Blade + Livewire 4 (componentes de clase) + Alpine.js (incluido con Livewire) + Tailwind CSS 4 |
| Base de datos | PostgreSQL 17 |
| Autenticación | Local (Laravel `Auth`, hash bcrypt) |
| Reportes PDF | `barryvdh/laravel-dompdf` |
| Reportes Excel | `maatwebsite/excel` v4 |
| Pruebas | PHPUnit 11 |
| Control de versiones | Git |

Todo corre **100% en local**: sin despliegue institucional, sin SSO, sin
integraciones externas. El almacenamiento de archivos es el disco local
de Laravel (`storage/app/private`, sin URL pública).

## 2. Requisitos previos

- PHP 8.2+ (`composer.json` fija `^8.2`; el proyecto se desarrolló y
  probó con 8.3) con las extensiones: `pdo_pgsql`, `pgsql`, `mbstring`,
  `openssl`, `curl`, `fileinfo`, `gd`, `intl`, `zip`.
- Composer 2.x
- Node.js 20+ y npm (Tailwind CSS 4 requiere Node 20 o superior para compilar)
- PostgreSQL 17 (u otra versión 13+) corriendo localmente

En Windows, la forma más simple de tener PHP + Composer es
[Laragon](https://laragon.org/) (`winget install LeNgocKhoa.Laragon`).
PostgreSQL se instala aparte (`winget install PostgreSQL.PostgreSQL.17`),
ya que la versión WAMP de Laragon no lo incluye.

**Nota Laragon:** si PHP no carga extensiones (`php -m` sale casi vacío),
falta copiar `php.ini-development` a `php.ini` dentro de la carpeta de
PHP de Laragon y descomentar (`extension=...`) las extensiones listadas
arriba, además de fijar `extension_dir` a la ruta absoluta de la carpeta
`ext` — Laragon normalmente hace esto al abrir su interfaz gráfica al
menos una vez, pero una instalación silenciosa (por línea de comandos)
no lo hace automáticamente.

## 3. Instalación paso a paso

```bash
# 1. Clonar el repositorio y entrar a la carpeta
git clone <url-del-repositorio> SIGAE-UTS
cd SIGAE-UTS

# 2. Instalar dependencias PHP y JS
composer install
npm install

# 3. Copiar el archivo de entorno y generar la clave de la aplicación
cp .env.example .env
php artisan key:generate

# 4. Editar .env con las credenciales reales de PostgreSQL local
#    DB_CONNECTION=pgsql
#    DB_HOST=127.0.0.1
#    DB_PORT=5432
#    DB_DATABASE=sigae_uts
#    DB_USERNAME=postgres
#    DB_PASSWORD=<tu contraseña>

# 5. Crear la base de datos (una vez, con el cliente de Postgres que prefieras)
createdb -U postgres sigae_uts

# 6. Migrar el esquema y sembrar datos de demostración
php artisan migrate --seed

# 7. Compilar los assets de Tailwind/Vite
npm run build

# 8. Levantar el servidor de desarrollo
php artisan serve
```

La aplicación queda disponible en `http://127.0.0.1:8000`.

### Base de datos de pruebas automatizadas

Las pruebas (`php artisan test`) usan una base de datos **separada** de
la de desarrollo, definida en `.env.testing` (no se versiona — usar
`.env.testing.example` como plantilla):

```bash
createdb -U postgres sigae_uts_testing
cp .env.testing.example .env.testing   # y completar la contraseña
php artisan test
```

Esto evita que correr las pruebas borre los datos con los que se está
navegando la aplicación manualmente (`RefreshDatabase` migra y limpia
esta base en cada ejecución).

### Correo local (recuperación de contraseña)

El sistema envía un correo real cuando un usuario pide restablecer su
contraseña (pantalla "¿Olvidó su contraseña?"). En local, ese correo se
entrega a **Mailpit** — un servidor SMTP + bandeja web que ya viene
incluido con Laragon, sin salir a internet ni depender de credenciales de
un proveedor externo (Gmail, etc.):

```bash
# Laragon ya lo trae; solo hace falta arrancarlo (una vez, mientras se use):
"C:\laragon\bin\mailpit\<version>\mailpit.exe"

# o, si Laragon lo integra en su propio menú, actívalo desde ahí
# (ícono "Mail" en la barra lateral de Laragon).
```

Con Mailpit corriendo, cualquier correo que la aplicación envíe (con la
configuración por defecto de `.env.example`: `MAIL_MAILER=smtp`,
`MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`) aparece de inmediato en
**http://localhost:8025** — se puede abrir el correo, ver el HTML
renderizado tal cual llegaría a un cliente real, y hacer clic en el
enlace de restablecimiento directamente desde ahí.

Si Mailpit no está corriendo, `php artisan serve` sigue funcionando con
normalidad — simplemente el envío del correo fallará silenciosamente en
segundo plano (no bloquea la respuesta al usuario) y no habrá dónde verlo.

**Importante para un futuro despliegue real:** esta configuración es solo
para desarrollo local. En un servidor de producción, `MAIL_MAILER` y las
credenciales deben apuntar a un proveedor real (un relay SMTP
institucional de UTS, o un servicio transaccional como Amazon SES,
Mailgun o Postmark con el dominio de UTS verificado) — nunca a Mailpit ni
a una cuenta de Gmail personal. El cambio es puramente de `.env`; el
código de la aplicación no cambia entre entornos.

## 4. Usuarios de prueba (sembrados por `UserSeeder`)

Todos con contraseña `password`:

| Correo | Rol(es) | Notas |
|---|---|---|
| `admin@sigae.local` | Administrador | |
| `coordinacion@sigae.local` | Coordinación | |
| `lider1@sigae.local` | Líder + Docente | Líder de todo el programa y, además, de la actividad "Dirección de trabajos de grado" |
| `docente1@sigae.local` | Docente | Tiene la evidencia de demostración (devuelta → aprobada) |
| `docente2@sigae.local` | Docente | |
| `docente3@sigae.local` | Docente | **Cuenta inactiva** — para probar el bloqueo de acceso |
| `auditor@sigae.local` | Auditor | Solo consulta (informes, revisión de solo lectura) |

El escenario obligatorio de demostración (componentes/docentes/líderes
variados, una actividad de 5 horas con una cantidad de entregables
distinta de 5, un compromiso transversal, y una evidencia que pasa por
devolución y aprobación) queda sembrado automáticamente por
`database/seeders/DatabaseSeeder.php`.

## 5. Arquitectura

### 5.1 Patrón general

Monolito Laravel + Livewire: no hay API REST ni SPA separada. Cada
pantalla es un componente Livewire de clase (`app/Livewire/**`) con su
vista Blade correspondiente (`resources/views/livewire/**`), montado en
una ruta de página completa (`routes/web.php`).

### 5.2 Autorización

- **Roles**: tabla `roles` + pivote `role_user` (un usuario puede tener
  varios). Se consultan con `User::hasRole()` / `hasAnyRole()`.
- **Middleware de ruta**: alias `role:administrator,coordination,...`
  (`App\Http\Middleware\EnsureHasRole`) para restringir secciones enteras.
- **Policies** (`app/Policies/**`) para autorización a nivel de registro
  (ej. un docente solo edita su propia evidencia; un líder solo revisa
  dentro de su ámbito vía `Evidence::isReviewableBy()`).
- **`EnsureAccountIsActive`**: cierra la sesión automáticamente si la
  cuenta del usuario autenticado fue desactivada.

### 5.3 Reglas de negocio centralizadas

- `App\Services\ComplianceCalculator`: calcula el % de avance
  **siempre** sobre entregables obligatorios (con o sin ponderación),
  **nunca** sobre horas asignadas. La usan el Dashboard (módulo 8) y los
  informes (módulo 9).
- `App\Services\Reports\ReportBuilder`: arma los 4 informes mínimos como
  una única estructura (`title`, `sections`, `summary`) que alimenta la
  vista en pantalla, el PDF y el Excel sin triplicar la consulta.
- `App\Models\Evidence::startOrGetDraftVersion()` /
  `submitCurrentVersion()`: implementan el versionado (cada ajuste tras
  una devolución genera una versión nueva; la versión enviada queda
  protegida).
- `App\Models\Concerns\Auditable` (trait): registra automáticamente
  creación/edición en los modelos administrativos en `audit_logs`, sin
  instrumentar cada componente a mano. Se desactiva únicamente durante
  `db:seed` (para no llenar la bitácora de datos de demostración).

### 5.4 Almacenamiento de archivos

Disco `local` de Laravel (`storage/app/private`, sin symlink público).
Cada archivo se guarda con un nombre aleatorio (`stored_name`); el
nombre original se conserva aparte para mostrarlo al usuario. Las
descargas pasan siempre por `EvidenceFileDownloadController`, que
verifica permisos en cada solicitud vía `EvidenceFilePolicy`.

### 5.5 Zona horaria

**Toda la aplicación corre en una sola zona horaria: `America/Bogota`
(UTC-5, sin horario de verano).** No se guarda en UTC para convertir
después en la vista — se decidió así, y no al revés, por dos razones
concretas de este proyecto:

1. **Las columnas de fecha/hora del esquema son `timestamp`/`dateTime`
   sin zona horaria** (`$table->timestamps()`, `dateTime('due_at')`,
   etc. en `database/migrations/**`). PostgreSQL no les aplica ninguna
   conversión de zona horaria al leer ni al escribir, sin importar la
   zona horaria de la sesión — guardan literalmente el valor de reloj de
   pared que reciben. Adoptar "guardar en UTC, mostrar en Bogotá"
   correctamente habría requerido convertir cada una de esas columnas a
   `timestamptz` (~12 tablas) para que Postgres sí hiciera la conversión,
   más mantener sincronizadas la zona horaria de la app y la de la sesión
   de base de datos en direcciones opuestas — mucho más superficie de
   error para un sistema de una sola institución.
2. **SIGAE-UTS es, por alcance, de una sola zona horaria**: todos los
   docentes, líderes, coordinación y administración de UTS operan desde
   Colombia. No hay (ni está previsto) un usuario en otro huso horario
   cuya hora local difiera de la institucional.

Con eso resuelto, la corrección es una sola línea:
`config('app.timezone')` en `config/app.php` es `'America/Bogota'` (antes
`'UTC'`, el valor por defecto de Laravel). Laravel aplica esto vía
`date_default_timezone_set()` muy temprano en el arranque de cada
petición, así que **todo** `now()` / `Carbon::now()` / `today()` de la
aplicación —fechas de auditoría (`Auditable`), vigencia de liderazgo
(`starts_at`/`ends_at`), envío y revisión de evidencias
(`submitted_at`, `decided_at`), y las comparaciones de "próximos
vencimientos" del dashboard (`due_at->between(now(), ...)`) — queda
corregido desde un único punto, sin parches por archivo. Como las
columnas son sin zona horaria, lo que se escribe y lo que se lee de
vuelta es exactamente la misma hora de pared en Bogotá: no hay
conversión posible que se desalinee entre sí.

Por defensa en profundidad, `config/database.php` también fija la zona
horaria de la *sesión* de PostgreSQL en `America/Bogota` (opción
`timezone` de la conexión `pgsql`, que Laravel traduce a
`SET TIME ZONE`). Esto no afecta a las columnas propias de la
aplicación (son sin zona horaria, como se explicó arriba), pero sí
importa para cualquier valor que la propia base de datos genere -en vez
de PHP- por su cuenta (por ejemplo, el `useCurrent()` de
`failed_jobs.failed_at`, una tabla interna del framework).

**Nota para datos anteriores a este cambio:** los registros creados
mientras la app corría en UTC (auditoría, fechas de liderazgo, etc.)
quedaron guardados con la hora de reloj UTC de ese momento, 5 horas
adelantada respecto a Bogotá — este fix corrige el comportamiento hacia
adelante, no reescribe el histórico ya guardado.

**Si el sistema alguna vez necesita usuarios en otra zona horaria**, la
ruta de migración es: (1) convertir las columnas de fecha/hora
relevantes de `timestamp`/`dateTime` a `timestamptz`, (2) fijar
`config('app.timezone')` de vuelta a `'UTC'` (y la zona horaria de la
sesión de Postgres también a `'UTC'`, para que el `timestamptz` guarde
siempre en UTC internamente), y (3) convertir a la zona horaria de cada
usuario únicamente en la capa de presentación, con
`Carbon::setTimezone($usuario->timezone)` justo antes de mostrar la
fecha.

### 5.6 Formato de fechas de solo lectura

Regla única para toda la aplicación: cualquier fecha mostrada en modo de
solo lectura (tabla, tarjeta, badge, informe) usa
**`$fecha->toReadable()`** — nunca `->format('d/m/Y...')` a mano en una
vista. Devuelve día + mes abreviado en español + año, y agrega la hora
solo si no es medianoche:

```php
$period->start_date->toReadable();      // "20 ene 2026"
$deliverable->due_at->toReadable();     // "20 abr 2026, 5:00 p. m." (si la hora no es 00:00)
```

Los inputs de formulario (datepicker, `dd/mm/aaaa`) no usan esto — siguen
su propio formato de edición, sin cambios.

**Implementación**: un macro de Carbon registrado una sola vez en
`App\Providers\AppServiceProvider::boot()` (método privado
`registerReadableDateMacro()`), sobre `\Carbon\Carbon` y
`\Carbon\CarbonImmutable`, así que cualquier fecha casteada por Eloquent
(`'date'`/`'datetime'`) en cualquier modelo obtiene el método
automáticamente, sin tocar cada modelo. Por dentro usa
`isoFormat('D MMM YYYY')` con locale `es` explícito (independiente de
`config('app.locale')`) y le quita el punto que CLDU pone tras el mes
abreviado ("ene." → "ene"), para calzar exactamente con el formato
acordado.

**Por qué un macro y no un Accessor por modelo**: un Accessor viviría
repetido en cada modelo con fechas (`AcademicPeriod`, `Leadership`,
`Deliverable`, `EvidenceVersion`, `Review`, `AuditLog`, ...) y un
desarrollador nuevo tendría que acordarse de añadirlo en cada modelo
futuro. El macro se registra una vez y queda disponible en toda fecha de
la aplicación —incluidas las que no vienen de un modelo, como
`now()->toReadable()` en el pie del PDF de informes— sin que nadie tenga
que recordarlo.

**Al añadir una fecha de solo lectura a una vista nueva**: usa
`->toReadable()` directamente; no la reintroduzcas con `->format(...)`.
Si hace falta un formato genuinamente distinto para un caso puntual (poco
común), decídelo explícitamente en esa vista en vez de generalizar el
macro con parámetros no usados en ningún otro lugar.

Mismo principio para el tamaño de un archivo: `EvidenceFile::readable_size`
(accessor `Attribute` en `app/Models/EvidenceFile.php`) convierte
`size_bytes` a `"245 KB"`/`"1.2 MB"` en un único punto — cualquier vista
que liste archivos adjuntos (bandeja de revisión, "Mis entregables") usa
`$file->readable_size`, nunca una conversión de bytes hecha a mano.

### 5.7 Vencimiento automático y exención de evidencias

Los estados `Vencido` y `Exento` de `EvidenceStatus` tienen cada uno un
flujo real y auditado — nunca se editan a mano fuera de estos dos puntos:

**Vencimiento automático** (`App\Console\Commands\MarkOverdueEvidences`,
comando `evidences:mark-overdue`):

- Selecciona toda evidencia en estado `Pendiente` o `Borrador` cuyo
  entregable ya pasó su `due_at`, y la transiciona a `Vencido`. No toca
  `Enviado`, `Requiere ajustes`, `Aprobado` ni `Exento` — esos estados ya
  significan que el docente actuó o que la evidencia ya no aplica.
- La comparación `due_at < now()` es válida sin ninguna conversión de
  zona horaria porque toda la app corre en una sola zona
  (`America/Bogota`, ver 5.5) y `due_at` se guarda como esa misma hora de
  pared.
- **Registrado en el scheduler de Laravel** (`routes/console.php`,
  `Schedule::command('evidences:mark-overdue')->dailyAt('01:00')`), pero
  el scheduler de Laravel **no hace nada por sí solo**: el servidor
  (producción o el Laragon local, si se quiere que corra de verdad todos
  los días) necesita el cron del sistema operativo apuntando a
  `schedule:run` cada minuto:

  ```
  * * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
  ```

  Sin esa línea de cron configurada, el comando existe y puede ejecutarse
  a mano (`php artisan evidences:mark-overdue`), pero nunca se dispara
  solo. En Windows/Laragon no hay cron nativo — para probarlo en local
  simplemente se ejecuta el comando a mano cuando haga falta; para que
  corra de verdad a diario en un despliegue real (Linux), es
  responsabilidad de quien despliegue configurar esa línea de cron o su
  equivalente (Supervisor, una tarea programada de Windows, etc.).
- Auditoría: cada transición llama a `AuditLog::record('evidence_marked_overdue', ...)`
  explícitamente. Como el comando corre en consola, `Auditable` (el trait
  que audita automáticamente cada `update()`) se autodesactiva —
  `auditingDisabled()` es `true` fuera de pruebas cuando
  `app()->runningInConsole()` — así que esta es la ÚNICA entrada que
  queda, con `user_id = null` (se ve como **"Sistema"** en la bitácora,
  gracias a `$log->user->name ?? 'Sistema'` que ya existía para
  `login_failed`): dejar `user_id` en null es exactamente lo que
  distingue "lo hizo el scheduler" de "lo hizo una persona".

**Exención manual** (`Evidence::markExempt()` / `Evidence::removeExemption()`,
expuestas en `EvidenceWorkspace` y la vista `evidence-workspace.blade.php`):

- Restringida a Administrador y Coordinación
  (`EvidencePolicy::markExempt`/`removeExemption`) — **deliberadamente
  sin Líder**. Aprobar/devolver es una decisión de revisión de contenido
  dentro de un ámbito de liderazgo; eximir es una decisión administrativa
  institucional (licencia, reasignación, etc.) que no depende de qué
  actividad lidera alguien. Es más restrictivo que "Aprobar/Devolver"
  pero menos que "Reabrir" (solo Administrador) — un punto intermedio
  consciente, documentado también en `docs/manual-diseno.md`.
- No se puede eximir una evidencia `Aprobada` (el resultado ya es
  definitivo) ni una ya `Exenta` (para eso está `removeExemption`).
- Exige una justificación (`exemptionJustification`, obligatoria) y pasa
  por `<x-confirm-modal>` antes de aplicarse — mismo patrón que "Cerrar
  periodo" o "Finalizar liderazgo".
- Auditoría: además de la entrada automática de `Auditable` (que registra
  el cambio de `status` pero no el porqué), `Evidence::markExempt()`
  añade explícitamente `AuditLog::record('evidence_marked_exempt', ...)`
  con la justificación y el estado anterior — dos entradas para una sola
  acción es intencional, no una duplicación accidental: una es el diff
  automático de campo, la otra es el evento de negocio con su motivo.
  `removeExemption()` añade `evidence_exemption_removed`. Quitar una
  exención nunca es "editar el campo status" — siempre pasa por este
  método, así queda su propio rastro.
- **Alcanzable desde "Entregables"**: la columna "Destinatarios" ahora
  enlaza a una vista nueva (`DeliverableRecipients`,
  `/deliverables/{deliverable}/recipients`) que lista cada destinatario
  con su estado y un enlace "Ver evidencia" hacia
  `evidence-workspace.blade.php` — la única forma de que Administración/
  Coordinación llegue a una evidencia que nunca pasó por la bandeja de
  revisión (pendiente, borrador, vencida), ya que `reviews.index` solo
  lista evidencia `Enviada`.

### 5.8 Modal de creación/edición reutilizable (catálogos y Periodos)

Componentes, Subcomponentes, Actividades, Programas, Compromisos
transversales y Periodos académicos comparten el mismo patrón de modal
(no el componente `<x-confirm-modal>` de las confirmaciones — este es
más simple, un `<div>` mostrado con `@if ($showModal)` del lado del
servidor, sin `x-show`/`x-cloak`). Cualquier catálogo nuevo con este
mismo patrón debe implementar exactamente estos tres métodos:

```php
public function openCreate(): void
{
    $this->reset([...campos del formulario..., 'editing']);
    $this->resetValidation();
    $this->showModal = true;
}

public function openEdit(Modelo $registro): void
{
    $this->resetValidation();   // antes de precargar los campos
    $this->editing = $registro;
    // ...asignar cada campo desde $registro...
    $this->showModal = true;
}

public function closeModal(): void
{
    $this->reset([...campos del formulario..., 'editing']);
    $this->resetValidation();
    $this->showModal = false;
}
```

`closeModal()` es el único punto de cierre — lo llaman "Cancelar"
(`wire:click="closeModal"`), el clic fuera de la tarjeta
(`@click.outside="$wire.closeModal()"` en el `<div class="card">`) y
Escape (`x-on:keydown.escape.window="$wire.closeModal()"` en el overlay).
El overlay lleva además `role="dialog"`, `aria-modal="true"` y
`aria-labelledby` apuntando al `<h2>` del título, igual que
`<x-confirm-modal>`.

**Por qué los tres `resetValidation()`, no solo uno:** `$this->reset()`
únicamente restaura el *valor* de las propiedades públicas — nunca toca
el error bag de la validación. Sin este fix, un intento de "Nuevo" con
campos vacíos (que genera errores) seguido de "Cancelar" y luego
"Editar" sobre un registro válido mostraba los mismos mensajes de error
de la validación anterior bajo campos que sí tenían datos correctos —
bug real encontrado y corregido en los seis módulos de este patrón.
Detalle completo en la memoria del proyecto
(`project_livewire4_gotchas.md`, sección sobre `reset()` y el error bag).

### 5.9 Estructura de carpetas relevantes

```
app/
  Enums/                  Enums de estado (RoleName, EvidenceStatus, ReviewDecision, ...)
  Exports/                Clases de exportación a Excel (Laravel Excel)
  Http/Controllers/       Solo los controladores no-Livewire (login/logout, descargas, exportaciones)
  Livewire/               Un subdirectorio por módulo (Auth, Admin, Catalogs, Deliverables,
                          Distribution, Evidence, Leaderships, Periods, Reports, Reviews, Audit)
  Models/                 Modelos Eloquent, incluida la carpeta Concerns/ (trait Auditable)
  Policies/               Autorización por registro
  Services/               Lógica de negocio reutilizable (ComplianceCalculator, ReportBuilder)
database/
  migrations/             Una migración por tabla, en inglés
  seeders/                Separados por módulo, encadenados desde DatabaseSeeder
  factories/              Para pruebas automatizadas
resources/views/
  layouts/                app.blade.php (autenticado) y guest.blade.php (login)
  livewire/               Una vista por componente, misma estructura que app/Livewire
  reports/pdf/            Plantilla HTML compartida para los PDF (DomPDF)
docs/                     Este manual, el diccionario de datos y el manual de usuario
tests/
  Feature/                Un subdirectorio por módulo
  Unit/                   Pruebas aisladas de servicios (ComplianceCalculator, ReportBuilder)
```

### 5.10 Identidad visual de los informes exportables (PDF y Excel)

Los 4 informes (Individual por docente, Por actividad, Compromisos
transversales, Consolidado por periodo) comparten un único punto de
formato: `App\Services\Reports\ReportTheme`. Ni DomPDF ni PhpSpreadsheet
pueden leer los tokens `@theme` de Tailwind (`resources/css/app.css`),
así que esta clase **duplica a propósito** los colores de marca y el
mapeo tono→color de `<x-status-badge>` en hexadecimal — si la paleta de
la app cambia, hay que actualizarla también aquí (ver §2 de
`manual-diseno.md`).

- **PDF** (`resources/views/reports/pdf/report.blade.php`): letterhead
  con logo + nombre institucional, barra `brand-primary`, título del
  informe, caja de resumen con barra de progreso para el "% de avance"
  cuando el informe la trae, secciones en bandas de color, tablas con
  encabezado `brand-primary`, zebra striping, alineación numérica y
  "píldoras" de color para la columna "Estado". Fuente: **Helvetica**
  (no Inter) — es una de las 14 fuentes estándar del PDF, no requiere
  embeber archivos `.ttf` (que este proyecto no distribuye localmente:
  Inter se carga solo vía Google Fonts en el navegador), y su trazo
  humanista sin gracias es la sustituta más cercana disponible.
- **Excel**: `ReportExport`/`ReportSectionSheet` (`app/Exports/`)
  delegan TODO el formato a `ReportTheme::styleExcelSheet()` vía un
  evento `AfterSheet` — encabezado institucional (título + sección +
  resumen, con `insertNewRowBefore` para no pisar los datos), header de
  columnas en `brand-primary`, `AutoFilter`, `freezePane`, bordes,
  zebra striping y el mismo color de "Estado" que el PDF/la web. Un
  informe nuevo que use `ReportSectionSheet` hereda este formato sin
  repetir lógica.
- **Detección de columnas genérica, no por nombre/posición**:
  `ReportTheme::isNumericColumnValue()` (para alinear a la derecha) y
  `ReportTheme::statusColumnIndex()` (para colorear "Estado") funcionan
  sobre el valor/encabezado de cada celda, no sobre índices fijos —
  siguen funcionando si un informe futuro agrega columnas sin tocar
  esta clase. `ReportBuilder` sigue entregando `$rows` como arrays de
  valores planos (p. ej. el estado ya viene como el string
  `"Aprobado"`, no el enum); el color se busca por esa etiqueta.
- **Números de página en el PDF — por qué NO es CSS puro:** DomPDF no
  implementa un contador `pages` especial ligado al total de páginas;
  su soporte de `content: counter(...)` es genérico y solo conoce
  contadores que la propia hoja de estilos declara con
  `counter-reset`/`counter-increment`, así que `counter(pages)` siempre
  resuelve a `0` (comprobado por inspección visual del PDF generado).
  La alternativa clásica de DomPDF (`<script type="text/php">` con
  `$PAGE_COUNT`) tampoco aplica aquí: esta app deshabilita
  `enable_php` a propósito y no se activa solo para esto. La solución
  usada es la API nativa de canvas: `ReportTheme::stampPageNumbers()`
  llama a `$pdf->render()` y luego `Canvas::page_script()` para dibujar
  "Página X de Y" directamente sobre cada página ya renderizada — no
  depende de `enable_php` porque no es PHP embebido en el documento,
  sino una llamada directa a la API de dompdf, invocada desde
  `ReportExportController::pdf()` antes de `$pdf->download()`.
- **Bug preexistente encontrado y corregido de paso:** Maatwebsite
  escribe las filas de datos con `PhpSpreadsheet\Worksheet::fromArray()`
  usando comparación `!=` contra `null` por defecto, y en PHP
  `0 != null` es `false` — así que cualquier valor entero `0` de un
  informe (p. ej. "Aprobados: 0") se perdía silenciosamente y la celda
  quedaba vacía en el Excel, sin relación con el rediseño en sí.
  Corregido implementando `Maatwebsite\Excel\Concerns\WithStrictNullComparison`
  en `ReportSectionSheet`.

### 5.11 Paginación: layout, feedback de carga y rendimiento real

- **Vista de paginación compartida** en
  `resources/views/vendor/livewire/tailwind.blade.php` (no
  `vendor/pagination/` — ver el comentario del propio archivo: Livewire
  pisa la vista por defecto de Laravel en cualquier componente con
  `WithPagination`, que es el caso de los 5 listados paginados de esta
  app). Traducida a español y con los tokens de marca; incluye un
  spinner (`wire:loading`, con `wire:loading.delay` para no parpadear en
  respuestas muy rápidas) junto al resumen "Mostrando X a Y de Z
  resultados", y los botones de anterior/siguiente/número de página se
  deshabilitan (`wire:loading.attr="disabled"`) mientras la petición
  está en curso — evita doble clic durante la carga y hace visible que
  algo está pasando, sin depender solo de la percepción.
- **Umbral estándar (25/página)** vía el trait
  `App\Livewire\Concerns\HasStandardPagination` — ver también
  `manual-diseno.md`, sección "Tablas: alineación numérica y
  paginación".
- **Índices que faltaban en `audit_logs`** (migración
  `2026_09_21_..._add_indexes_to_audit_logs_table`): `created_at`
  (columna del `orderByDesc()` de `AuditLogIndex` y de los filtros
  `fromFilter`/`toFilter`), `user_id` (filtro por usuario) y `action`
  (filtro por acción). Ninguna tenía índice — `foreignId()->constrained()`
  no crea un índice automáticamente en PostgreSQL (a diferencia de
  MySQL/InnoDB, que sí indexa las columnas de llave foránea). Con los
  ~170 registros de la base de demostración no se nota ninguna
  diferencia, pero `audit_logs` es por diseño la tabla que más crece sin
  límite en el tiempo (de solo escritura, nunca se purga): sin estos
  índices, tanto el `ORDER BY` como el filtro de fecha habrían
  terminado haciendo un recorrido secuencial completo de la tabla en
  producción real. Verificado con `EXPLAIN`: la consulta de la página
  pasó de un recorrido secuencial + ordenamiento en memoria a un
  `Index Scan Backward using audit_logs_created_at_index`.
- **El recálculo del total en cada página es esperado, no un bug.**
  `paginate()` de Laravel (a diferencia de `simplePaginate()`) ejecuta
  una consulta `COUNT(*)` en cada carga de página porque necesita
  `total()` para el texto "de Z resultados" y para pintar los números de
  página — es el costo inherente de esa función, no algo recalculado de
  más por error. Cambiar a `simplePaginate()` eliminaría ese costo pero
  también esas dos capacidades, que si se piden explícitamente en este
  módulo. El índice de `created_at` sí ayuda al `COUNT(*)` cuando se usa
  el filtro de fecha (rango con índice de apoyo en vez de recorrido
  completo); sin filtro de fecha, un `COUNT(*)` sin condición siempre
  recorre la tabla completa en PostgreSQL (no mantiene un contador de
  filas cacheado como MyISAM) — a la escala de una sola institución
  (documentada en todo este manual) esto se mantiene en el orden de
  milisegundos incluso con decenas de miles de filas, así que no se
  justificó ninguna solución más compleja (conteos aproximados, caché
  del total, etc.) solo para este caso.
- **Consulta de "Acciones" del filtro, cacheada 10 minutos.**
  `AuditLogIndex::render()` se vuelve a ejecutar en cada interacción del
  componente, incluido un simple cambio de página — sin caché, la
  consulta `SELECT DISTINCT action FROM audit_logs` (sin índice de apoyo
  hasta este mismo cambio) se repetía en cada clic aunque el conjunto de
  acciones distintas casi nunca cambia (solo cuando se agrega una acción
  nueva en el código). Envuelta en `Cache::remember(..., now()->addMinutes(10), ...)`
  — es la única consulta cacheada de las tres que arma esta pantalla:
  la lista de usuarios del otro filtro ya es barata (una tabla pequeña),
  y los propios `$logs` (los datos que se filtran) nunca se cachean, se
  leen siempre en vivo.

### 5.12 Traducción de la bitácora de auditoría a lenguaje claro

`audit_logs` guarda `action`, `auditable_type` y las claves de
`metadata->changes` en inglés/snake_case a propósito (es nomenclatura de
código, y el filtro de la pantalla compara contra ese valor crudo) — pero
la bitácora la lee Administración/Coordinación, no un desarrollador.
`App\Services\Audit\AuditLogPresenter` es el único punto de traducción a
español, reutilizado tanto por la columna como por el filtro "Acción" de
`audit-log-index.blade.php`:

- **`actionLabel(string $action): string`** — las 11 acciones que el
  código realmente registra hoy (`grep -rn "AuditLog::record(" app/`; no
  solo `created`/`updated`, automáticas del trait `Auditable`, sino
  también las 5 que cada flujo dispara a mano: `login`, `logout`,
  `login_failed`, `login_blocked_inactive`,
  `password_reset_requested`, `password_reset_completed`,
  `evidence_marked_exempt`, `evidence_marked_overdue`,
  `evidence_exemption_removed`). Una acción sin traducción mapeada
  (código futuro que agregue una nueva) se muestra tal cual en inglés en
  vez de romper o desaparecer — degradación segura, nunca un error.
- **`actionOptions(iterable $actions): array`** — arma las opciones del
  `<select>` del filtro a partir de los valores realmente presentes en la
  tabla (sin cambiar esa consulta, ver §5.11), traduciendo solo la
  etiqueta visible; el `value` del `<option>` sigue siendo el string
  crudo en inglés, que es contra lo que compara el `WHERE`.
- **`auditableLabel(?string $auditableType): ?string`** — traduce el
  prefijo de la columna "Objeto" (ej. `User` → "Usuario") para los 13
  modelos que usan el trait `Auditable`
  (`AcademicPeriod`, `Activity`, `Component`, `CrossCuttingCommitment`,
  `Deliverable`, `DeliverableTemplate`, `Evidence`, `Leadership`,
  `ProgramUnit`, `Review`, `Subcomponent`, `TeacherAssignment`, `User`).
  Un modelo nuevo con el trait que aún no esté en el mapeo muestra su
  `class_basename()` en inglés (ej. "SomeNewModel") en vez de romper.
- **`describeChanges(AuditLog $log): string`** — arma la columna
  "Detalle" en una frase, nunca el JSON crudo de `metadata`:
  - `is_active` tiene frase propia y **distinta según el modelo**: para
    `User` es "Cuenta activada"/"Cuenta desactivada" (tiene sentido
    hablar de "cuenta"); para cualquier otro modelo (los 6 catálogos con
    `is_active`: `Component`, `Subcomponent`, `Activity`, `ProgramUnit`,
    `CrossCuttingCommitment`, `DeliverableTemplate`) es
    "Activado"/"Desactivado", sin la palabra "cuenta" — un catálogo no es
    una cuenta de acceso.
  - `password` viene siempre en `redacted_fields` (nunca en `changes`,
    ver el trait `Auditable`), y se anuncia como "Contraseña
    actualizada" sin exponer nada del valor — sigue sin mostrarse jamás.
  - `remember_token` (el otro campo redactado posible) se **suprime por
    completo**: es un token de sesión sin ningún significado legible
    para un usuario no técnico, a diferencia de "la contraseña cambió".
  - Cualquier otro campo usa un respaldo genérico:
    "`Campo traducido` cambió a `valor`" — el nombre de campo se traduce
    si está en el mapa de ~35 columnas conocidas (`name`, `email`,
    `status`, `due_at`, `weight_percentage`, los `_id` de relación,
    etc.; ver el código fuente para la lista completa) o se deja tal
    cual (snake_case en inglés) si no lo está — nunca se rompe ni se
    oculta un campo nuevo. Los booleanos se muestran como "Sí"/"No" (no
    `true`/`false`) en este respaldo genérico también, no solo en
    `is_active`.
  - **El `valor` también se traduce cuando el campo es un enum del
    proyecto** (`EvidenceStatus`, `AcademicPeriodStatus`,
    `ReviewDecision`, `PeriodicityType`, `EvidenceType`) — sin esto,
    `getChanges()` de Eloquent entrega el valor crudo tal como se guarda
    en la columna (`'pending'`, `'exempt'`, `'active'`...), no la
    instancia del enum ni su `label()`, así que el respaldo genérico
    mostraba literalmente "Estado cambió a pending" en vez de "Estado
    cambió a Pendiente" — bug real, encontrado generando datos de
    prueba con los flujos reales de la app. `status` es ambiguo entre
    modelos (`Evidence` y `AcademicPeriod` usan enums de estado
    distintos con el mismo nombre de columna) y se resuelve por modelo
    en `MODEL_FIELD_ENUMS`; el resto de campos con enum no se repite
    entre modelos y no necesita esa distinción.
  - Si `metadata` no tiene `changes` ni `redacted_fields` (los `login`/
    `logout`, que no tocan ningún modelo), la columna queda vacía —
    mismo comportamiento que antes de este cambio.

### 5.13 "Mi perfil"

Autoservicio de cualquier usuario autenticado sobre su propia cuenta
(`App\Livewire\Profile`, sin subcarpeta — mismo nivel que `Dashboard`,
no un módulo con varias pantallas). Enlazado desde el bloque de usuario
al final del sidebar (escritorio y drawer móvil), no desde un ítem de
`$navGroups` — es una pantalla personal, no una sección de navegación
por rol.

- **Sin Policy/Gate.** A diferencia de `app/Livewire/Admin/Users`
  (un Administrador gestionando OTRAS cuentas, con `UserPolicy`), aquí
  no hace falta autorización adicional: cualquier usuario autenticado
  ya tiene permiso de editar su propia cuenta por definición. El único
  guardián es el middleware `auth` de la ruta.
- **Dos secciones, dos formularios, dos estados de éxito
  independientes.** Un único componente Livewire con dos métodos
  (`saveProfile()`, `savePassword()`) y dos claves de sesión distintas
  para el mensaje de éxito (`profileStatus`, `passwordStatus`) — para
  que guardar una sección nunca limpie ni interfiera con lo que el
  usuario esté escribiendo (sin guardar) en la otra. No hay redirección
  tras guardar (a diferencia de `UserForm`, que redirige a la lista):
  "Mi perfil" no tiene una "lista" a la que volver, así que el
  mensaje de éxito se muestra en línea, mismo patrón ya usado en
  `EvidenceWorkspace` (banda `bg-status-success-subtle` con ícono
  `check-circle`).
- **Alcance deliberadamente angosto.** Mismos tres campos que "Editar
  usuario" salvo Roles y Cuenta activa (los administra exclusivamente
  un Administrador desde "Usuarios") — nada de gestión de sesiones de
  navegador activas ni campos que no existen en el modelo `User`.
- **Contraseña nueva: requisitos de complejidad, no solo `min:8`.**
  "Editar usuario" (un Administrador fijando la contraseña de otra
  persona) solo exige `min:8`. Aquí, al ser el propio usuario
  escogiendo su contraseña —el mismo tipo de evento que un
  restablecimiento por correo (`ResetPassword`)— se exige la regla
  `Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()`
  en vez de solo `min:8`, más cerca de la política real de contraseñas
  del sistema. **No es 100% idéntica** a la de `ResetPassword` (que
  exige mayúscula + minúscula + **número o símbolo**, con un checklist
  visual en vivo): la regla nativa de Laravel usada aquí exige mayúscula
  + minúscula + número específicamente (un símbolo solo no basta), y no
  incluye el checklist visual — decisión deliberada para no construir
  una regla de validación a medida ni replicar esa UI solo para esta
  pantalla; si se requiere paridad exacta, replicar la regla de
  `ResetPassword`.
- **`current_password` (regla nativa de Laravel)** valida "Contraseña
  actual" contra el hash ya guardado del usuario autenticado, sin
  ninguna consulta manual — mensaje ya traducido
  (`lang/es/validation.php`: "La contraseña es incorrecta.").
- Botones "Guardar" en `brand-primary` sólido (`.btn-primary` ya usa
  ese color sólido, sin degradado — el degradado azul→verde queda
  reservado a login/emails, ver `manual-diseno.md` §4).

## 6. Comandos útiles

```bash
php artisan test                     # correr toda la suite de pruebas
php artisan migrate:fresh --seed     # reiniciar la base de datos de desarrollo desde cero
./vendor/bin/pint                    # aplicar el estilo de código
npm run dev                          # Vite en modo watch durante desarrollo
npm run build                        # compilar assets para "producción" local
```

## 7. Seguridad (repaso, sección 10 de la especificación)

| Requisito | Cómo se cumple |
|---|---|
| Hash seguro de contraseñas | Cast `'password' => 'hashed'` de Laravel (bcrypt) |
| HTTPS en producción | No aplica en este entorno 100% local (se documenta como requisito para un despliegue real; en local se usa HTTP) |
| Expiración de sesión por inactividad | `SESSION_LIFETIME` (config/session.php), sesión en base de datos |
| Protección CSRF | Middleware `VerifyCsrfToken` de Laravel (activo por defecto en el grupo `web`) |
| Validación de entradas en servidor | `$this->validate()` en cada componente Livewire que recibe datos |
| Prevención de inyección SQL | Eloquent/Query Builder en todo el proyecto; no hay una sola consulta SQL cruda con datos de usuario (el único `DB::statement` es el `CHECK` constraint de `deliverables`, con una cadena fija, no con datos de usuario) |
| Validación de archivos | Reglas `mimes:` (tipo real, no solo extensión) + `max:` (tamaño) + límite de cantidad, tomadas de la configuración del entregable |
| Descarga con verificación de permisos | `EvidenceFileDownloadController` + `EvidenceFilePolicy`, en cada solicitud |
| Secretos en variables de entorno | Todo en `.env` / `.env.testing`, ambos excluidos de Git (`.gitignore`) |
| Bitácora de auditoría | Módulo 10 — ver diccionario de datos, tabla `audit_logs` |

## 8. Alcance explícitamente fuera de este proyecto

Ver la especificación original, sección 3: no incluye módulo de trabajo
de grado, nómina/liquidación de horas, asistencia/notas, repositorio de
producción científica, gestión documental institucional general, ni
integraciones externas (SSO, OneDrive/SharePoint, firma electrónica,
app móvil, analítica avanzada, aprobación multinivel). Ver también el
diccionario de datos para las tres tablas del modelo conceptual original
que no se implementaron (`system_parameters`, `imports`, `notifications`)
y su justificación.
