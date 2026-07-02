#!/bin/sh
# Note: intentionally NOT using `set -e`. A failing migration or cache step
# should log loudly but still let php-fpm start, so the container stays UP and
# debuggable (exec in and run the failing command) instead of crash-looping as
# "unhealthy".

echo "⏳ Waiting for database..."
until php artisan db:monitor --max=1 2>/dev/null || \
      php -r "new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
  echo "  DB not ready, retrying in 3s..."
  sleep 3
done
echo "✅ Database is ready"

if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache || echo "⚠️  config:cache failed (continuing)"
    php artisan route:cache  || echo "⚠️  route:cache failed (continuing)"
    php artisan view:cache   || echo "⚠️  view:cache failed (continuing)"
fi

if [ "${FORCE_MIGRATE}" = "1" ]; then
    echo "▶️  Running migrations..."
    if php artisan migrate --force; then
        echo "✅ Migrations complete"
    else
        echo "⚠️  Migrations FAILED — starting app anyway so it can be inspected."
        echo "    Run:  php artisan migrate --force   inside the container to see the error."
    fi
fi

php artisan storage:link --force 2>/dev/null || true

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

exec "$@"
