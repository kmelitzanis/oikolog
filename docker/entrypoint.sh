#!/bin/sh
set -e

echo "⏳ Waiting for database..."
until php artisan db:monitor --max=1 2>/dev/null || \
      php -r "new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
  echo "  DB not ready, retrying in 3s..."
  sleep 3
done
echo "✅ Database is ready"

if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

if [ "${FORCE_MIGRATE}" = "1" ]; then
    php artisan migrate --force
fi

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

exec "$@"
