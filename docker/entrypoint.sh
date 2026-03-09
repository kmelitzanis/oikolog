#!/usr/bin/env bash
set -e

# Ensure composer dependencies are installed
if [ ! -d "vendor" ]; then
  composer install --no-interaction --prefer-dist
fi

# Optionally run migrations in development
if [ "$APP_ENV" = "local" ] || [ "$FORCE_MIGRATE" = "1" ]; then
  php artisan migrate --force || true
fi

# Set permissions for Laravel
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R 775 storage bootstrap/cache || true

exec "$@"

