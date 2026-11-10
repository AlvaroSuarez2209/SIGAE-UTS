#!/bin/sh
set -e

# APP_KEY debe llegar ya generado como variable de entorno de Render (ver
# "Despliegue en entorno de pruebas" en docs/manual-tecnico.md). Sin él,
# Laravel no puede cifrar sesiones/cookies — mejor fallar aquí, de forma
# visible en el log de arranque, que dejar que la app arranque rota.
if [ -z "$APP_KEY" ]; then
    echo "ERROR: falta APP_KEY. Generar uno con 'php artisan key:generate --show' (en local) y pegarlo en las variables de entorno de Render." >&2
    exit 1
fi

# Cachear config/rutas/vistas recién ahora, con las variables de entorno
# reales de Render ya presentes — nunca en el Dockerfile (ahí todavía no
# existen, ver comentario en esa sección).
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migra la base de Neon automáticamente en cada arranque — idempotente
# (Laravel solo corre las migraciones pendientes), así que es seguro que
# esto se repita en cada despliegue, no solo en el primero.
#
# --database=pgsql_migrate: la conexión directa a Neon (sin "-pooler"),
# nunca la pooled que usa el resto de la app — ver config/database.php y
# "Despliegue en entorno de pruebas" en docs/manual-tecnico.md.
php artisan migrate --force --database=pgsql_migrate

# Siembra el rol Administrador y el usuario admin@uts.edu.co UNA sola vez
# — db:seed-if-empty comprueba que la tabla users esté vacía antes de
# correr db:seed, así que reiniciar el contenedor (Render lo hace cada
# vez que el plan gratuito lo "despierta" tras inactividad) nunca vuelve
# a sembrar. Corre por la conexión pooled normal (sin --database=
# pgsql_migrate): son INSERT simples, no DDL, así que no tienen el
# problema de transacciones que sí tenía migrate. Ver
# App\Console\Commands\SeedIfEmpty y "Despliegue en entorno de pruebas"
# en docs/manual-tecnico.md.
php artisan db:seed-if-empty

# Cambio (vaciado inmediato de la cola de correo): sin esto, "php artisan
# serve" corre UN solo proceso — mientras atiende el vaciado de la cola
# tras una acción de usuario (hasta MAIL_QUEUE_IMMEDIATE_FLUSH_SECONDS,
# ver config/queue_flush.php), ese único proceso no podía atender a
# NINGÚN otro usuario. PHP_CLI_SERVER_WORKERS (nativo del servidor
# embebido de PHP 8.3+, que ya usa esta imagen) lo reparte entre varios
# procesos; Laravel solo lo respeta junto con --no-reload (ver
# Illuminate\Foundation\Console\ServeCommand::initialize() — sin esa
# flag, ignora la variable y avisa que vuelve a un solo proceso).
# --no-reload además es lo correcto para un contenedor: ServeCommand
# solo la usa para reiniciarse si detecta cambios en el .env local, algo
# que no pasa (ni tiene sentido) corriendo ya empaquetado en una imagen.
#
# Por qué 3 es seguro para sesión/caché/cola compartidas entre procesos:
# SESSION_DRIVER=database y QUEUE_CONNECTION=database viven en Neon
# (comparten estado entre los 3 sin problema, cada fila se reserva/lee
# de forma atómica). CACHE_STORE=file (ver docs/manual-tecnico.md §8,
# "CACHE_STORE=file, no database") vive en el disco local del propio
# contenedor — Cache::lock() con ese driver usa flock(), que coordina
# correctamente entre procesos del mismo contenedor igual que entre
# hilos de uno solo, así que QueueFlusher (App\Services\Mail\QueueFlusher)
# sigue evitando dos vaciados a la vez aunque corran en procesos
# distintos.
#
# Memoria (estimado, no medido en el plan real de Render): cada proceso
# es un intérprete PHP 8.3 completo con las extensiones de esta imagen
# (pdo_pgsql, zip, gd, intl, mbstring) — unos 20-30MB en reposo cada uno,
# más el pico de bootstrap de Laravel+Livewire por request (30-60MB,
# más si esa request genera un PDF/Excel). Con 3 procesos, el peor caso
# razonable (los 3 atendiendo a la vez algo pesado) ronda 250-300MB,
# dentro del límite de 512MB de los planes Starter/Free de Render, pero
# sin margen amplio — revisa la pestaña "Metrics" de Render tras el
# primer despliegue con este cambio; si ves memoria cerca del límite o
# reinicios por OOM, baja PHP_CLI_SERVER_WORKERS a 2 (variable de
# entorno en el panel de Render, no hace falta tocar este archivo).
#
# Cómo cambiar el número de procesos: variable de entorno
# PHP_CLI_SERVER_WORKERS en el panel de Render (o en .env.render para
# referencia local) — "${PHP_CLI_SERVER_WORKERS:-3}" usa 3 si no está
# definida, cualquier otro valor la sobrescribe sin tocar este script.
#
# Cómo cambiar el tiempo máximo del vaciado inmediato: variable de
# entorno MAIL_QUEUE_IMMEDIATE_FLUSH_SECONDS (ver config/queue_flush.php,
# por defecto 10s) — no tiene relación con PHP_CLI_SERVER_WORKERS, cada
# una se ajusta por separado.
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-3}"

# Render decide el puerto real vía $PORT en tiempo de ejecución; 8080 es
# solo el valor de respaldo si esa variable no llegara a existir.
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}" --no-reload
