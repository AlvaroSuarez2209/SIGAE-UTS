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

### 5.5 Estructura de carpetas relevantes

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
