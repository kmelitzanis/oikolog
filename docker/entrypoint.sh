#!/bin/sh
set -e

# Cache config/routes/views for production
if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Run migrations if FORCE_MIGRATE=1
if [ "${FORCE_MIGRATE}" = "1" ]; then
    php artisan migrate --force
fi

# Fix storage permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

exec "$@"
