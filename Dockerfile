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

# ---- Stage 2: Frontend Assets ----
# Compiles the Filament admin panel's Vite/Tailwind theme
# (resources/css/filament/admin/theme.css) into public/build. Without this
# stage, the runtime image ships only Filament's stock pre-bundled CSS and
# every custom `bg-[#212631]`-style class in this app's own Blade views and
# Filament PHP classes renders as plain unstyled HTML.
FROM node:22-bookworm-slim AS assets
WORKDIR /app
RUN corepack enable && corepack prepare pnpm@9.15.9 --activate

COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY . .
COPY --from=vendor /app/vendor /app/vendor
RUN pnpm run build

# ---- Stage 3: Production Runtime ----
FROM dunglas/frankenphp:1-php8.4-bookworm AS runtime

# Install production PHP extensions for Neon Postgres, SQLite, Filament, and
# Octane. imap is required by MailBridge (app/Services/Mail/MailBridge.php)
# — the company IMAP/SMTP mailbox bridge that replaced the Outlook MCP
# Python subprocess (see issue #40).
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    pdo_sqlite \
    gd \
    zip \
    intl \
    bcmath \
    opcache \
    pcntl \
    imap

# Production PHP & OPcache tuning for Laravel Octane + FrankenPHP on Cloud Run
COPY php/local.ini /usr/local/etc/php/conf.d/local.ini
COPY php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /app

COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build
COPY docker-entrypoint.sh /app/docker-entrypoint.sh

RUN sed -i 's/\r$//' /app/docker-entrypoint.sh \
    && chmod +x /app/docker-entrypoint.sh \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache

ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["/app/docker-entrypoint.sh"]
CMD []
