# syntax=docker/dockerfile:1
#
# Production multi-stage Dockerfile for Google Cloud Run (PHP 8.4 + FrankenPHP)
# Governed by ADR-001 and ADR-016.

# ---- Stage 1: PHP Dependencies ----
FROM composer:2 AS vendor
WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --ignore-platform-reqs \
    --optimize-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ---- Stage 2: Production Runtime ----
FROM dunglas/frankenphp:1-php8.4-bookworm AS runtime

# Install production PHP extensions for Neon Postgres, SQLite, and Filament
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    pdo_sqlite \
    gd \
    zip \
    intl \
    bcmath \
    opcache \
    pcntl

WORKDIR /app

COPY --from=vendor /app /app

RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

ENV PORT=8080
EXPOSE 8080

CMD ["frankenphp", "php-server", "--listen", ":8080", "--root", "/app/public"]
