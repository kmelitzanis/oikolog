# Running bills-log in Docker (development)

This repository includes a simple Docker setup for local development.

Prerequisites:

- Docker and docker-compose installed

Quick start:

1. Copy your `.env` from `.env.example` and set values. For local testing the compose file provides defaults.

2. Build and start the containers:

```bash
docker compose up --build -d
```

3. The web app will be available at: http://localhost:8000

Notes:

- The `app` service uses PHP-FPM and runs an entrypoint that will run `composer install` if `vendor` doesn't exist and (
  optionally) run migrations when `APP_ENV=local` or `FORCE_MIGRATE=1`.
- MySQL is configured with root/secret and database `bills_log` in `docker-compose.yml`. Adjust `.env` and
  `docker-compose.yml` to match your preferred credentials.
- Volumes mount your project into the container so changes on disk are visible immediately.

Troubleshooting:

- If you hit permission issues, ensure the `storage` and `bootstrap/cache` directories are writable by the container
  user. The entrypoint sets these to `www-data:www-data`.
- For production use you'll want to build assets, run migrations in a safe way, and configure a proper secret APP_KEY.

