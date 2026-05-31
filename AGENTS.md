# AGENTS.md — Quick guide for AI coding agents

Checklist (what this file helps you do):

- [ ] Understand the big-picture architecture and where to make changes
- [ ] Run and reproduce developer workflows (dev server, tests, Docker)
- [ ] Locate patterns and conventions (translations, auth, policies)
- [ ] Identify integration points and external dependencies

Purpose
This project is a Laravel 11 application (PHP 8.2) that manages household bills, incomes, shopping lists and products.
AGENTS should be aware of high-level flows, common places to change behavior, and project-specific conventions so they
can propose and implement safe, minimal changes.

Quick facts

- Framework: Laravel 11 (see `composer.json`)
- PHP min: ^8.2 (`composer.json`)
- Frontend: Tailwind, Alpine, Vite (`tailwind.config.js`, `vite.config.js`, `package.json`)
- Auth: Laravel Sanctum is used for API tokens (`composer.json`, `app/Models/User.php`). A small compatibility fallback
  lives at `app/Support/sanctum_compat.php`.
- Database: MySQL by default for Docker (`docker-compose.yml`) but local dev supports SQLite (see `.env.example` usage
  in `README.md` and `database/database.sqlite`).

Big picture architecture (where code lives)

- HTTP web UI: `routes/web.php` → controllers in `app/Http/Controllers/Web/` → Blade views in `resources/views/`.
- API: `routes/api.php` → controllers in `app/Http/Controllers/Api/`. API uses `auth:sanctum,web` middleware for
  authenticated routes.
- Domain models: `app/Models/` (e.g., `Bill`, `Income`, `ShoppingList`, `Product`, `Translation`, `User`). Use Eloquent;
  factories live in `database/factories/`.
- Business services: `app/Services/` (example: `NutritionApiService.php` calls Open Food Facts).
- Translation system: file-based messages + DB overrides via `app/Translation/DatabaseLoader.php` and
  `database/seeders/TranslationSeeder.php`. Translations management UI at `/translations` (controller under `Web`) — DB
  entries override files.
- Policies & auth: `app/Policies/` (e.g., `ShoppingListPolicy.php`), middleware in `app/Http/Middleware/` (e.g.,
  `SetLocale.php`, `AdminMiddleware.php`). Locale list is limited (`SetLocale::$available = ['en','el']`).

Key developer workflows (commands and tips)

- Install deps: `composer install` and `npm install` (see `README.md`). Composer scripts auto-copy `.env.example` and
  run `artisan key:generate` on project creation.
- Local dev server: `php artisan serve` (or use Docker `docker-compose up`).
- Database bootstrap (local SQLite):
    - Copy env: `cp .env.example .env` and set `DB_CONNECTION=sqlite` / `DB_DATABASE=database/database.sqlite`.
    - Migrate & seed translations: `php artisan migrate` then
      `php artisan db:seed --class="Database\Seeders\TranslationSeeder"`.
- Storage: `php artisan storage:link` to expose uploaded avatars/public files.
- Frontend build: `npm run build` (or `npm run dev` during development with Vite).
- Tests: `php artisan test` (Pest & PHPUnit configured). Tests run with in-memory SQLite per `phpunit.xml` (no DB file
  needed).
- Docker: `docker-compose.yml` defines `app`, `webserver`, and `db` (MySQL 8.1). App image
  `kostasmel/oikolog-app:latest` is used in compose; the compose file expects MySQL env vars. Healthchecks are present;
  migrations can be forced via `FORCE_MIGRATE` env.

Project-specific conventions & patterns (examples)

- Translation override: The runtime translation loader merges file translations with DB translations; DB entries win.
  See `app/Translation/DatabaseLoader.php` and `TranslationSeeder.php`.
- Locale selection priority: session > authenticated user's `locale` > app default. Valid locales limited to
  `['en','el']`. See `app/Http/Middleware/SetLocale.php`.
- Safe optional features: code avoids fatal errors when optional packages are missing — e.g.
  `app/Support/sanctum_compat.php` defines a no-op trait when Sanctum isn't autoloaded; `User::registerMediaConversions`
  and `avatarUrl` check `method_exists` for media library integration.
- Admin gating: admin UI routes use `admin` middleware (`app/Http/Middleware/AdminMiddleware.php`) which expects
  `User::isAdmin()`.
- Route ordering matters: e.g., `routes/web.php` defines `/bills/events` before resource-like bill routes to avoid route
  parameter collision. When adding routes, preserve ordering to avoid shadowing.
- Controllers use grouped `Route::controller(...)->prefix(...)->name(...)` patterns in `web.php`; mirror this style for
  new web routes.

Integration points & external dependencies

- External APIs: Open Food Facts via `NutritionApiService` (HTTP timeouts, error logging). Replace or mock in tests by
  faking `Http` facade.
- Media handling: `spatie/laravel-medialibrary` optionally used. Code checks for presence before calling methods.
- 2FA: `pragmarx/google2fa-laravel` is included; two-factor setup controllers exist under `Web`.
- Queues, cache, third-party services: configured via `.env` and `config/` files. Tests set many services to
  in-memory/null to simplify CI (`phpunit.xml`).

Where to look for work you might be asked to do

- Adding a new API endpoint → `routes/api.php` + `app/Http/Controllers/Api/` + policies/tests in `tests/Feature`.
- Changing translation text → `resources/lang/*/messages.php` or DB via `Translation` model; use `TranslationSeeder` to
  seed file values into DB for editing.
- Changes to auth/permissions → `app/Models/User.php`, `app/Policies/`, `app/Http/Middleware/`.
- Frontend UX updates → `resources/js/`, `resources/css/`, and `vite.config.js` (rebuild with `npm run build` or
  `npm run dev`).

Testing & safe iteration tips

- Run unit/feature tests locally: `php artisan test`. Tests use in-memory SQLite; avoid changing `phpunit.xml` unless
  you know the implications.
- To mock external HTTP calls use `Illuminate\Support\Facades\Http::fake()` in tests (NutritionApiService uses `Http`
  facade).
- When changing DB structure, create migrations and update `database/factories` and `database/seeders` accordingly; run
  `php artisan migrate --seed` in dev.

Files/paths of highest importance (quick reference)

- Routes: `routes/web.php`, `routes/api.php`
- Controllers: `app/Http/Controllers/` (Web and Api subfolders)
- Models: `app/Models/`
- Services: `app/Services/`
- Translation loader & seeder: `app/Translation/DatabaseLoader.php`, `database/seeders/TranslationSeeder.php`
- Middleware: `app/Http/Middleware/`
- Tests: `tests/Feature/`, `tests/Unit/`, `phpunit.xml`
- Dev configs: `composer.json`, `package.json`, `docker-compose.yml`, `vite.config.js`

If in doubt

- Read `routes/*.php` to find the entrypoint for behavior you want to change.
- Follow the existing route/controller/middleware/policy pattern rather than adding ad-hoc checks.
- Preserve graceful fallbacks (see `sanctum_compat.php` and `User::avatarUrl`).

This file is intentionally concise. If you want, I can:

- Expand this into a checklist with exact files to edit for common tasks (create API endpoint, add translation, add UI
  page)
- Generate starter test cases or patch a sample feature end-to-end

---
Generated on: 2026-05-31

