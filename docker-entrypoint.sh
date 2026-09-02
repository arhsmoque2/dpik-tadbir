#!/bin/sh
set -e

# Pre-warm Laravel and Filament caches on production cold-starts
if [ "$APP_ENV" = "production" ] || [ -n "$DB_URL" ] || [ -n "$DATABASE_URL" ]; then
    echo "⚡ Warming production runtime caches..."
    php artisan optimize || true
    php artisan filament:optimize || true
fi

# Pass-through to arguments if provided (e.g. migration jobs or queue workers)
if [ $# -gt 0 ]; then
    exec "$@"
else
    exec frankenphp run --config /app/Caddyfile --adapter caddyfile
fi