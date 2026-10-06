# SIGAE-UTS

Sistema de Información para la Gestión de Actividades y Evidencias
Docentes — Institución Universitaria Tecnológica de Santander.

Permite a un docente registrar y enviar evidencia del cumplimiento de sus
actividades (clases, tutorías, investigación, extensión, compromisos
transversales, etc.), y a líderes, Coordinación y Administración
revisarla, aprobarla o devolverla, además de consolidar el avance por
docente, actividad y periodo académico en informes exportables.

## Stack

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.2+ / Laravel 12 |
| Frontend | Blade + Livewire 4 + Tailwind CSS 4 |
| Base de datos | PostgreSQL (local) / Neon PostgreSQL (producción) |
| Correo | Mailpit (local) / Brevo, API HTTP (producción) |
| Colas | Driver `database`; en producción, un cron externo procesa la cola llamando a un endpoint propio protegido por un secreto (sin worker persistente) |
| Alojamiento (producción) | Render, imagen Docker, plan gratuito |

## Levantar el entorno local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate

# crear la base de datos (ver DB_* en .env) y luego:
php artisan migrate --seed

npm run build
php artisan serve
```

Con eso, la aplicación queda en `http://127.0.0.1:8000`. Además:

- **Correo**: en local se entrega a [Mailpit](https://mailpit.axllent.org/)
  (incluido con Laragon) — bandeja web en `http://localhost:8025`, sin
  salir a internet.
- **Notificaciones de evidencias**: van en cola; hace falta dejar
  corriendo `php artisan queue:work` en otra terminal para que se
  procesen y lleguen a Mailpit.

Instrucciones completas (requisitos previos, base de datos de pruebas,
arquitectura, decisiones de diseño) en `docs/manual-tecnico.md`.

## Acceso inicial

`php artisan migrate --seed` siembra un único usuario Administrador
(`admin@uts.edu.co`), con la contraseña que definas en la variable de
entorno `ADMIN_INITIAL_PASSWORD` (ver `.env.example`) — nunca un valor
fijo en el código ni en este archivo. Sin esa variable, el seeder falla
con un mensaje claro en vez de usar cualquier contraseña por defecto.

Desde esa cuenta se crean los demás usuarios, periodos, catálogos y
entregables del sistema.

## Despliegue en producción

Entorno de pruebas sobre servicios gratuitos (Render + Neon + Brevo) —
instrucciones completas, en orden, en "Despliegue en entorno de pruebas"
dentro de `docs/manual-tecnico.md`. Resumen:

- **Cómo se despliega**: un push a `master` hace que Render construya la
  imagen del `Dockerfile` (compila los assets de Vite y las dependencias
  de PHP) y la ponga en marcha — no hay un paso de despliegue manual
  aparte.
- **Qué hace `docker/start.sh` al arrancar el contenedor**: cachea
  configuración/rutas/vistas (`config:cache`, `route:cache`,
  `view:cache`), corre `php artisan migrate --force` contra la conexión
  directa de Neon, y siembra el rol Administrador y el usuario
  administrador inicial **solo si la tabla `users` está vacía**
  (`db:seed-if-empty`) — así un reinicio del contenedor nunca repite la
  siembra.
- **Administrador inicial**: la misma regla que en local — su contraseña
  sale de `ADMIN_INITIAL_PASSWORD`, puesta en el panel de variables de
  entorno de Render. Sin ella, el seeder falla en vez de usar cualquier
  valor por defecto.

### Variables de entorno de producción

Plantilla completa (nombres, orden recomendado y de dónde sale cada
valor) en `.env.render.example` — nunca se le pegan valores reales, esos
van directo al panel de Render. Las más importantes:

| Variable | Qué es |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` — con `true`, un error expondría detalles del servidor |
| `APP_KEY` | Clave de cifrado de la aplicación |
| `APP_URL` | URL pública real del servicio una vez desplegado |
| `ADMIN_INITIAL_PASSWORD` | Contraseña del Administrador inicial (ver arriba) |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSLMODE` | Credenciales de conexión a Neon |
| `DB_HOST_MIGRATE` | Host de conexión directa de Neon, solo para migraciones (ver nota abajo) |
| `MAIL_MAILER`, `BREVO_API_KEY`, `MAIL_FROM_ADDRESS` | Envío de correo vía la API HTTP de Brevo |
| `CRON_SECRET` | Token que protege el endpoint que procesa la cola |

**Nota sobre Neon**: las migraciones corren contra el host de conexión
**directa** (sin el sufijo `-pooler`), no el host agrupado que usa el
resto de la app — el modo de agrupación de conexiones de Neon puede
dejar desincronizado el plan de una consulta en caché después de alterar
una tabla (la familia de error típica de esto es algo como `cached plan
must not change result type`); el síntoma real que se registró en este
proyecto al migrar contra el host agrupado fue distinto pero relacionado
(`current transaction is aborted`) — ver `docs/manual-tecnico.md` §8.1
para el detalle completo.

### Limitaciones conocidas del plan gratuito

- **El servicio se suspende por inactividad**: tras un período sin
  tráfico, Render "duerme" el contenedor: la primera carga después de
  eso puede tardar cerca de un minuto en responder mientras arranca de
  nuevo.
- **Los archivos subidos (evidencias, logos) no tienen persistencia
  garantizada**: el disco del contenedor puede recrearse desde la imagen
  al despertar, sin garantía de ser el mismo de antes de dormirse — solo
  la base de datos en Neon persiste de forma confiable. Para producción
  real, la opción es almacenamiento externo (ej. S3) o un disco
  persistente de Render.
- **`evidences:mark-overdue` no se ejecuta solo**: depende del
  scheduler de Laravel, que a su vez necesita un cron de sistema
  operativo (`schedule:run` cada minuto) que este plan no ofrece — hay
  que correrlo a mano o agregar un cron externo equivalente al de la
  cola.

### Verificación posterior al despliegue

- Iniciar sesión con la cuenta Administrador y la contraseña de
  `ADMIN_INITIAL_PASSWORD`.
- Confirmar en los logs de Render que `migrate --force` corrió sin
  errores.
- Visitar el endpoint que procesa la cola (requiere el token de
  `CRON_SECRET` en la URL) y confirmar que responde correctamente, no
  con 403.

## Documentación

- `docs/manual-tecnico.md` — arquitectura, instalación, decisiones técnicas.
- `docs/manual-usuario.md` — guía de uso por rol.
- `docs/manual-diseno.md` — sistema de diseño (paleta, tipografía, componentes).
- `docs/diccionario-datos.md` — esquema de base de datos.

## Pruebas

```bash
php artisan test
```

Usa una base de datos separada — ver "Base de datos de pruebas
automatizadas" en `docs/manual-tecnico.md`.
