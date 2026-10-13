# Portfolio Ecosystem — Setup & Startup Guide

Three services that must all be running for the system to work end to end:

| Service | Stack | Port | URL |
|---|---|---|---|
| `portfolio-backend` | Laravel 13 (PHP 8.3+) | **8000** | http://127.0.0.1:8000 |
| `portfolio-frontend` | Next.js 16 (public site) | **3000** | http://localhost:3000 |
| `portfolio-admin` | Next.js 16 (admin CMS) | **3001** | http://localhost:3001 |

> **Both frontends are Next.js, not Vite.** They would each default to port
> 3000, so the admin panel **must** be started with `-p 3001` — that is the port
> `ADMIN_URL` and `SANCTUM_STATEFUL_DOMAINS` in the backend `.env` expect.

---

## Prerequisites

| Tool | Version used | Check |
|---|---|---|
| PHP | 8.4.23 (8.3+ required) | `php -v` |
| Composer | 2.x | `composer --version` |
| Node.js | 24.15.0 (18+ required) | `node -v` |
| MySQL | 8.x, listening on 3306 | `mysqladmin status` |

---

## First-time setup

### 1. Backend

```bash
cd portfolio-backend

composer install
cp .env.example .env          # skip if .env already exists
php artisan key:generate
php artisan storage:link      # needed for local file uploads
```

Create the database, then edit `.env` to match your MySQL credentials:

```sql
CREATE DATABASE portfolio_backend CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan migrate           # 19 migrations
php artisan db:seed           # admin user + singleton rows
```

Both seeders are **idempotent** — re-running `db:seed` resets the admin password
to the known value instead of creating duplicates.

There is also a one-shot helper that does install + key + migrate + assets:

```bash
composer run setup
```

### 2. Frontend and admin

```bash
cd portfolio-frontend && npm install
cd ../portfolio-admin && npm install
```

Both need a `.env.local` (already present in this checkout):

```bash
# portfolio-frontend/.env.local
NEXT_PUBLIC_API_URL=http://localhost:8000/api
NEXT_PUBLIC_BASE_URL=http://localhost:3000
```

```bash
# portfolio-admin/.env.local
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

The `/api` suffix matters: both `lib/api.js` files append paths like `/hero` and
`/admin/settings` directly to this value.

---

## Starting everything

Run each in its own terminal, from the `portfolio/` root:

```bash
# Terminal 1 — backend API
cd portfolio-backend && php artisan serve --host=127.0.0.1 --port=8000

# Terminal 2 — public site
cd portfolio-frontend && npm run dev          # binds :3000

# Terminal 3 — admin CMS  (note the explicit port)
cd portfolio-admin && npx next dev -p 3001
```

### Background alternative

```bash
mkdir -p /tmp/pf-logs
cd portfolio-backend  && nohup php artisan serve --host=127.0.0.1 --port=8000 > /tmp/pf-logs/backend.log  2>&1 &
cd ../portfolio-frontend && nohup npx next dev -p 3000 > /tmp/pf-logs/frontend.log 2>&1 &
cd ../portfolio-admin    && nohup npx next dev -p 3001 > /tmp/pf-logs/admin.log    2>&1 &
```

### Confirm all three are up

```bash
curl -s -o /dev/null -w 'backend  %{http_code}\n' http://127.0.0.1:8000/api/settings
curl -s -o /dev/null -w 'frontend %{http_code}\n' http://localhost:3000
curl -s -o /dev/null -w 'admin    %{http_code}\n' http://localhost:3001
```

Three `200`s means the stack is healthy. The **first** request to a Next.js dev
route triggers a Turbopack compile that can take 10–60 s — allow a generous
timeout before concluding something is broken.

---

## Logging in

Open http://localhost:3001/login and use the seeded account:

```
Email:    info@hasib.com
Password: 42862266
```

Defined in `database/seeders/AdminUserSeeder.php`. **Change this before
deploying anywhere public.**

---

## Environment variables that matter

### `portfolio-backend/.env`

| Key | Value used | Why it matters |
|---|---|---|
| `APP_KEY` | generated | App boots without it, but encryption/sessions break |
| `APP_DEBUG` | `true` locally | **Set `false` in production** — it exposes stack traces |
| `APP_URL` | `http://localhost:8000` | Absolute URL generation |
| `DB_CONNECTION` | `mysql` | — |
| `DB_DATABASE` | `portfolio_backend` | Must exist before `migrate` |
| `DB_USERNAME` / `DB_PASSWORD` | your MySQL creds | — |
| `FRONTEND_URL` | `http://localhost:3000` | CORS |
| `ADMIN_URL` | `http://localhost:3001` | CORS |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:3000,localhost:3001,localhost:8000` | Token auth for the admin panel |
| `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` | `database` | These use DB tables created by `migrate` |
| `FILESYSTEM_DISK` | `local` | Uploads go to `storage/app/public` — needs `storage:link` |
| `MAIL_MAILER` | `log` | Mail is written to the log, not sent |

R2/AWS and SMTP keys are placeholders. Fill them in only if you switch
`FILESYSTEM_DISK` to `r2`/`s3` or want real email delivery.

### Frontend / admin

| Key | Where | Value |
|---|---|---|
| `NEXT_PUBLIC_API_URL` | both | `http://localhost:8000/api` |
| `NEXT_PUBLIC_BASE_URL` | frontend | `http://localhost:3000` (canonical/OG URLs) |
| `NEXT_PUBLIC_SKIP_AUTH` | admin | Leave unset. Set `true` only to preview the UI with the API down |

---

## Database commands

```bash
php artisan migrate:status          # what has run
php artisan migrate                 # apply pending
php artisan db:seed                 # idempotent: admin user + singletons
php artisan migrate:fresh --seed    # DESTRUCTIVE: drops every table, then reseeds
```

Seeders:
- `AdminUserSeeder` — the single admin account (`info@hasib.com`).
- `SingletonSeeder` — one row each for settings, hero, about, contact-info, plus
  a starter skill category, skill, project and timeline item.

`nav_items`, `api_showcases` and `testimonials` are intentionally left empty;
the public site falls back to `portfolio-frontend/lib/fallbacks.js` for
navigation and hides the other two sections until you add records.

---

## Production build

```bash
cd portfolio-backend
php artisan config:cache && php artisan route:cache && php artisan view:cache

cd ../portfolio-frontend && npm run build && npm run start   # :3000
cd ../portfolio-admin    && npm run build && npx next start -p 3001
```

Before deploying: set `APP_DEBUG=false`, set `APP_ENV=production`, change the
seeded admin password, and point `FRONTEND_URL` / `ADMIN_URL` /
`SANCTUM_STATEFUL_DOMAINS` at your real domains.

---

## Tests

```bash
cd portfolio-backend && php artisan test
```

Four PHPUnit suites (Unit / Feature / Regression / Contract) plus the Playwright
E2E suite in `tests/e2e` at the repo root. `portfolio-backend` also ships
`scripts/rate-limit-check.sh`, which probes a running API for per-visitor rate
limit buckets without writing anything.

---

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| Admin serves the public site | Admin was started without `-p 3001` and grabbed 3000 |
| `401` on every admin page | Backend not running, or `NEXT_PUBLIC_API_URL` missing the `/api` suffix |
| First page load hangs 30–60 s | Cold Turbopack compile. Normal in dev; wait it out |
| Admin edits don't show on the public site | 60 s ISR cache (`REVALIDATE_SECONDS` in `portfolio-frontend/lib/api.js`). Wait, or lower it |
| `SQLSTATE[HY000] [1049] Unknown database` | Create the DB before running `migrate` |
| `429 Too Many Requests` on contact form | Working as intended — `throttle:public-write`, 10/min per client IP |
| `429` on `/api/settings`, site renders default content | Public-read limit (120/min per client IP), or an upstream proxy's own limit. The `api_fallback` log line's `reason`/`retry_after_ms` says which; `curl` the frontend's `/api-health` for the live status and timing. Background: `portfolio-backend/BACKEND-SETUP.md` § *Rate limiting and proxy trust* |
| Every visitor seems to share one rate-limit bucket | `TRUSTED_PROXIES` unset or wrong, so `$request->ip()` is the proxy's address. Prove it with `cd portfolio-backend && ./scripts/rate-limit-check.sh` |
| Uploaded images 404 | Run `php artisan storage:link` |
| `Route [login] not defined` 500s | Fixed in `bootstrap/app.php`; run `php artisan config:clear` if a stale cache persists |
