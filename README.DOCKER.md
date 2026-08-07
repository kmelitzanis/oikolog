# Running Oikolog in Docker (development)

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
- MySQL is configured with root/secret and database `oikolog` in `docker-compose.yml`. Adjust `.env` and
  `docker-compose.yml` to match your preferred credentials.
- The application code is **baked into the image**, not mounted. Only `storage`, `bootstrap/cache` and `public` are
  named volumes. Editing files on the host does nothing until you rebuild the image — and there is no Laravel `.env`
  inside the container: configuration comes from the `environment:` block, which the entrypoint freezes with
  `config:cache` on every start.

Configuration:

- Values in the `environment:` block use `${VAR}`, which compose reads from the `.env` sitting **next to
  `docker-compose.yml`**. That file is compose's env file, not Laravel's.
- After changing it, recreate the container so the entrypoint re-caches the config:

```bash
docker compose up -d --force-recreate app
```

Web Push notifications:

- Generate a VAPID key pair once (anywhere — the keys are not tied to a machine):

```bash
docker compose exec app php artisan push:vapid
```

- Put the three printed values into the compose `.env` as `VAPID_SUBJECT`, `VAPID_PUBLIC_KEY` and
  `VAPID_PRIVATE_KEY`, then recreate the `app` container. Without them, the notification toggle in Settings stays
  disabled. Changing them later invalidates every existing browser subscription.
- Push and service workers need a secure context. `localhost` is exempt, but reaching the NAS over plain
  `http://192.168.x.x` is **not** — notifications and PWA install will silently fail for anyone on the LAN until the
  app is served over HTTPS.

Troubleshooting:

- If you hit permission issues, ensure the `storage` and `bootstrap/cache` directories are writable by the container
  user. The entrypoint sets these to `www-data:www-data`.
- For production use you'll want to build assets, run migrations in a safe way, and configure a proper secret APP_KEY.
