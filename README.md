# SIGAE-UTS

Sistema de Información para la Gestión de Actividades y Evidencias
Docentes — Unidades Tecnológicas de Santander.

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
| Base de datos | PostgreSQL |
| Correo (local) | Mailpit |
| Colas | Driver `database` |

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

`php artisan migrate --seed` siembra un único usuario Administrador:

| Correo | Contraseña |
|---|---|
| `admin@uts.edu.co` | `***REMOVED***` |

Desde esa cuenta se crean los demás usuarios, periodos, catálogos y
entregables del sistema.

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
