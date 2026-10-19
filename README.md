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
- Local accounts (created by `php artisan db:seed`, local only, password `password`): `admin@erudsway.test` (admin), `instructor@erudsway.test` (instructor)
- Video processing runs on the queue: `cd apps/api && php -d opcache.enable_cli=0 artisan queue:work --queue=media,default` (needs ffmpeg on PATH)
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
