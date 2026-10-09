# syntax=docker/dockerfile:1

# Imagen de producción de "Seguimiento de Estudiantes".
#
# Elegimos serversideup/php:8.3-fpm-nginx (sección 9 del encargo) en vez de FrankenPHP porque:
#   - Empaqueta NGINX + PHP-FPM supervisados por S6 Overlay en una sola imagen, que es
#     justamente el formato pedido ("Nginx + PHP-FPM").
#   - Ya corre como usuario sin privilegios (www-data) en el puerto 8080 por defecto.
#   - Trae un script de automatización para Laravel (variables AUTORUN_*) que ejecuta
#     migrate, config:cache, route:cache, view:cache y storage:link al arrancar, sin
#     necesidad de un entrypoint propio.
#   - Expone un healthcheck nativo contra una ruta HTTP (la apuntamos a /up de Laravel).
#   - FrankenPHP (modo worker) exige más cuidado con el estado entre peticiones; para una
#     aplicación Inertia+React con sesiones y colas clásicas, FPM es la opción más simple
#     y probada en producción.
#
# Build multi-etapa: 1) dependencias PHP, 2) assets de Vite/React, 3) imagen final mínima.

ARG PHP_VERSION=8.3

# ---------------------------------------------------------------------------
# Etapa 1: dependencias de Composer (sin dev, autoloader optimizado)
# ---------------------------------------------------------------------------
FROM serversideup/php:${PHP_VERSION}-cli AS vendor
USER root
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --optimize-autoloader \
        --no-interaction

COPY . .
RUN composer dump-autoload --no-dev --optimize

# ---------------------------------------------------------------------------
# Etapa 2: assets de frontend (Vite + React + Tailwind)
# ---------------------------------------------------------------------------
FROM node:22-slim AS assets
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
# Necesita vendor (Ziggy/composer autoload no hace falta para el build de Vite,
# pero copiamos el árbol completo para que vite.config.js y los imports resuelvan igual).
RUN npm run build

# ---------------------------------------------------------------------------
# Etapa 3: imagen final
# ---------------------------------------------------------------------------
FROM serversideup/php:${PHP_VERSION}-fpm-nginx AS final

USER root

# Extensiones requeridas por la aplicación (sección 2 y 9 del encargo).
# install-php-extensions no reinstala las que ya vienen en la imagen base.
RUN install-php-extensions \
        pdo_mysql \
        gd \
        zip \
        intl \
        bcmath \
        exif \
        opcache

# mysqldump, para la copia de seguridad diaria (app:respaldar-base-datos / scheduler).
RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build

# El healthcheck nativo de la imagen hace una petición HTTP a esta ruta; /up es la
# ruta de salud de Laravel (bootstrap/app.php), extendida en
# app/Listeners/CheckDatabaseHealth.php para fallar también si MySQL no responde.
ENV HEALTHCHECK_PATH=/up

# Automatizaciones de Laravel al arrancar (ver docs de serversideup/php):
# equivalen a migrate --force + config:cache + route:cache + view:cache + storage:link.
ENV AUTORUN_ENABLED=true
ENV AUTORUN_LARAVEL_MIGRATION=true
ENV AUTORUN_LARAVEL_MIGRATION_FORCE=true
ENV AUTORUN_LARAVEL_STORAGE_LINK=true
ENV AUTORUN_LARAVEL_CONFIG_CACHE=true
ENV AUTORUN_LARAVEL_ROUTE_CACHE=true
ENV AUTORUN_LARAVEL_VIEW_CACHE=true
ENV AUTORUN_LARAVEL_EVENT_CACHE=true

USER www-data

EXPOSE 8080
