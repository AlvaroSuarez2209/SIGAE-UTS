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
php artisan migrate --force

# Render decide el puerto real vía $PORT en tiempo de ejecución; 8080 es
# solo el valor de respaldo si esa variable no llegara a existir.
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
