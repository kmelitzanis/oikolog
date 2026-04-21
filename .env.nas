# Copy this file to .env on your NAS and fill in the values

APP_NAME=Oikolog
APP_ENV=production
APP_KEY=                          # Run: php artisan key:generate --show
APP_DEBUG=false
APP_URL=http://your-nas-ip:8000

DB_DATABASE=oikolog
DB_USERNAME=oikolog
DB_PASSWORD=changeme_strong_password
DB_ROOT_PASSWORD=changeme_root_password

NGINX_PORT=8000
MYSQL_PORT=3306

FORCE_MIGRATE=1

