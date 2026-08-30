#!/bin/sh
set -e

# Pre-warm Laravel and Filament caches on production cold-starts
if [ "$APP_ENV" = "production" ] || [ -n "$DB_URL" ] || [ -n "$DATABASE_URL" ]; then
    echo "⚡ Warming production caches (config, routes, views, filament)..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
    php artisan event:cache || true
    php artisan filament:optimize || true
fi

# Pass-through to arguments if provided (e.g. migration jobs or queue workers)
if [ $# -gt 0 ]; then
    exec "$@"
else
    exec php artisan octane:start \
        --server=frankenphp \
        --host=0.0.0.0 \
        --admin-port=2019 \
        --port="${PORT:-8080}"
fi