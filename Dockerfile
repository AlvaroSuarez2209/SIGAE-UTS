# syntax=docker/dockerfile:1
#
# Imagen para desplegar SIGAE-UTS en Render (plan gratuito) contra una
# base de datos Neon Postgres — ver "Despliegue en entorno de pruebas" en
# docs/manual-tecnico.md para la lista completa de variables de entorno.
#
# No usa Nginx/PHP-FPM a propósito: `php artisan serve` es suficiente
# para un entorno de pruebas de un solo contenedor (Render solo necesita
# un proceso escuchando en $PORT), y evita configurar y mantener un
# segundo servicio dentro de la misma imagen solo para esto. No es la
# forma de servir tráfico de producción real a alta concurrencia.

# ---- Etapa 1: compilar los assets de Tailwind/Vite para producción ----
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources/ resources/
# "npm run build" (no "npm run dev"): genera public/build/ minificado y
# con hash de caché, vía el plugin oficial de Laravel para Vite — el
# mismo comando que ya se documenta para producción local en el manual
# técnico, solo que aquí corre dentro de la imagen.
RUN npm run build

# ---- Etapa 2: aplicación PHP ----
FROM php:8.3-cli-bookworm AS app
WORKDIR /var/www/html

# Extensiones que Laravel necesita (ver "Requisitos previos" en
# docs/manual-tecnico.md): pdo_pgsql/pgsql para PostgreSQL (Neon), zip y
# gd para los informes Excel/PDF (maatwebsite/excel, barryvdh/laravel-dompdf),
# intl y mbstring para el idioma es_CO.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libonig-dev \
        unzip \
        git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql zip gd intl mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copiar primero solo los manifiestos para aprovechar la caché de capas de
# Docker — esta instalación de dependencias solo se repite si
# composer.lock cambia, no en cada cambio de código de la aplicación.
# --no-scripts es obligatorio aquí: composer.json dispara
# "post-autoload-dump" -> "php artisan package:discover" automáticamente,
# y en este punto todavía no existe "artisan" (recién llega con el
# COPY . . de abajo) — sin --no-scripts, ese hook falla con
# "Could not open input file: artisan" y aborta todo el build.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction

COPY . .
COPY --from=assets /app/public/build public/build

# Recién ahora, con "artisan" y el resto de la app ya presentes, se genera
# el autoload optimizado — esto sí dispara "post-autoload-dump" (y por lo
# tanto "package:discover"), y esta vez encuentra "artisan" sin problema.
RUN composer dump-autoload --optimize --no-interaction

# storage/ y bootstrap/cache/ deben quedar escribibles por el usuario con
# el que corre la app (ver USER más abajo) — logs, sesiones, vistas
# compiladas y los archivos privados de evidencias viven ahí.
RUN chown -R www-data:www-data storage bootstrap/cache

COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

USER www-data

# Render asigna el puerto real en la variable de entorno $PORT en tiempo
# de ejecución (no en el build) — este EXPOSE es solo documentación;
# docker/start.sh es quien realmente decide en qué puerto escuchar.
EXPOSE 8080

ENTRYPOINT ["start.sh"]
