# Erudsway

Online learning marketplace for Erudsway Educorp LLP: students buy video courses, approved instructors publish them, and the platform controls moderation, pricing, commissions and payouts.

- **Plan and decisions:** [docs/PLAN.md](docs/PLAN.md)
- **API:** `apps/api` (Laravel 13, PHP 8.3+) — modular monolith, REST under `/api/v1`
- **Web:** `apps/web` (Next.js 16, React 19, Tailwind 4, shadcn/ui)
- **Typed API client:** `packages/api-client`, generated from the API's OpenAPI spec

## Getting started

Requirements: PHP 8.3+, Composer, Node 24, pnpm, and Docker (for PostgreSQL, Redis and Mailpit).

```bash
docker compose -f infra/docker-compose.dev.yml up -d
pnpm install
cd apps/api && composer install && cp .env.example .env && php artisan key:generate && php artisan migrate --seed
```

Run the two apps in separate terminals:

```bash
cd apps/api && php -d opcache.enable_cli=0 artisan serve --port=8000
pnpm dev:web
```

- Web: http://localhost:3000 — design review screens: `/`, `/course/complete-full-stack-web-development`, `/learn/complete-full-stack-web-development`, `/instructor/courses/demo/curriculum`, `/design-system`
- API health: http://localhost:8000/api/v1/health
- Local accounts (created by `php artisan db:seed`, local only, password `password`): `admin@erudsway.test` (admin), `instructor@erudsway.test` (instructor), `learner@erudsway.test` (student), `finance@erudsway.test` (finance staff — payouts need a second approver)
- Background jobs: `cd apps/api && php -d opcache.enable_cli=0 artisan queue:work --queue=critical,default`, and for video processing (its own connection, long timeout) `php -d opcache.enable_cli=0 artisan queue:work media --queue=media --timeout=11000` (needs ffmpeg on PATH). Production runs both under Horizon — see `deploy/README.md`.
- PostgreSQL check (production runs PostgreSQL): `DB_CONNECTION=pgsql DB_DATABASE=erudsway_test DB_USERNAME=postgres php -d opcache.enable_cli=0 vendor/bin/pest`
- Payments: without Razorpay keys the API uses a local test gateway (checkout shows a "Test payment" dialog; refused in production). With test keys set `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET` and `RAZORPAY_WEBHOOK_SECRET`, and point the Razorpay webhook at `/api/v1/webhooks/razorpay` (events: payment.captured, order.paid, refund.processed, refund.failed). Run `php artisan schedule:work` locally for the scheduled jobs (payment reconciliation, releasing instructor earnings after the 30-day hold, drafting monthly payouts).
- Video playback: segments are served by Nginx `secure_link` in production (`deploy/server/nginx/site.conf`; provisioning sets the shared secret). Locally the API serves them itself.
- API docs (OpenAPI UI): http://localhost:8000/docs/api

> **Windows note:** the local PHP build intermittently crashes (PHPStan, Scramble, Pest) when CLI OPcache JIT is on, so project scripts pass `-d opcache.enable_cli=0`. Without Docker, `apps/api/.env` falls back to SQLite and file drivers.

## Everyday commands

| Task | Command |
|---|---|
| API tests | `composer test` (in `apps/api`) |
| API style / static analysis | `composer lint` · `composer analyse` (in `apps/api`) |
| Web tests / lint / types | `pnpm --filter web test` · `pnpm lint` · `pnpm typecheck` |
| Refresh API contract | `pnpm api:openapi && pnpm api:client` |

## Conventions

- **API modules** live in `apps/api/app/Modules/<Name>` with their own `routes.php` (mounted at `/api/v1`) and `Database/migrations`. Register each module's service provider in `bootstrap/providers.php`.
- **Responses** always use `{ "data", "meta"? }` or `{ "error": { "code", "message", "fields"? } }` — see `App\Support\Http\ApiResponse`.
- **Money** is integer minor units (paise) via `App\Support\Money\Money`; never floats.
- **Frontend pages** read `params` inside a `<Suspense>` child so navigations stay instant (Next.js 16 Cache Components).
- **Demo data** in `apps/web/src/lib/demo-data.ts` exists only for Phase 0 design review and is replaced by API data from Phase 2. Photos are from Unsplash, credited on the page.
