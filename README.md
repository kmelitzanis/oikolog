<p align="center">
  <img src="public/icons/icon-192.png" width="96" height="96" alt="Oikolog">
</p>

<h1 align="center">Oikolog</h1>

<p align="center">
  Household bills, income, shopping and meal planning — for the whole family, in one place.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-%5E8.2-777BB4?logo=php&logoColor=white" alt="PHP ^8.2">
  <img src="https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white" alt="Laravel 11">
  <img src="https://img.shields.io/badge/Tailwind-4-06B6D4?logo=tailwindcss&logoColor=white" alt="Tailwind 4">
  <img src="https://img.shields.io/badge/license-GPL--3.0-blue" alt="GPL-3.0">
</p>

---

Oikolog keeps track of what a household actually spends and owes. Recurring bills know when they are next due,
payments (including partial ones) are recorded against them, and everything can be shared with the rest of the
family. Around that sits the day-to-day: shopping lists, recipes and a weekly meal plan that can push its
ingredients straight into a list.

## Features

**Money**

- Recurring and one-off bills — weekly through yearly, with automatic next-due-date calculation
- Full and **partial** payments, with per-payment history and one-click undo
- Bills with a variable amount ("cost varies") — enter the real figure at payment time
- Income sources with expected dates, and a month-to-date "received" view
- Dashboard: net for the month, what needs attention now, six-month trend and spend by category
- Month overview — the month as a countdown line, showing what is left to pay
- Calendar with per-day status indicators (overdue / due soon / paid / upcoming)
- Receipts and attachments on any bill

**Household**

- Family groups with an invite code, owner/member roles and ownership transfer
- Shared bills visible to every member, with a recent-activity feed
- Shopping lists with quantities, barcode lookup and a product catalogue
- Recipes with ingredients, steps and timings
- Weekly meal planner that can send a week's ingredients to a shopping list

**Platform**

- Installable PWA with offline fallback and a service worker
- Greek and English throughout, with database-backed translation overrides you can edit in the UI
- Two-factor authentication (TOTP) with QR enrolment
- Role-based admin area for categories, providers, products and users
- Light and dark themes

## Tech stack

| Layer    | Choice                                              |
|----------|-----------------------------------------------------|
| Backend  | Laravel 11, PHP 8.2+                                |
| Frontend | Blade, Alpine.js, Tailwind CSS 4, Vite              |
| Database | MySQL 8 (Docker default) or SQLite (local dev)      |
| Auth     | Laravel Sanctum, `pragmarx/google2fa` for TOTP      |
| Media    | `spatie/laravel-medialibrary`, `intervention/image` |
| Calendar | FullCalendar                                        |
| Testing  | Pest / PHPUnit                                      |

## Getting started

```bash
git clone https://github.com/kmelitzanis/oikolog.git
cd oikolog
composer install
npm install
cp .env.example .env
php artisan key:generate
```

For local development SQLite is the quickest path — set this in `.env`:

```
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

Then create the schema, seed the translations, and link storage so uploads are reachable:

```bash
touch database/database.sqlite
php artisan migrate
php artisan db:seed --class="Database\Seeders\TranslationSeeder"
php artisan storage:link
```

Create an account and start the app:

```bash
php artisan make:user --admin
npm run build
php artisan serve
```

### ⚠️ The dev server port matters

`SANCTUM_STATEFUL_DOMAINS` in `.env` lists the hosts allowed to authenticate with the session cookie. If you serve
the app on a port that is not in that list, every `/api/*` request returns **401** and the API-backed pages
(shopping lists, recipes, meal planner) silently render empty — with no error in the UI.

Either serve on the configured port, or add the one you use:

```
SANCTUM_STATEFUL_DOMAINS=localhost:8000,127.0.0.1:8000
```

## Docker

```bash
docker compose up -d
```

Brings up `app`, `webserver` and a MySQL 8 database. Migrations run on boot by default; set `FORCE_MIGRATE=0` to
skip them.

## Development

```bash
npm run dev            # Vite dev server with HMR
npm run build          # production assets
php artisan test       # Pest + PHPUnit
```

Useful commands:

| Command                            | Purpose                                                                        |
|------------------------------------|--------------------------------------------------------------------------------|
| `php artisan make:user`            | Create a user (`--admin` for an owner account)                                 |
| `php artisan admin:reset-password` | Reset the admin password                                                       |
| `php artisan bills:realign`        | Snap drifted `next_due_date`s back onto their schedule (`--dry-run` to preview) |

### Translations

Translations live in `resources/lang/{en,el}/messages.php`, and database entries override the files at runtime.
Admins can edit them at `/translations` — useful for fixing wording without a deploy. The admin account is the one
whose email matches `ADMIN_EMAIL` in `.env`.

### Design system

The UI follows a documented design system: **amber-500 `#f59e0b` on slate**, with the status colours mapped 1:1 to
bill state. Anything on an amber surface takes slate-900 ink — amber is far too light to carry white text.

The logo is the "beam stack": there is no separate icon mark, the wordmark *is* the logo, and the `l` of *log* is
redrawn as four ledger beams of unequal length. It lives in `resources/views/components/logo.blade.php`, sized in
`em` so the mark tracks the wordmark at any size. App icons are generated from `public/icons/icon.svg`.

## Project layout

```
app/Http/Controllers/Web/    Blade-rendered pages
app/Http/Controllers/Api/    JSON endpoints used by the Alpine components
app/Models/                  Eloquent models (Bill, Income, ShoppingList, Recipe, …)
app/Policies/                Authorisation rules
resources/views/             Blade templates and components
resources/js/pages/          Per-page Alpine components
design-system/               Generated design-system previews
```

More detail for contributors — and for AI coding agents — lives in [AGENTS.md](AGENTS.md).

## License

Released under the **GNU GPL v3.0**. See [LICENSE](LICENSE).

---

<p align="center">
  If Oikolog is useful to you, <a href="https://buymeacoffee.com/kostasmel">buy me a beer</a> 🍺
</p>
