#!/bin/sh
set -e

if [ ! -f ".env" ]; then
    echo ".env not found. Copying from .env.example..."
    cp .env.example .env
fi

if [ ! -d "vendor" ]; then
    echo "Vendor folder is empty. Installing dependencies..."
    composer install --no-interaction --optimize-autoloader
else
    echo "Vendor folder exists. Syncing..."
    composer install --no-interaction --optimize-autoloader
fi

# Run database setup only when explicitly requested.
# This prevents php-fpm startup failures (and 502 from nginx) when DB is not ready yet.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    php artisan db:seed --force
fi

exec "$@"
