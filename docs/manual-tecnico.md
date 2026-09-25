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

### Correo local (recuperación de contraseña y notificaciones de evidencias)

El sistema envía correos reales en dos casos: cuando un usuario pide
restablecer su contraseña (pantalla "¿Olvidó su contraseña?") y cuando
una evidencia cambia de estado (ver 5.15). En local, ambos se entregan a
**Mailpit** — un servidor SMTP + bandeja web que ya viene incluido con
Laragon, sin salir a internet ni depender de credenciales de un
proveedor externo (Gmail, etc.):

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

**Las notificaciones de evidencias van en cola — hace falta un worker
corriendo.** A diferencia del correo de recuperación de contraseña (que
se envía de inmediato, sin cola), las 9 notificaciones de cambio de
estado de evidencias (`App\Notifications\Evidence\*`, ver 5.15)
implementan `ShouldQueue`, para que aprobar/devolver/enviar una evidencia
no espere a que el correo salga. Los jobs quedan en la tabla `jobs`
(driver `database`, `QUEUE_CONNECTION=database` por defecto en
`.env.example`) hasta que algo los procese. En local, eso significa dejar
corriendo, en una terminal aparte:

```bash
php artisan queue:work
# o, en desarrollo, si se prefiere que recargue solo al cambiar código:
php artisan queue:listen
```

Sin este comando corriendo, los correos de evidencias quedan encolados
indefinidamente en la tabla `jobs` y nunca llegan a Mailpit — no fallan,
simplemente esperan. `php artisan queue:work` los procesa uno a uno tan
pronto se encolan.

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
- `App\Services\PasswordPolicy`: única definición de la complejidad mínima
  de contraseña (8 caracteres, mayúscula, minúscula, número, símbolo),
  compartida por "Mi perfil", "Usuarios" (Administrador) y "olvidé mi
  contraseña" — antes cada ruta mantenía su propia regla por separado.
- `App\Models\Concerns\Auditable` (trait): registra automáticamente
  creación/edición en los modelos administrativos en `audit_logs`, sin
  instrumentar cada componente a mano. Se desactiva únicamente durante
  `db:seed` (para no llenar la bitácora de datos de demostración).
- **`App\Rules\CaseAccentInsensitiveUnique`: unicidad de nombre insensible
  a mayúsculas y tildes** en los 6 catálogos (Componentes, Subcomponentes,
  Actividades, Programas, Periodos, Compromisos transversales) —
  "Inducción", "induccion" e "INDUCCIÓN" cuentan como el mismo nombre.
  `Rule::unique()` de Laravel compara bytes exactos en PostgreSQL, así que
  esas 3 variantes pasaban antes como registros distintos. Compara en PHP
  vía `Str::ascii()` (transliteración a ASCII ya incluida en Laravel) en
  vez de la extensión `unaccent` de PostgreSQL, para no depender de
  instalarla ni en Neon ni en el Postgres local — estos catálogos son
  pequeños, así que comparar contra todas las filas del alcance en PHP es
  un costo trivial. API fluida (`->where()->ignore()`) a propósito
  parecida a `Rule::unique()`, para ser un reemplazo casi directo.
  Actividades es la única de las 6 con alcance compuesto
  (`component_id` + `subcomponent_id`, ya que antes no tenía ninguna
  validación de unicidad); las otras 5 mantienen el mismo alcance que ya
  tenían (Subcomponentes por `component_id`, las demás solo por nombre).
  Actividades también suma dos índices únicos de respaldo en BD (uno para
  cuando hay subcomponente, un índice parcial para cuando no lo hay,
  porque PostgreSQL trata cada `NULL` como distinto en un índice único
  normal) — los otros 5 catálogos ya tenían su índice único exacto desde
  antes.
- **`$query->whereAccentInsensitive($columna, $valor)` — mismo problema de
  mayúsculas/tildes, pero para los buscadores (`<x-search-input>`), no
  para unicidad.** Buscar "distribucion" no encontraba "Distribución"
  porque `ilike` de PostgreSQL sí distingue tildes. A diferencia de
  `CaseAccentInsensitiveUnique` (que compara en PHP porque los catálogos
  que valida son pequeños), esto se resuelve en SQL: los 5 buscadores
  existentes (Usuarios, Liderazgos, Distribución docente, Actividades,
  Subcomponentes) viven justo en las tablas de mayor volumen del
  sistema — traer todas las filas a PHP para filtrar anularía la
  paginación que varias de ellas ya tienen. Es un macro de
  `Illuminate\Database\Eloquent\Builder`, registrado una sola vez en
  `AppServiceProvider::registerAccentInsensitiveSearchMacro()`, que usa
  `translate(lower(columna), 'áéíóúñü', 'aeiounu')` en ambos lados del
  `LIKE` — `translate()` es una función nativa de PostgreSQL (sin la
  extensión `unaccent`, mismo motivo por el que
  `CaseAccentInsensitiveUnique` tampoco depende de ella), así que no hace
  falta instalar nada ni en Neon ni en local.
- **`App\Services\PendingWorkChecker`: cuánto trabajo pendiente depende de
  un usuario por su rol de Docente o de Líder — compartido por
  `UserForm::blocksRoleRemoval()` (quitar un rol) y
  `UserIndex::toggleActive()` (desactivar la cuenta por completo), la
  misma regla aplicada a los dos flujos que pueden dejar a alguien sin la
  capacidad de resolver su propio trabajo.** Quitar Docente (o
  desactivar a alguien que lo es) a quien tiene evidencias que no estén
  en `Approved`/`Exempt` (mismo criterio que `EvidencePolicy::markExempt()`),
  o quitar Líder (o desactivar) a quien tiene evidencias `Submitted`
  dentro de su ámbito de liderazgo vigente (mismo criterio de alcance que
  `User::canLeadAssignment()`), se **bloquea** con un error indicando
  cuántas evidencias/revisiones tiene pendientes — no es solo una
  advertencia. En `UserForm`, agregar un rol nunca se bloquea (el chequeo
  solo mira roles que desaparecen de la selección), y se omite por
  completo si el mismo guardado desactiva la cuenta (`is_active` en
  falso) — ese caso ya lo cubre `toggleActive()` por su cuenta. En
  `UserIndex`, el chequeo mira los roles que el usuario **ya tiene**
  (desactivar quita toda capacidad, no un rol puntual) y nunca aplica al
  reactivar. El mensaje se muestra en `$deactivationError`, con el mismo
  bloque visual (`bg-status-error-subtle`) que ya usa
  `EvidenceWorkspace::$submissionError`.
- **`lang/es/validation.php`: mensajes de validación en español natural,
  sin nombres técnicos de columna.** El archivo base (todas las reglas
  estándar de Laravel: `required`, `max`, `unique`, `email`...) ya estaba
  publicado y `APP_LOCALE=es` ya hacía que Laravel lo usara — lo que
  faltaba era la sección `'attributes'`, que traduce el nombre del campo
  que Laravel interpola en `:attribute`. Sin una entrada ahí, Laravel
  muestra el nombre crudo de la propiedad Livewire/columna
  (`assigned_hours`) en vez de su nombre natural ("horas asignadas").
  Se hizo un barrido de todas las reglas de validación de
  `app/Livewire/**` (`grep -rn "validate(\|protected \$rules\|function rules()\|\$rules\["`)
  y se completaron unas 35 entradas que faltaban — donde el campo ya
  tenía traducción en `AuditLogPresenter::FIELD_LABELS` (usado para la
  bitácora, no para validación) se reutilizó la misma palabra en
  minúscula, para no tener dos redacciones distintas del mismo campo.
  `.env.testing` no tenía `APP_LOCALE`/`APP_FALLBACK_LOCALE` (caía al
  `'en'` de `config/app.php`) — se agregaron ahí también, igual que en
  `.env` y `.env.render.example`, porque sin esto ningún test podía
  verificar el idioma real de un mensaje de error.
- **`App\Models\Concerns\OrdersNewestFirst`: los 5 catálogos "planos"
  (Componentes, Subcomponentes, Actividades, Programas, Compromisos
  transversales) listan lo más reciente primero, no alfabético.** Antes
  cada Livewire Index ordenaba su tabla principal a mano con
  `->orderBy('name')` — en el uso real, lo que Administración/
  Coordinación acaba de crear o editar es justo lo que quiere confirmar
  arriba de la lista. El trait agrega `scopeNewestFirst()`
  (`->orderByDesc('id')`, no `created_at`: `id` es estrictamente
  monótono y único, así que el orden queda determinista incluso si
  varias filas comparten el mismo `created_at` por un seeder o una
  factory en un bucle rápido). Los `<select>` de un catálogo dentro del
  formulario de otro (ej. el desplegable de Componentes en el formulario
  de Actividades) siguen ordenados por nombre a propósito — ahí ayuda
  ubicar una opción conocida, no ver la más reciente primero.
  **Periodos académicos queda fuera**: ya ordenaba por `start_date`
  descendente (fecha de inicio del periodo), un criterio más correcto
  para un periodo académico que "más reciente creado" — cambiarlo habría
  sido una regresión, no una mejora.
- **`Deliverable::acceptsEvidenceSubmissions()`: un docente solo puede
  guardar borrador, adjuntar archivos, agregar enlaces o enviar mientras
  el periodo académico del entregable está Activo.** RF-009 nombra 4
  estados de periodo (planeación, activo, cerrado, archivado) sin
  detallar el comportamiento de cada uno salvo "cerrado" — se resolvió
  que "planeación" (el periodo aún se está configurando: distribución,
  actividades, líderes) y "archivado" tampoco deben permitir cargar
  evidencias, igual que "cerrado". El método vive en `Deliverable` (no en
  `AcademicPeriod`) porque es ahí donde `EvidenceWorkspace` ya tenía la
  relación a mano. Se usa en `saveDraft()`, `submit()` (que llama a
  `saveDraft()` primero, así que hereda el bloqueo sin código adicional),
  `updatedNewFiles()` (rechaza el archivo apenas se adjunta, igual que ya
  hacía con la whitelist de extensiones) y `addLink()`. Es solo sobre
  acciones de escritura — ver los entregables asignados sigue funcionando
  en cualquier estado del periodo (RF-045).

Disco `local` de Laravel (`storage/app/private`, sin symlink público).
Cada archivo se guarda con un nombre aleatorio (`stored_name`); el
nombre original se conserva aparte para mostrarlo al usuario. Las
descargas pasan siempre por `EvidenceFileDownloadController`, que
verifica permisos en cada solicitud vía `EvidenceFilePolicy`.

**Extensiones permitidas, con una whitelist por defecto.** El campo
"Formatos de archivo permitidos" de un entregable (`DeliverableForm`) es
opcional — pero dejarlo vacío nunca significa "cualquier extensión es
válida". `EvidenceWorkspace::fileValidationRules()` arma la regla
`mimes:` a partir de `Deliverable::effectiveAllowedFileTypes()`, que
aplica `Deliverable::DEFAULT_ALLOWED_FILE_TYPES`
(`pdf, doc, docx, jpg, jpeg, png`) cuando el entregable admite evidencia
tipo "Archivo"/"Múltiples archivos" y no se configuró explícitamente
ninguna extensión. La regla `mimes:` de Laravel valida el tipo real del
contenido (vía `finfo`), no solo la extensión del nombre de archivo.

**Dos gotchas de `WithFileUploads` (Livewire) en `updatedNewFiles()`,
confirmados leyendo el código fuente de Livewire, no por hipótesis:**

- **`resetErrorBag('newFiles')` no limpiaba un error de una subida
  fallida en el propio endpoint de Livewire.** Cuando el archivo excede
  el límite de subida de Livewire (`/livewire/upload-file`, antes de que
  `newFiles` llegue a asignarse), `WithFileUploads::_uploadErrored()`
  guarda el mensaje bajo la key indexada `newFiles.0`, no `newFiles`
  — y `HandlesValidation::resetErrorBag()` de Livewire compara con
  `Str::is($patrón, $key)`: sin un `*` en el patrón que pasamos, exige
  coincidencia exacta, así que `newFiles.0` sobrevivía al reset y seguía
  visible al adjuntar un archivo válido después. Se cambió a
  `resetErrorBag(['newFiles', 'newFiles.*'])`.
- **`Validator::make(['file' => $file], ['file' => $rules])` mostraba
  "El campo file..." en vez de "El campo archivo...".** La key ad hoc
  `'file'` no tiene entrada en `lang/es/validation.php` (solo `newFiles`/
  `newFiles.*` la tienen). Se cambió a
  `Validator::make(['newFiles' => [$file]], ['newFiles.*' => $rules])`
  para reutilizar la traducción existente, en vez de agregar una entrada
  nueva y genérica `'file'` que podría aplicar sin querer a algún otro
  `Validator::make()` futuro. De paso, unifica la key de validación con
  la que ya usa `saveDraft()` para esto mismo.

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
(`wire:click="closeModal"`) y Escape
(`x-on:keydown.escape.window="$wire.closeModal()"` en el overlay).
Deliberadamente **no** hay `@click.outside` en el `<div class="card">`:
un clic fuera de lugar no debe descartar en silencio un formulario a
medio llenar (mismo criterio en `<x-confirm-modal>`, que tampoco lo
tiene). El overlay lleva además `role="dialog"`, `aria-modal="true"` y
`aria-labelledby` apuntando al `<h2>` del título, igual que
`<x-confirm-modal>`.

**Por qué los tres `resetValidation()`, no solo uno:** `$this->reset()`
únicamente restaura el *valor* de las propiedades públicas — nunca toca
el error bag de la validación. Sin este fix, un intento de "Nuevo" con
campos vacíos (que genera errores) seguido de "Cancelar" y luego
"Editar" sobre un registro válido mostraba los mismos mensajes de error
de la validación anterior bajo campos que sí tenían datos correctos —
bug real encontrado y corregido en los seis módulos que siguen este
patrón: Componentes, Subcomponentes, Actividades, Programas,
Compromisos transversales y Periodos (`ComponentIndex.php` es la
plantilla que copian los demás).

**Beneficio adicional, no buscado a propósito:** antes de este fix,
estas modales no tenían ninguna forma de cerrarse desde el lado del
cliente — solo un botón que hacía `$set('showModal', false)`. Al
introducir `closeModal()` como el único punto de cierre para resolver
el error bag, de paso quedó como el lugar natural para engancharle
Escape y clic-fuera-de-la-tarjeta, que antes no existían en ninguno de
estos seis módulos.

### 5.9 Estructura de carpetas relevantes

**Convención de idioma**: todo el código y el esquema de base de datos
están en inglés (tablas, columnas, modelos Eloquent, enums, nombres de
clase) — el español queda reservado para los textos de interfaz y el
contenido cargado por el usuario. Ver también el diccionario de datos.

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
  `WithPagination`, que es el caso de los 6 listados paginados de esta
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
- **N+1 de `ComplianceCalculator` en el Dashboard, corregido con carga
  masiva en vez de una consulta por docente.**
  `Dashboard::coordinationPanel()` y `leaderPanel()` recorrían la lista
  de docentes con `.map()`, y por cada uno hacían
  `Deliverable::where(...)->get()` más la consulta interna de
  `ComplianceCalculator::forUser()` — 2 consultas nuevas por docente, en
  cada `render()` (cada cambio de filtro de periodo). Ahora se trae todo
  de una vez (entregables del periodo con sus destinatarios, evidencias
  de los docentes involucrados) y se agrupa en PHP
  (`Collection::groupBy()`/`filter()`); `ComplianceCalculator::forUser()`
  acepta un tercer parámetro opcional `$evidences` para usar esos datos
  ya cargados en vez de volver a consultar. Medido con `DB::getQueryLog()`:
  `coordinationPanel()` con 20 docentes pasó de **42 a 4 consultas**
  (antes escalaba linealmente: 2N+2; ahora es constante), y
  `leaderPanel()` con 10 docentes de **57 a 8**.
- **`Evidence::scopeReviewableBy()`: "evidencias que este usuario puede
  revisar" como un JOIN, no como un filtro en PHP evidencia por
  evidencia.** `ReviewInbox`, `Dashboard::leaderPanel()` (el contador
  "pendientes de revisión en tu ámbito") y
  `UserForm::pendingReviewCountForLeader()` traían TODAS las evidencias
  `Submitted` y llamaban `isReviewableBy()` una por una — cada llamada,
  para un Líder, disparaba `matchingTeacherAssignment()` +
  `canLeadAssignment()`, 2 consultas nuevas por evidencia. El nuevo scope
  expresa la misma regla (empareja por `teacher_assignments` + liderazgo
  vigente en `leaderships`) como un único `JOIN`. `isReviewableBy()` sigue
  existiendo tal cual para el chequeo de una sola evidencia
  (`EvidencePolicy::review()`), donde no tiene sentido montar un JOIN.
  Medido: `ReviewInbox` con 10 evidencias pasó de un número de consultas
  proporcional a la cantidad de evidencias a un número fijo, verificado
  con un test que lo mantiene por debajo de un umbral aunque la cantidad
  de evidencias crezca.
- **Solo el Líder asignado revisa/aprueba/devuelve evidencias — se quitó
  el atajo de Administrador/Coordinación.** El documento de alcance es
  explícito: RS-005 y RF-027/RF-056 a RF-059 enuncian la revisión siempre
  como "el líder deberá...", nunca como capacidad de Coordinación
  (que "supervisa cumplimiento" y "obtiene informes", no revisa
  evidencias) ni de Administrador. `Evidence::isReviewableBy()` y
  `scopeReviewableBy()` ya no tienen ningún atajo por rol — solo cuentan
  un liderazgo vigente sobre el ámbito de la evidencia.
  **Única excepción real: los compromisos transversales**, que al no
  tener actividad nunca pueden tener un líder que los cubra
  (`matchingTeacherAssignment()` siempre `null` para ellos) — sin ninguna
  vía de aprobación quedarían atascados en "Enviada" para siempre, así
  que ahí (y solo ahí) Administrador conserva la capacidad de revisar
  (Coordinación sigue sin poder, ni siquiera en este caso).
  `scopeReviewableBy()` mantiene `$onlyViaLeaderRole` (antes existía para
  otro motivo — omitir el atajo general — ahora sirve para excluir
  específicamente el camino de Administrador sobre transversales, para
  alguien que sea Administrador y Líder a la vez):
  `Dashboard::leaderPanel()` lo usa en su contador "en tu ámbito" (no debe
  sumar transversales que ese usuario solo pueda revisar por ser también
  Administrador) y `UserForm::pendingReviewCountForLeader()` lo usa para
  no bloquear quitar el rol Líder por evidencias que en realidad dependen
  del rol Administrador de esa misma persona; `ReviewInbox` lo deja en su
  valor por defecto porque ahí sí interesa todo lo que el usuario puede
  revisar por cualquier vía. `ReviewShow::mount()` autoriza la página si
  el usuario puede revisar **o** reabrir (`EvidencePolicy::reopen()`,
  exclusivo de Administrador sobre una evidencia ya `Approved`) — sin
  este OR, un Administrador que solo puede reabrir (no revisar, porque la
  evidencia es de una actividad fuera de su ámbito) se habría quedado sin
  poder ni siquiera entrar a la página.
- **Índices faltantes en `evidences`, `deliverables`, `teacher_assignments`
  y `leaderships`** (migración
  `2026_09_25_..._add_indexes_for_dashboard_and_review_queries`): mismo
  hallazgo que `audit_logs` (`foreignId()->constrained()` no indexa en
  PostgreSQL). Se agregó índice a `evidences.status` y `evidences.user_id`
  (los uniques compuestos existentes no cubrían ninguno de los dos como
  filtro independiente), `deliverables.academic_period_id`, y dos índices
  compuestos — `teacher_assignments(academic_period_id, program_unit_id,
  activity_id)` y `leaderships(user_id, academic_period_id,
  program_unit_id)` — para los patrones de filtro que `leaderPanel()` y
  el JOIN de `scopeReviewableBy()` usan y que el unique existente de cada
  tabla no cubre (su columna líder es otra).
- **`Model::preventLazyLoading()` activado solo en local**
  (`AppServiceProvider::boot()`, `$this->app->isLocal()`) — red de
  seguridad preventiva: lanza una excepción al acceder a una relación no
  cargada, en vez de disparar una consulta silenciosa, para que un N+1
  nuevo aparezca en el desarrollo diario en vez de en producción. Nunca
  en `testing` ni en producción, a propósito.

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
- **`document_number` y `email` son de solo lectura, no autoservicio
  libre.** Son datos administrativos/de acceso — corregirlos requiere
  pasar por "Usuarios" (Administrador), donde ya existe la validación
  de unicidad y queda auditado vía el trait `Auditable`. En la vista,
  ambos campos van con el atributo HTML `disabled` (`.field-input` ya
  trae el estilo `disabled:bg-surface-muted disabled:text-text-secondary`,
  sin tocar CSS aparte) y un `.field-help` "Si necesitas actualizar
  este dato, contacta a un Administrador." `saveProfile()` valida y
  guarda únicamente `name` — ni siquiera si alguien manipulara la
  petición de Livewire a mano para escribir en `document_number`/
  `email` esos valores llegarían a persistirse, porque `update()` no
  los toca en absoluto (no es solo un candado de interfaz).
- **Contraseña nueva: una única política compartida
  (`App\Services\PasswordPolicy::rules()`).** Las 3 rutas que crean o
  cambian una contraseña —aquí, "Editar usuario" (un Administrador
  fijando la de otra persona) y `ResetPassword` (restablecimiento por
  correo)— exigen exactamente la misma regla:
  `Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols()`.
  Antes cada una tenía su propia definición por separado —"Editar
  usuario" solo pedía `min:8`, y `ResetPassword` una implementación
  manual ligeramente distinta (número **o símbolo**, en vez de número
  específicamente)— con el riesgo de que las 3 se desincronizaran en
  silencio; ahora un cambio a la política se hace en un único lugar.
  `ResetPassword` sigue teniendo su propio checklist visual en vivo (ver
  abajo), pero es solo la traducción campo-por-campo de esta misma
  política, no una segunda definición.
- **`current_password` (regla nativa de Laravel)** valida "Contraseña
  actual" contra el hash ya guardado del usuario autenticado, sin
  ninguna consulta manual — mensaje ya traducido
  (`lang/es/validation.php`: "La contraseña es incorrecta.").
- Botones "Guardar" en `brand-primary` sólido (`.btn-primary` ya usa
  ese color sólido, sin degradado — el degradado azul→verde queda
  reservado a login/emails, ver `manual-diseno.md` §4).
- **El nombre en el sidebar se actualiza sin recargar la página —
  `App\Livewire\UserName`.** `layouts/app.blade.php` NO es en sí mismo
  un componente Livewire: es el layout que Livewire renderiza una sola
  vez por carga de página, y en el que luego inserta el "slot" del
  componente activo (`Profile`, `Dashboard`, etc.) en cada actualización
  AJAX — un `{{ $user->name }}` puesto directamente ahí nunca se
  refrescaría solo porque `Profile` actualice su propio HTML. Por eso
  el nombre se extrajo a su propio componente, `App\Livewire\UserName`,
  montado dos veces en el layout (`<livewire:user-name key="..." />`,
  con `key` distinto en escritorio y en el drawer móvil, obligatorio en
  Livewire al montar el mismo componente más de una vez en la misma
  página). `Profile::saveProfile()` llama a
  `$this->dispatch('profile-updated')` tras guardar; Livewire lo
  retransmite como un `CustomEvent` de navegador real, así que
  **cualquier** componente Livewire ya montado en la página lo recibe
  (no hace falta relación padre-hijo) — `UserName::refreshName()`,
  con el atributo `#[On('profile-updated')]`, es quien reacciona y
  vuelve a leer `Auth::user()->name`. Roles, el enlace "Mi perfil" y
  "Cerrar sesión" siguen siendo Blade plano en el layout — no hacía
  falta moverlos, nada de eso cambia desde "Mi perfil".
  - **Revisado, no hacía falta el mismo tratamiento en otros lugares**:
    el único otro sitio del sistema que muestra el nombre del usuario
    autenticado es el saludo de `dashboard.blade.php`
    ("Bienvenido, ..."), pero ese es el propio `render()` de
    `App\Livewire\Dashboard` — se vuelve a consultar la base de datos
    en cada carga de esa página, y como el proyecto no usa
    `wire:navigate` en ningún enlace (toda navegación entre pantallas
    es una carga de página normal), no hay forma de que ese saludo
    quede desactualizado sin que a la vez ocurra una recarga completa
    que ya lo refresca solo. Distinto del sidebar, que sí permanece
    montado sin recargar mientras se guarda el formulario de "Mi
    perfil" en la misma página.

### 5.14 Nombre del archivo descargable de los informes: sin datos personales

El nombre del archivo PDF/Excel de un informe (`ReportExportController::fileName()`)
se arma a partir de `$report['file_identifier']`, **nunca** de
`$report['title']` — son dos campos deliberadamente separados que
`ReportBuilder` devuelve para cada uno de los 4 informes (ver el
docblock de la clase para el detalle completo):

- `title` es lo que se ve en pantalla y dentro del propio documento
  (PDF/Excel) — puede incluir el nombre completo de una persona sin
  ningún problema, porque quien lo está viendo ya sabe a quién
  corresponde el informe que pidió.
- `file_identifier` es lo único que `Str::slug()` convierte en el
  nombre del archivo. Solo el informe **individual por docente**
  identificaba a una persona ahí (el nombre completo del docente); los
  otros 3 no identifican a ninguna persona en su título — actividad +
  componente, compromiso transversal, o solo el periodo son
  información institucional, no personal — así que su
  `file_identifier` repite el mismo contenido que `title`, sin cambios.

| Informe | Antes (nombre de archivo) | Ahora |
|---|---|---|
| Individual por docente | `informe-individual-diego-docente-2026-1.pdf` | `informe-individual-{id_docente}-2026-1.pdf` |
| Por actividad | `informe-por-actividad-docencia-clases-teoricas-2026-1.pdf` | sin cambios (sin dato personal) |
| Compromisos transversales | `informe-de-compromisos-transversales-capacitacion-institucional-2026-1.pdf` | sin cambios (sin dato personal) |
| Consolidado por periodo | `consolidado-del-periodo-2026-1.pdf` | sin cambios (nunca identificó a nadie) |

Separar los dos campos explícitamente (en vez de, por ejemplo,
anonimizar el nombre solo dentro de `fileName()` con una expresión
regular) evita que un cambio futuro al texto de `title` — si algún día
se agrega el nombre de un líder a "Por actividad", por ejemplo — se
filtre al nombre del archivo sin que nadie lo note: cualquiera que
edite un `title` tiene que decidir explícitamente qué poner en
`file_identifier` al mismo tiempo, en la misma línea del código.

**Trazabilidad en pantalla, sin cambios**: las 4 pantallas de informes
ya mostraban con claridad a qué corresponde cada descarga antes de
hacer clic — el filtro seleccionado (docente/actividad/compromiso/
periodo, por nombre) y el título del informe quedan visibles justo al
lado de los botones "Descargar PDF"/"Descargar Excel" — así que este
cambio no necesitó ningún ajuste de interfaz para no perder esa
trazabilidad, solo en el nombre del archivo ya descargado.

### 5.15 Notificaciones por correo de cambios de estado de evidencias

Medio adicional de seguimiento para los docentes (requerimiento de la
dirección del proyecto): cada transición de estado de una evidencia
envía uno o dos correos, usando Mailpit en local (ver 3, "Correo local")
y quedando en cola (ver 3, "Las notificaciones de evidencias van en
cola").

**9 clases, una por combinación transición/destinatario**
(`App\Notifications\Evidence\*`), todas heredando de la base abstracta
`EvidenceStatusNotification` (mismo principio que `ReportTheme` para
PDF/Excel: un único lugar de identidad visual — logo, badge de estado,
layout — en vez de repetirlo 9 veces):

| Transición | Al docente/actor original | Al otro rol |
|---|---|---|
| Enviado | `EvidenceSubmissionConfirmedNotification` | `EvidencePendingReviewNotification` (líder o Coordinación) |
| Requiere ajustes | `EvidenceReturnConfirmedNotification` (al revisor) | `EvidenceNeedsAdjustmentNotification` (al docente) |
| Aprobado | `EvidenceApprovalConfirmedNotification` (al revisor) | `EvidenceApprovedNotification` (al docente) |
| Vencido | — (automático) | `EvidenceOverdueNotification` — **un solo envío, dos destinatarios**: docente y Coordinación |
| Exento | `EvidenceExemptionConfirmedNotification` (a quien la marcó) | `EvidenceExemptedNotification` (al docente) |

**Enganchada exactamente donde ya ocurre cada transición, nunca por un
observer genérico de cambios de estado:**

- `Evidence::submitCurrentVersion()`, `Evidence::markExempt()`: el envío
  vive dentro del propio método del modelo, junto al `update()` del
  estado — igual patrón que el `AuditLog::record()` que ya hacían.
- `Evidence::approveCurrentReview()` / `returnCurrentReviewForAdjustment()`
  (nuevos): antes, `ReviewShow::approve()`/`returnForAdjustment()`
  actualizaban el estado directamente sobre el modelo (a diferencia de
  las otras 3 transiciones, que ya eran métodos dedicados) — se
  refactorizaron a estos dos métodos para que las 5 transiciones queden
  centralizadas por igual.
- `MarkOverdueEvidences::handle()`: el envío vive dentro del mismo
  `foreach` que ya marca cada evidencia como `Vencido`. La consulta del
  comando ya excluye la evidencia que una corrida anterior dejó en
  `Expired` (solo selecciona `Pendiente`/`Borrador`), así que ejecutarlo
  dos veces sobre los mismos datos nunca reenvía el aviso — no hace
  falta ningún guard adicional.

**Por qué `reopen()` no dispara nada:** `ReviewShow::reopen()` (acción
excepcional de Administrador para reabrir una evidencia ya aprobada)
también deja el estado en `NeedsAdjustment` — el mismo valor que produce
una devolución real. Si la notificación se hubiera enganchado observando
genéricamente "el estado pasó a NeedsAdjustment" en vez de en el punto
exacto de `returnCurrentReviewForAdjustment()`, `reopen()` habría
disparado por error el aviso de "tu evidencia requiere ajustes" — una
acción sin `Review` asociada y fuera de los 5 escenarios pedidos. Es la
razón concreta por la que este subsistema evita cualquier forma de
observer de estado.

**Destinatario "líder" en Enviado/Vencido** (`Evidence::reviewerRecipients()`
y `Evidence::coordinationUsers()`): un entregable transversal nunca tiene
líder por diseño (no tiene actividad), y una actividad puede quedarse
momentáneamente sin nadie liderándola — en ambos casos, Coordinación
recibe el aviso en su lugar, con el mismo criterio de vigencia de
liderazgo que ya usa `User::canLeadAssignment()` (liderazgo activo hoy,
por programa + periodo, con o sin actividad específica).

**Plantilla compartida** (`resources/views/mail/status-notification.blade.php`):
adaptada de `emails/reset-password.blade.php` (mismo logo incrustado con
`$message->embed()`, mismo degradado de marca azul→verde reservado para
momentos "de marca"), quitando el aviso de seguridad y la nota de
expiración —no aplican aquí— y agregando el badge de estado y el bloque
de contexto (entregable, actividad/compromiso transversal, periodo). El
color del badge (`statusBgColor`/`statusTextColor`) viene de
`ReportTheme::statusTone()` — la misma paleta que ya usan `<x-status-badge>`
y los informes PDF/Excel, para que el badge del correo nunca quede
desincronizado si algún día cambia el tono de un estado.

## 6. Comandos útiles

```bash
php artisan test                     # correr toda la suite de pruebas
php artisan migrate:fresh --seed     # reiniciar la base de datos de desarrollo desde cero
./vendor/bin/pint                    # aplicar el estilo de código
npm run dev                          # Vite en modo watch durante desarrollo
npm run build                        # compilar assets para "producción" local
php artisan queue:work               # procesar las notificaciones de evidencias encoladas (ver 3 y 5.15)
php artisan evidences:mark-overdue   # marcar a mano las evidencias vencidas (normalmente vía scheduler, ver 5.7)
```

## 7. Seguridad (repaso, sección 10 de la especificación)

| Requisito | Cómo se cumple |
|---|---|
| Hash seguro de contraseñas | Cast `'password' => 'hashed'` de Laravel (bcrypt) |
| HTTPS en producción | No aplica en este entorno 100% local (en local se usa HTTP) — checklist de variables a cambiar antes de un despliegue real en 7.1 |
| Expiración de sesión por inactividad | `SESSION_LIFETIME` (config/session.php), sesión en base de datos |
| Cookie de sesión solo por HTTPS | `SESSION_SECURE_COOKIE` — `false` en local, debe pasar a `true` antes de un despliegue real (ver 7.1) |
| Complejidad mínima de contraseña | `App\Services\PasswordPolicy::rules()`, única definición compartida por las 3 rutas que crean o cambian una contraseña (ver 5.3) |
| Protección CSRF | Middleware `VerifyCsrfToken` de Laravel (activo por defecto en el grupo `web`) |
| Validación de entradas en servidor | `$this->validate()` en cada componente Livewire que recibe datos |
| Prevención de inyección SQL | Eloquent/Query Builder en todo el proyecto; no hay una sola consulta SQL cruda con datos de usuario (el único `DB::statement` es el `CHECK` constraint de `deliverables`, con una cadena fija, no con datos de usuario) |
| Validación de archivos | Reglas `mimes:` (tipo real, no solo extensión) + `max:` (tamaño) + límite de cantidad, tomadas de la configuración del entregable |
| Descarga con verificación de permisos | `EvidenceFileDownloadController` + `EvidenceFilePolicy`, en cada solicitud |
| Secretos en variables de entorno | Todo en `.env` / `.env.testing`, ambos excluidos de Git (`.gitignore`) |
| Bitácora de auditoría | Módulo 10 — ver diccionario de datos, tabla `audit_logs` |

### 7.1 Checklist de despliegue

Variables de `.env` que solo son correctas en local y **deben** cambiar
antes de cualquier despliegue con HTTPS real — nada de esto es
automático, es responsabilidad de quien despliegue revisarlo:

- **`APP_DEBUG=false`** — con `true` en producción, cualquier error 500
  expone el stack trace completo (rutas del servidor, y potencialmente
  valores ya cargados del `.env`) a quien lo provoque.
- **`APP_ENV=production`**.
- **`SESSION_SECURE_COOKIE=true`** — en `false` (correcto en local, sin
  HTTPS), la cookie de sesión nunca lleva el flag `Secure`, ni siquiera
  si el sitio ya sirve por HTTPS.
- **`MAIL_MAILER` y credenciales de correo**, apuntando a un proveedor
  real (relay SMTP institucional, Amazon SES, Mailgun, Postmark...) en
  vez de Mailpit — ver 3, "Correo local". **Ojo con plataformas que
  bloquean puertos SMTP salientes** (Render free lo hace desde
  septiembre de 2025, ver 8.1) — en esos casos hace falta la API HTTP
  del proveedor, no SMTP, sin importar cuál sea.
- El cron del sistema operativo apuntando a `schedule:run` cada minuto
  (`evidences:mark-overdue` no se dispara solo sin esto) — ver 5.7.
- Un proceso supervisado (Supervisor, systemd, etc.) corriendo
  `php artisan queue:work` de forma persistente — ver 3, "Las
  notificaciones de evidencias van en cola".

## 8. Despliegue en entorno de pruebas (Render + Neon + Brevo)

Este es un despliegue de **pruebas**, no de producción real — usa
servicios gratuitos (Render, Neon Postgres, Brevo vía su API HTTP) que
tienen límites propios (Render free no ofrece un worker de colas persistente,
ver más abajo). No reemplaza el entorno local de desarrollo (Mailpit,
PostgreSQL local), que sigue funcionando exactamente igual — nada de esta
sección toca `.env` ni la configuración local.

### 8.1 Cómo funciona

- **Proxies de confianza (`bootstrap/app.php`)**: Render termina TLS en su
  propio proxy y reenvía la petición al contenedor por HTTP plano,
  informando el esquema real vía `X-Forwarded-Proto`. Sin
  `$middleware->trustProxies(at: '*')`, Laravel ve esa conexión interna
  como HTTP y genera con ese esquema toda URL absoluta (`url()`,
  `asset()`, `@vite`) — el síntoma real de esto fue el navegador
  bloqueando los assets del login como contenido mixto, aunque `APP_URL`
  ya estuviera en `https`. `at: '*'` es lo estándar para este tipo de
  despliegue de un solo proxy interno controlado por la plataforma (nunca
  el cliente final, que solo le habla al proxy); no cambia nada en local,
  que no manda ningún header `X-Forwarded-*`. No hace falta además
  `URL::forceScheme('https')`: forzar el esquema a mano solo tendría
  sentido si no se pudiera confiar en el proxy, y aquí sí se puede —
  agregarlo sería redundante (y, sin una condición por entorno, rompería
  el `http` correcto en local).
- **`LOG_CHANNEL=stderr`, no `stack`**: el canal por defecto (`stack` →
  `single`) escribe en `storage/logs/laravel.log`, un archivo *dentro*
  del contenedor — se pierde en cada reinicio/redeploy y nunca aparece en
  la pestaña "Logs" de Render. `stderr` (ya definido en
  `config/logging.php`, sin cambios de código necesarios) escribe a la
  salida estándar de error del proceso, que es justo lo que Render
  captura y muestra en esa pestaña.
- **`Dockerfile`** (raíz): Render no soporta PHP nativo, así que la app se
  empaqueta en una imagen Docker de dos etapas — una etapa con Node
  compila los assets de Tailwind/Vite en modo producción (`npm run
  build`, nunca `npm run dev`) y la otra, con PHP 8.3, instala las
  dependencias de Composer (`--no-dev --optimize-autoloader`) y copia
  encima los assets ya compilados. No usa Nginx/PHP-FPM a propósito —
  `php artisan serve` es suficiente para un contenedor único de pruebas;
  no es la forma de servir tráfico de producción real a alta
  concurrencia.
- **`docker/start.sh`**: el comando que realmente arranca el contenedor.
  Corre `config:cache`/`route:cache`/`view:cache` y **`php artisan
  migrate --force --database=pgsql_migrate`** con las variables de
  entorno reales de Render ya presentes (nunca en tiempo de build, ahí
  todavía no existen), y recién después levanta `php artisan serve
  --host=0.0.0.0 --port=$PORT` — la variable que Render asigna en tiempo
  de ejecución. `migrate --force` corriendo en cada arranque es seguro:
  Laravel solo aplica las migraciones pendientes, así que un segundo
  despliegue no vuelve a ejecutar las que ya corrieron.
- **Migraciones por la conexión directa de Neon, no la pooled**: el resto
  de la app usa `DB_HOST` (el host `-pooler` de Neon) a través de la
  conexión `pgsql`, pero `migrate` usa una conexión aparte,
  `pgsql_migrate` (`config/database.php`), que apunta a `DB_HOST_MIGRATE`
  — el host de Neon **sin** `-pooler`. El pooler de Neon corre PgBouncer
  en modo *transaction pooling*, que puede reasignar la sesión de
  backend real entre una transacción y la siguiente; esto causó un fallo
  real y reproducible en el primer despliegue de pruebas (la primerísima
  migración, `create_users_table`, fallaba siempre igual, con el
  síntoma clásico de Postgres "current transaction is aborted" — el
  mensaje genérico que aparece en *cualquier* sentencia posterior a la
  que realmente falló dentro de la misma transacción). Sin
  `DB_HOST_MIGRATE` definido (como en local, donde no existe ningún
  pooler), `pgsql_migrate` cae al mismo `DB_HOST` de siempre — el
  comportamiento local no cambia.
- **Seed solo la primera vez (`php artisan db:seed-if-empty`,
  `App\Console\Commands\SeedIfEmpty`)**: siembra el rol Administrador y
  `admin@uts.edu.co` (`DatabaseSeeder`, que en este entorno solo corre
  `RoleSeeder` y `UserSeeder` — ver 5.7 y el commit de reseed) **solo si
  la tabla `users` está vacía**. Hace falta esta guarda porque Render, en
  el plan gratuito, reinicia el contenedor cada vez que lo "despierta"
  tras un período de inactividad — sin ella, `db:seed` volvería a correr
  en cada arranque. No usa un archivo marcador en el disco del contenedor
  (ej. `storage/.seeded`): al "despertar", Render puede recrear el
  contenedor desde la imagen, sin garantía de que sea el mismo disco de
  antes de dormirse — la única fuente de verdad que persiste de verdad
  entre arranques es la base de datos en Neon, así que `users` vacía o no
  es la señal correcta. Corre por la conexión pooled normal (`pgsql`, sin
  `--database=pgsql_migrate`): son `INSERT` simples vía `firstOrCreate()`,
  no DDL, así que no tienen el problema de transacciones que sí tenía
  `migrate`.
- **`CACHE_STORE=file`, no `database` — mismo bug del pooler que las
  migraciones, esta vez en el rate limiter de login**: confirmado con un
  log real de Render (`SQLSTATE[25P02]: current transaction is aborted`
  en un `UPDATE` sobre la tabla `cache`, al fallar un intento de login).
  `RateLimiter::hit()` (usado por `Login::login()` y
  `ForgotPassword::sendResetLink()` — solo se llama cuando el intento
  falla, por eso el síntoma era "solo la contraseña incorrecta da error
  500") hace un `increment()` atómico que `Illuminate\Cache\DatabaseStore`
  implementa como una transacción explícita (`SELECT ... FOR UPDATE`
  seguido de `UPDATE`, ambos dentro de la misma transacción) — la misma
  forma que rompía `migrate` con el pooler de Neon. Aquí la solución no
  es la conexión directa (`pgsql_migrate`): el caché se usa en cada
  request, sacarlo del pooler por completo no resuelve nada. En vez de
  eso, `CACHE_STORE=file` saca el caché de Postgres del todo — correcto
  para este despliegue de una sola instancia, donde no hay otro
  contenedor con el que compartir el caché de todas formas.
  **Descartado explícitamente que `cache_locks` fuera la causa**: se
  revisó el código fuente de `Illuminate\Cache\RateLimiter` — ninguno de
  sus métodos (`tooManyAttempts`, `hit`, `attempts`, `clear`) usa
  `Cache::lock()`; esa tabla es para locks atómicos, un mecanismo
  distinto que este proyecto no usa en ningún punto.
  **`SESSION_DRIVER=database` no tiene este problema** (se queda igual):
  `Illuminate\Session\DatabaseSessionHandler::write()` hace un `SELECT`
  y, en una llamada aparte, un solo `INSERT` o `UPDATE` — nunca envuelve
  ambos en una transacción explícita, así que no depende de que el
  pooler mantenga la misma sesión de backend entre dos sentencias.
  **`queue:work` (`/cron/process-queue`) corregido preventivamente, sin
  esperar a verlo fallar**: `Illuminate\Queue\DatabaseQueue::pop()`
  (lo que `queue:work` corre en cada job) tiene la misma forma exacta
  que el `increment()` del caché — `SELECT ... FOR UPDATE` + `UPDATE`
  dentro de una única transacción — así que el mismo riesgo con el
  pooler aplica en principio, aunque nunca llegó a fallar en un log
  real. `config/queue.php` ya soporta apuntar la conexión "database" de
  colas a otra conexión de base de datos de forma nativa
  (`'connection' => env('DB_QUEUE_CONNECTION')`), así que no hizo falta
  ningún cambio de código — solo `DB_QUEUE_CONNECTION=pgsql_migrate` en
  el entorno. Verificado en local que la cola queda usando
  `pgsql_migrate` internamente mientras el resto de la app (`DB::connection()`
  por defecto) sigue en `pgsql` sin cruzarse, y que un job real se puede
  encolar y procesar de punta a punta por esa conexión.
- **`MAIL_MAILER=brevo` (API HTTP), no `smtp` — Render bloquea los
  puertos SMTP salientes en su plan gratuito**: confirmado con el log
  real de un intento de "olvidé mi contraseña" en Render, que fallaba
  con error 500 mientras el mismo flujo funcionaba sin problema en
  local. La causa no es un bug de configuración ni de credenciales: es
  una política de la plataforma, documentada en el changelog oficial de
  Render — desde septiembre de 2025, el plan gratuito bloquea todo
  tráfico saliente por los puertos 25, 465 y 587 (los que usa SMTP), sin
  excepción por proveedor. Brevo por SMTP nunca iba a funcionar ahí,
  sin importar qué credenciales se probaran.

  La solución es la API HTTP de Brevo, que corre por HTTPS (puerto 443,
  no bloqueado). Symfony Mailer (la librería que Laravel usa por debajo)
  ya trae soporte para esto vía el paquete oficial
  `symfony/brevo-mailer`: `Symfony\Component\Mailer\Transport` mantiene
  una lista de fábricas de transporte conocidas (`BrevoTransportFactory`
  entre ellas) que detecta automáticamente con `class_exists()` — con
  solo instalar el paquete, `Transport::fromDsn('brevo+api://API_KEY@default')`
  ya sabe resolver ese DSN al transporte correcto
  (`BrevoApiTransport`), sin ningún registro manual adicional. También
  hizo falta instalar `symfony/http-client` aparte (`BrevoApiTransport`
  lo requiere para hacer las peticiones HTTP; no estaba instalado por
  ningún otro paquete del proyecto).

  Del lado de Laravel, `AppServiceProvider::registerBrevoApiMailTransport()`
  conecta ese DSN con el sistema de mailers vía `Mail::extend('brevo',
  ...)` — `MailManager::createSymfonyTransport()` consulta esa lista de
  transportes personalizados antes que sus transportes nativos
  (`smtp`, `ses`, `postmark`...). `config/mail.php` define el mailer
  `brevo` (`'transport' => 'brevo'`) y `config/services.php` guarda la
  API Key (`BREVO_API_KEY`, **no** la clave SMTP que se generó antes —
  son credenciales distintas dentro del mismo panel de Brevo, en
  "SMTP & API" → pestaña "API Keys").

  **Verificado de punta a punta sin tocar el entorno local**: con una
  API Key de prueba (inválida a propósito), un envío real a través del
  mailer `brevo` llegó hasta la API real de Brevo y volvió con
  `HttpTransportException: "Unable to send an email: Key not found
  (code 401)"` — confirma que DNS, TLS y el formato de la petición HTTP
  funcionan correctamente; lo único que falta para un envío real es una
  API Key válida. El mailer `smtp` (Mailpit) sigue intacto en
  `config/mail.php`, y el `.env` local sigue en `MAIL_MAILER=smtp` sin
  ningún cambio — este fix es exclusivo del entorno de Render.
- **Sin worker de colas persistente**: el plan gratuito de Render no
  ofrece un proceso de fondo aparte del propio servicio web. En su lugar,
  `GET /cron/process-queue` (`App\Http\Controllers\Cron\ProcessQueueController`,
  fuera de los grupos de middleware `auth`/`role` en `routes/web.php` —
  quien la llama es un cron externo, nunca una persona autenticada) exige
  un token compartido (`CRON_SECRET`, comparado con `hash_equals()` y que
  falla cerrado si no está configurado) y corre `queue:work
  --stop-when-empty --max-time=50` una sola vez por solicitud. Un cron
  externo gratuito, [cron-job.org](https://cron-job.org), debe golpear
  esa URL cada 2-5 minutos con el token en la query string
  (`?token=...`).
- **Limitación conocida, no cubierta por lo anterior**: `evidences:mark-overdue`
  depende del scheduler de Laravel (`Schedule::command(...)->dailyAt(...)`
  en `routes/console.php`, ver 5.7), que a su vez necesita `schedule:run`
  disparado cada minuto por un cron del sistema operativo — algo que
  tampoco existe en Render free. Este despliegue de pruebas **no**
  marca evidencias vencidas automáticamente; hay que correrlo a mano
  (`php artisan evidences:mark-overdue` vía la shell de Render) o agregar
  un segundo cron externo apuntando a un endpoint equivalente al de la
  cola, si hace falta para la demostración.

### 8.2 Variables de entorno a configurar en Render

Plantilla completa en `.env.render.example` (no se carga automáticamente,
es solo para copiar valores). **Nunca pegar ahí valores reales** — ese
archivo se commitea al repositorio a propósito, como plantilla; los
valores reales van directo al panel de Render. Orden recomendado, para
no ir y venir entre paneles:

1. **Neon**: crear el proyecto/base de datos primero — de su panel salen
   `DB_HOST`, `DB_PORT` (`5432`), `DB_DATABASE`, `DB_USERNAME`,
   `DB_PASSWORD`. Neon **exige** SSL y rechaza la conexión sin esto —
   `config/database.php` toma el modo de `DB_SSLMODE` (`env('DB_SSLMODE',
   'prefer')`, con `'prefer'` de respaldo para que el local, que no lo
   define, siga sin SSL como siempre); en Render hay que fijar
   explícitamente `DB_SSLMODE=require`. El panel de Neon muestra **dos**
   connection strings, "Pooled connection" y una directa sin el sufijo
   `-pooler` en el host — `DB_HOST` es el primero (pooled, para toda la
   app); `DB_HOST_MIGRATE` es el segundo (directo, solo para
   `migrate --force`, ver 8.1).
2. **Brevo**: crear la cuenta y, en "SMTP & API" → pestaña **"API
   Keys"** (aparte de la pestaña "SMTP" — son credenciales distintas),
   generar una nueva API Key — de ahí sale `BREVO_API_KEY`. **No** la
   clave SMTP: Render bloquea esos puertos, ver 8.1.
3. **`APP_KEY`**: generar uno nuevo en local con `php artisan key:generate
   --show` (no reutilizar el de `.env` local) y pegarlo tal cual.
4. **`CRON_SECRET`**: generar un valor aleatorio largo (ej. `php artisan
   tinker --execute="echo Str::random(40);"`) — se pega dos veces: acá y
   en la URL que se configure en cron-job.org.
5. El resto de variables de `.env.render.example` (`APP_ENV=production`,
   `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`,
   `QUEUE_CONNECTION=database`, etc.) son valores fijos, no dependen de
   ninguna cuenta externa — se copian tal cual.
6. Recién con todo lo anterior en el panel, crear el servicio Web en
   Render apuntando a este repositorio (detecta el `Dockerfile`
   automáticamente) y desplegar.
7. Con el servicio ya desplegado y su URL real conocida, volver a entrar
   al panel y corregir `APP_URL` (`.env.render.example` trae un
   placeholder `https://<tu-servicio>.onrender.com`).
8. Configurar el cron externo en cron-job.org apuntando a
   `https://<tu-servicio>.onrender.com/cron/process-queue?token=<CRON_SECRET>`,
   cada 2-5 minutos.

## 9. Alcance explícitamente fuera de este proyecto

Ver la especificación original, sección 3: no incluye módulo de trabajo
de grado, nómina/liquidación de horas, asistencia/notas, repositorio de
producción científica, gestión documental institucional general, ni
integraciones externas (SSO, OneDrive/SharePoint, firma electrónica,
app móvil, analítica avanzada, aprobación multinivel). Ver también el
diccionario de datos para las tres tablas del modelo conceptual original
que no se implementaron (`system_parameters`, `imports`, `notifications`)
y su justificación.
