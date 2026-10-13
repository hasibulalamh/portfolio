# Portfolio Backend — Setup

Laravel 13 API that powers the two Next.js apps in this workspace:

| App | Local URL | Role |
|---|---|---|
| `portfolio-backend` | http://localhost:8000 | Laravel API (this project) |
| `portfolio-frontend` | http://localhost:3000 | Public portfolio site |
| `portfolio-admin` | http://localhost:3001 | Admin panel (CMS) |

## Requirements

- PHP 8.3+ with the `pdo_mysql` extension
- Composer
- MySQL 8
- Node.js 20+ (for the two frontends)

## First-time setup

```bash
cd portfolio-backend

composer install
cp .env.example .env
php artisan key:generate
```

Create the database and point `.env` at it:

```sql
CREATE DATABASE portfolio_backend
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=portfolio_backend
DB_USERNAME=root
DB_PASSWORD=your_password
```

Then migrate, seed, and link storage:

```bash
php artisan migrate --seed
php artisan storage:link   # required for local file uploads to be served
php artisan serve          # http://localhost:8000
```

`storage:link` is easy to forget: without it, uploaded images save correctly
but return 404 when the browser requests them.

## Admin account

`AdminUserSeeder` creates the single admin account. Both seeders are
idempotent, so re-running `php artisan db:seed` resets the password to the
seeded value rather than creating duplicates.

| Field | Value |
|---|---|
| Email | `info@hasib.com` |
| Password | `42862266` |

Change this before deploying anywhere public — edit the seeder, or update the
password directly:

```bash
php artisan tinker --execute="\App\Models\User::first()->update(['password' => \Hash::make('a-strong-password')]);"
```

## Frontend configuration

Both frontends read the API base URL from their own `.env.local`. The `/api`
suffix is required.

`portfolio-frontend/.env.local`:

```dotenv
NEXT_PUBLIC_API_URL=http://localhost:8000/api
NEXT_PUBLIC_BASE_URL=http://localhost:3000
```

`portfolio-admin/.env.local`:

```dotenv
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

The admin panel also supports `NEXT_PUBLIC_SKIP_AUTH=true`, which bypasses the
login check so the UI can be previewed with the API down. Leave it unset for
normal use — with it on, every API call still fails, so the panel looks
functional but saves nothing.

## Running everything

Three terminals:

```bash
cd portfolio-backend  && php artisan serve            # :8000
cd portfolio-frontend && npm run dev                  # :3000
cd portfolio-admin    && PORT=3001 npm run dev        # :3001
```

Sign in at http://localhost:3001/login.

## CORS

The API is consumed cross-origin by both Next.js apps, so `config/cors.php`
lists the allowed browser origins. `supports_credentials` is `true` because the
admin's axios client sends credentials, and that rules out a wildcard origin —
every origin must be listed explicitly.

The policy is applied in two layers:

1. **Laravel's built-in `HandleCors` middleware** (framework default) answers
   preflight `OPTIONS` requests with a 204 and emits the CORS headers.
2. **`app/Http/Middleware/Cors.php`** (registered outermost in
   `bootstrap/app.php`) enforces the same allowlist on every actual response:
   an allowed origin gets its exact origin echoed with credentials, and any
   origin not on the list receives no CORS headers at all.

`config/cors.php` is the single source of truth — both layers read it, so they
cannot disagree. The allowlist is built from the `FRONTEND_URL` and `ADMIN_URL`
env vars (with localhost:3000/3001 defaults for development); there are no
hardcoded production domains in the file.

### Allowing a new frontend origin

Set the env vars and restart, no code change needed:

```dotenv
FRONTEND_URL=https://portfolio.example.com
ADMIN_URL=https://admin.example.com
```

If the deployment cannot use env vars, edit `config/cors.php` directly — but
remember the app must be restarted after `php artisan config:cache`.

## Security headers

The app sets these on every response via `app/Http/Middleware/SecurityHeaders.php`
(global middleware):

| Header | Value |
|---|---|
| `X-Powered-By` | removed (response object + SAPI `header_remove`) |
| `X-Content-Type-Options` | `nosniff` |
| `Cross-Origin-Resource-Policy` | `same-origin` |
| `Content-Security-Policy` | `default-src 'none'; frame-ancestors 'none'; form-action 'none'` |

For production, mirror these at the web-server level so they also cover
responses that never reach Laravel (static files, web-server 404s): see
`deploy/nginx-portfolio-api.conf.example` and
`deploy/apache-portfolio-api.conf.example`.

CORS headers (`Access-Control-Allow-*`) deliberately stay in the app: the
allowlist is dynamic and credentials-enabled, so a static header in the web
server would bypass it. Also note that `Cross-Origin-Resource-Policy:
same-origin` applies to `/api/*` responses; uploads are served from R2 (or the
local storage symlink, which the web server serves without these headers), so
frontends can still display them cross-origin.

## Rate limiting and proxy trust

Every limit in this app is per **client IP**, and that is only meaningful
because the proxies in front of the app are trusted. The two halves are
separate pieces of configuration, and getting one of them wrong is how this
project took a production outage: all visitors shared a single bucket, the
first few requests of the minute spent it, and everyone else was refused.

### Trusted proxies

Every request arrives through three hops — Cloudflare, then Render's load
balancer, then a local hop inside the container — so `REMOTE_ADDR` is always the
container's own `127.0.0.1`, never a visitor. `bootstrap/app.php` therefore
trusts the proxies listed in `config/trustedproxy.php`: Cloudflare's published
egress ranges, Render's private `10.0.0.0/8` load balancer, and the `127.0.0.0/8`
local hop. Ranges are used rather than the literal `REMOTE_ADDR`, because that
token would trust whoever opened the connection — including a request that
reached the container directly from a public address. Only the `X-Forwarded-*`
headers are trusted; `X-Forwarded-Prefix` is not, because nothing here rewrites
paths.

With those trusted, Symfony resolves the client IP by walking
`X-Forwarded-For` from the right and skipping our own proxies, so the value a
client wrote is always further left and can never become the answer.

Deliberately **not** `'*'`. Cloudflare appends to a client-supplied
`X-Forwarded-For` rather than replacing it, so a trust-everything list makes the
leftmost — client-forged — address the client IP, and anyone can mint a fresh
bucket with every request.

| Setting | Meaning |
|---|---|
| `TRUSTED_PROXIES` (env) | Comma-separated IPs/CIDRs to trust, or `REMOTE_ADDR`. Unset ⇒ Cloudflare's ranges + `10.0.0.0/8` + `127.0.0.0/8`. |

Set it in the Render dashboard (it is read when the config is cached at
container start). If Cloudflare's ranges change and the list goes stale, the
failure is degraded rather than broken: requests from an unlisted range resolve
to that range's address and share one bucket again — the same behaviour as
trusting nothing.

### Which limiter protects what

| Limiter | Routes | Budget | Key |
|---|---|---|---|
| `public-read` | every public `GET /api/*` | 120/min | client IP |
| `public-write` | `POST /api/contact-messages`, `POST /api/meeting-requests` | 10/min | client IP |
| `click-tracking` | `POST /api/track` | 30/min | client IP |
| (login, in `AuthService`) | `POST /api/login` | 5 failed attempts/min | `email` + client IP |

All four live in `app/Providers/AppServiceProvider.php` except the login one,
which stays in `AuthService` because it has to be per-credential rather than
per-route.

They are **named** limiters rather than inline `throttle:10,1` specs for a
reason: an inline spec keys its bucket on `sha1(domain|ip)` alone — the *same*
key for every route that uses one — so ten contact-form posts used to spend the
click-tracking budget too, and thirty tracked clicks locked a visitor out of the
contact form. A named limiter keys on `sha1(name|ip)`, so each limit protects
only what it names. Budgets were carried over unchanged.

`public-read` is deliberately generous: one homepage render fetches ten
endpoints at once, and the 60s ISR revalidation does it again, so the limit has
to clear that with room to spare while still stopping one client from spending
the whole (free-tier, single-worker) container.

`tests/Feature/RateLimitProxyTrustTest.php` pins both halves — that the proxies
are trusted and a forged prefix cannot win, and that each limit is keyed per
visitor with the strict budgets intact.

### Cache headers on public reads

`app/Http/Middleware/PublicCacheHeaders.php` marks successful public reads
`public, max-age=60, s-maxage=300, stale-while-revalidate=300`. Laravel's default
is `no-cache, private`, which forbids every intermediary from storing anything:
the frontend paid a full origin round trip for content that had not changed in
weeks, and every one of those requests was a request that could be rate limited.
`max-age` covers the Next.js Data Cache and a browser reading the API directly;
`s-maxage` is what lets the Cloudflare edge answer repeat reads without touching
the container. Errors — including 429s — are left uncacheable on purpose.

### Checking it by hand

`scripts/rate-limit-check.sh` probes a running API and prints a status-code
histogram per scenario: the resolved client IP for a forwarded request, a
public-read bucket exhausted by one visitor while another is unaffected, and the
strict form / tracking limits. It is write-free (every POST it sends fails
validation), so it is safe against a development database — never production.

```bash
API_URL=http://127.0.0.1:8000/api ./scripts/rate-limit-check.sh
```

## File uploads

`POST /api/admin/upload` accepts a single `file` field and returns
`{ url, path, disk }`. The frontend stores the returned `url` on the owning
record.

Storage target is chosen automatically:

- **Cloudflare R2** when `R2_ACCESS_KEY_ID`, `R2_BUCKET` and `R2_ENDPOINT` are all set
- **Local `public` disk** otherwise, so uploads work before R2 is configured

To enable R2, fill in the `R2_*` block in `.env`:

```dotenv
R2_ACCESS_KEY_ID=...
R2_SECRET_ACCESS_KEY=...
R2_BUCKET=portfolio-backend
R2_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
R2_URL=https://your-public-bucket-domain.com
```

`R2_URL` is the bucket's public hostname (custom domain or `r2.dev` subdomain)
and is what gets embedded in the returned URL, so it must be publicly readable.

Validation is enforced server-side per upload type — the browser-side check in
`FileUpload.jsx` is a convenience, not a control. MIME type is verified from
file contents, and stored filenames are UUIDs, so a `.php` file renamed to
`.png` is rejected and a client-supplied filename is never trusted.

## Email

Delivery goes through [Resend](https://resend.com). Meeting-request replies are
the only outgoing mail: `PUT /admin/meeting-requests/{id}/reply` sends
`App\Mail\MeetingRequestReplyMail` to the requester.

### Configuration

`resend/resend-php` is already a direct dependency, and Laravel ships the
`resend` mailer in `config/mail.php` reading `RESEND_API_KEY` from
`config/services.php`. Only these `.env` values matter:

```env
MAIL_MAILER=resend
RESEND_API_KEY=re_xxxxxxxxxxxxxxxxxxxxxxxxxxxx
MAIL_FROM_ADDRESS=onboarding@resend.dev
MAIL_FROM_NAME="Hasibul Alam Portfolio"
```

The `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` keys are SMTP
settings and are ignored by the Resend transport.

**Getting an API key:** sign in at [resend.com](https://resend.com) →
**API Keys** → *Create API Key* (sending permission is enough). The key is shown
once. It is a live credential — keep it out of version control and out of
`.env.example`. Run `php artisan config:clear` after changing it if you have
cached config.

### Verifying delivery

```bash
php artisan mail:test-resend you@example.com
```

Sends a one-line test email without creating a real meeting-request reply. It
prints the active mailer, the from address and whether the API key is set, then
reports the transport's own error on failure. Options:

```bash
php artisan mail:test-resend you@example.com --mailer=log   # render locally, send nothing
```

Delivery status for every message is at [resend.com/emails](https://resend.com/emails).

### Test mode vs production

A new Resend account is restricted: **it only delivers to the email address that
owns the account**, and only from `onboarding@resend.dev`. Sending anywhere else
fails with:

> You can only send testing emails to your own email address. To send emails to
> other recipients, please verify a domain at resend.com/domains…

That is the expected state before domain verification — not a misconfiguration.
Until then, use your own address when testing.

**To send to real visitors, verify a domain:**

1. [resend.com/domains](https://resend.com/domains) → *Add Domain* (e.g. `hasib.com`).
2. Add the DNS records Resend shows — an MX and TXT pair for SPF, plus a
   `resend._domainkey` TXT record for DKIM — at your DNS provider.
3. Wait for the dashboard to report **Verified** (usually minutes; DNS
   propagation can take up to 48h).
4. Point `MAIL_FROM_ADDRESS` at that domain, e.g. `MAIL_FROM_ADDRESS=info@hasib.com`.
5. Re-run `php artisan mail:test-resend` against an outside address to confirm.

Adding a DMARC record (`_dmarc` TXT) afterwards improves deliverability but is
not required for verification.

### Failure behaviour

A reply is **always saved**, even when the email cannot be sent. The service
catches transport failures, logs them, and the API responds
`"Reply saved, but the email could not be sent. Check the mail configuration."`
instead of reporting a false success. Check what happened with:

```bash
grep "Failed to email meeting request reply" storage/logs/laravel.log | tail
```

Transient `cURL error 35` / connection-reset failures against
`api.resend.com` are network hiccups — retry the reply.

The internal `admin_note` on a meeting request is never passed to the mailable,
so it cannot leak into an outgoing email. `tests/Feature/MeetingRequestReplyMailTest.php`
asserts this along with the recipient, subject and rendered body, so the
guarantee holds regardless of which mailer is configured.

## API shape

Every response — success or failure — uses the same envelope:

```json
{ "data": ..., "message": "...", "errors": null }
```

Validation failures return HTTP 422 with `errors` keyed by field name. Wrong
credentials return 401, not 422: the request was well-formed, the credentials
were not.

### Routes

Public (no auth):

```
GET  /api/settings          GET  /api/projects
GET  /api/nav-items         GET  /api/projects/{slug}
GET  /api/hero              GET  /api/api-showcases
GET  /api/about             GET  /api/testimonials
GET  /api/skills            GET  /api/contact-info
GET  /api/timeline
POST /api/contact-messages     (rate limited, 10/min per client IP)
POST /api/meeting-requests     (rate limited, 10/min per client IP)
POST /api/track                (rate limited, 30/min per client IP)
```

Public reads are limited to 120/min per client IP and served with shared-cache
headers — see [Rate limiting and proxy trust](#rate-limiting-and-proxy-trust).

Auth:

```
POST /api/login       POST /api/logout       GET /api/admin/me
```

Admin (Bearer token required) — full CRUD under `/api/admin`:
`settings`, `nav-items`, `hero`, `about`, `skill-categories`, `skills`,
`timeline-items`, `projects`, `api-showcases`, `testimonials`, `contact-info`,
`messages`, `meeting-requests`, `upload`.

Notable details:

- Reordering posts the whole list: `PUT .../reorder` with `{ items: [{ id, order }] }`
- `PUT /api/admin/messages/{id}/read` **toggles** read state (the panel uses it for both directions)
- `GET /api/admin/skills?category_id=N` filters by category
- Project slugs are generated from the title and de-duplicated (`haatbazar`, `haatbazar-2`); the admin never sends one
- Admin project routes bind by numeric id; the public route binds by slug
- Deleting a message or meeting request is a soft delete

## Testing

`scripts/smoke-test.sh` exercises every endpoint against a running server and
asserts on each status code:

```bash
php artisan serve &
./scripts/smoke-test.sh
```

It creates and deletes its own records. It writes to the same database it runs
against, so point it at a development database, not production.

## Deploying

1. `APP_ENV=production`, `APP_DEBUG=false`, and set a real `APP_URL`
2. Set `FRONTEND_URL` / `ADMIN_URL` to the real frontend origins (this is what
   the CORS allowlist is built from — see [CORS](#cors))
3. Apply the security headers at the web-server level too — see
   `deploy/nginx-portfolio-api.conf.example` / `deploy/apache-portfolio-api.conf.example`
4. Change the seeded admin password
5. Configure R2, and verify a Resend domain so mail reaches real recipients (see [Email](#email))
6. Confirm the proxy chain: the API must be reached through Cloudflare and
   Render's balancer. If either hop changes, set `TRUSTED_PROXIES` accordingly
   and re-run `scripts/rate-limit-check.sh` — see
   [Rate limiting and proxy trust](#rate-limiting-and-proxy-trust)
7. `php artisan config:cache route:cache`
8. Serve `public/` over HTTPS
