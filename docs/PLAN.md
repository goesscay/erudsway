# Erudsway — Online Learning Marketplace: Architecture & Delivery Plan

**Owner:** Erudsway Educorp LLP · **Status:** Approved; Phases 0–4 built · **Date:** 2026-10-18

> **Phase 0 notes (2026-10-18):** scaffolded on Laravel 13.37 and Next.js 16.4.2 (newer than the 16.3 line verified earlier; Cache Components + Partial Prefetching enabled by default). Images on design-review screens come from Unsplash with credits. Open follow-ups for Phase 2: sticky buy bar on mobile course pages, mobile search, replacing `demo-data.ts` with API data.
>
> **Phase 1 notes (2026-10-19):** identity module live — register/login/logout (Sanctum SPA cookies), email verification (signed link → web app), password reset, profile and password change, roles + fine-grained staff permissions (seeded by migration), instructor applications with admin approve/reject (no self-review), user suspension that ends access immediately, rate limits, and an append-only audit log. Emails are queued. Emails are stored lower-cased instead of using `citext`, so the schema also runs on SQLite for local development. Deferred: branded email templates (Phase 7), Playwright E2E suite (needs both servers in CI), mobile navigation for the admin console.
>
> **Phase 2 notes (2026-10-19):** the portal is focused on **accounts, tax and finance**. Top-level course areas set by Erudsway: GST · Income Tax · TDS & TCS · Accounts & Career · Audit & Assurance · ROC & Company Law · Litigation & Appeals · FEMA & Cross-border · IPO & Capital Markets · Startup & Setup (subcategories proposed in the demo seeder). Catalog module live: categories, price tiers, courses, sections/lectures, public catalogue API with search (PostgreSQL full-text + trigram; SQLite fallback for local tests), filters with facet counts, sorting, pagination, instructor profiles, short public caching. Web pages read the API through cached server functions that never run at build time; course pages carry schema.org Course JSON-LD; sitemap.xml and robots.txt added. Still open: the PostgreSQL search path is untested until a local `erudsway` role exists or CI can run; legal pages (terms, privacy, refund policy, grievance officer) linked from the footer don't exist yet; Phase 3 must sanitise instructor-written HTML server-side.

> **Phase 3 notes (2026-10-19):** instructor course builder and media pipeline. Instructors create a draft, then fill four steps (intended learners, curriculum, landing page, pricing) with a live readiness checklist; sections and lectures reorder by drag or keyboard. Videos upload in resumable 8 MB chunks, are checked with ffprobe and transcoded on the `media` queue into AES-128-encrypted HLS (360/720/1080p, never above the source) with a poster frame; the key is stored encrypted in the database, never on disk. Articles use a small rich-text editor and are sanitised server-side with HTMLPurifier (allowlisted tags, http/https/mailto links only); downloadable resources up to 50 MB. Course images are centre-cropped to 1280×720. Submitting locks the course; admins with `moderate_courses` approve (publishes) or request changes (feedback required) — each decision is audited and emailed. Verified end to end in the browser with a real upload processed by the queue worker. The local queue worker must run with `--queue=media,default`. Not yet built: reviewers can't play lecture videos inside the review sheet, and the HLS key URI is a placeholder — both arrive with the Phase 4 player.

> **Phase 4 notes (2026-10-19):** enrollment and the learning player. New Learning module: enrollments (free, admin grant, and the `purchase` source ready for Phase 5), per-lecture progress, private timestamped notes. Course progress lives on the enrollment row rather than a separate 1:1 `course_progress` table. One `Entitlements` service decides access for every outline, content, download, playback and key request, so revoking takes effect immediately (enrolled learners, the course's instructor and course reviewers; free-preview lectures of live courses are open to everyone). Playback follows §6: the API issues a 2-hour, viewer-bound token; master and variant playlists are generated per request with the key URI rewritten to `/api/v1/stream/{asset}/key` and every segment signed in Nginx `secure_link_md5` format (`docs/deploy/nginx-hls.conf`; a PHP fallback serves the same signed URLs locally). The web player is a custom hls.js player (native HLS on Safari) with speed and quality menus, keyboard shortcuts, a moving learner watermark, resume, 15-second progress saves, an "Up next" countdown and automatic playlist refresh when links expire. Videos count as complete at 90%; articles are marked complete by the learner. Also built: My Learning, one-click free enrollment, a "Preview this course" dialog on course pages, staff grant/revoke of course access with an audited reason (`manage_users`), and preview links for instructors and reviewers. Verified end to end in the browser with the real encrypted test clip. Not yet done: paid enrollment arrives with checkout (Phase 5); Q&A, announcements and reviews tabs arrive in Phase 7.
**Benchmark:** udemy.com (marketplace model: students buy video courses, approved instructors publish, platform keeps full control and pays commissions)

> Nothing has been built yet. This document is the "before writing code" deliverable from the spec (steps 1–6). Section 14 lists the decisions that need your sign-off before Phase 0 starts.

---

## 1. Product scope in one page

| Actor | Can do | Cannot do |
|---|---|---|
| **Visitor** | Browse catalog, search, view course landing pages, watch free preview lectures, add to cart (guest cart) | Watch paid content, review |
| **Student** | Buy courses, learn in player, track progress, take quizzes/assignments, earn certificates, review & rate, Q&A, wishlist, order history, invoices, request refunds | See anything they haven't been entitled to |
| **Instructor** (approved by us) | Create/edit own courses, upload videos/resources, build curriculum & quizzes, submit for review, create *own-course* coupons (within platform limits), see earnings, payouts, students, reviews, answer Q&A | Publish without our approval, set price outside allowed price tiers, see other instructors' data, change commission, touch money |
| **Admin / Staff** (Erudsway) | Everything: approve instructors & courses, unpublish, set commission rules, price tiers, platform coupons, refunds, payouts, reports, settings, audit logs, impersonate (audited) | Edit ledger history (append-only by design) |

"Full control with us" is enforced technically, not just by UI: every course goes through moderation, prices come from admin-defined tiers, commission is snapshotted per sale by the server, payouts require admin approval, and every admin/financial action is audit-logged.

---

## 2. Architecture

```
                    ┌──────────────────────────── Browser / (later) Expo app ─────────────────────────────┐
                    │                                                                                     │
                    ▼                                                                                     ▼
        www.erudsway.com  (Next.js, SSR/RSC)                                     Nginx: HLS segments (secure_link,
        - public catalog SEO pages (server-rendered, cached)                     signed + expiring URLs, no PHP)
        - student / instructor / admin apps (client components)                              ▲
                    │  REST /api/v1 (typed client, cookies)                                  │ per-user playlist + key
                    ▼                                                                         │ (entitlement-checked)
        api.erudsway.com (Laravel 13, modular monolith)  ────────────────────────────────────┘
        - Sanctum SPA cookie auth (web) / Sanctum tokens (mobile)
        - Policies + roles, Form Requests, API Resources
        - Domain services, DB transactions for money & enrollment
                    │                       │                         │
                    ▼                       ▼                         ▼
              PostgreSQL              Redis (cache, queues,      Storage disk "private"/"public"
              (source of truth)       sessions, rate limits)     (VPS now → S3/R2 later: config change only)
                                            │
                                            ▼
                               Horizon-supervised queue workers
                               (emails, invoices, certificates, webhooks, reports, search indexing,
                                ffmpeg HLS transcoding on an isolated `media` queue)

        Razorpay  ── webhooks (signed) ──▶ /api/v1/webhooks/razorpay  ──▶ webhook_events (idempotent) ──▶ queue
```

### Key architectural decisions

1. **Two deployables, one repo.** `apps/web` (Next.js) and `apps/api` (Laravel). No microservices.
2. **Laravel owns all business logic.** Next.js renders and calls the API; it never computes prices, entitlements or commissions.
3. **Auth:** Sanctum *stateful SPA* auth with HttpOnly, Secure, SameSite=Lax session cookies on the shared parent domain (`.erudsway.com`) + CSRF cookie. Next.js server components forward the incoming cookie to the API for SSR of logged-in pages. Mobile app later uses Sanctum personal access tokens against the same endpoints.
4. **Money is integers.** All amounts stored as `bigint` minor units (paise) with a `currency` column. No floats anywhere.
5. **Ledger, not counters.** Instructor earnings are an append-only `ledger_entries` table; balances are derived. Refunds and payouts are new entries, never edits.
6. **Entitlement is a row.** Access to paid content = an `active` row in `enrollments`, created only by server-side payment verification (webhook or verified signature), admin grant, or free/100%-coupon flow — inside a DB transaction.
7. **Storage abstraction from day 1.** All files go through Laravel `Storage` disks (`public`, `private`). Moving to S3/R2 later is an `.env` change plus a one-off copy job.
8. **Video provider abstraction.** `VideoProvider` interface; launch implementation is `LocalHlsProvider` (VPS + ffmpeg + Nginx), Cloudflare Stream can be added later without touching course or player code (see §6, decision D1).
9. **OpenAPI is the contract.** Laravel generates the OpenAPI spec; the frontend's typed client is generated from it in CI, so a backend change that breaks the frontend fails the build.

---

## 3. Versions (verified 2026-10-18)

| Component | Version to pin | Notes |
|---|---|---|
| PHP | **8.4** | Laravel 13 supports 8.3–8.5; 8.4 is the mature choice for production |
| Laravel | **13.x** | Released March 2026; bug fixes to Q3 2027, security to Q1 2028 |
| Next.js | **16.3.x** (latest patch at scaffold time) | App Router, React Server Components. Next 15 LTS ends 21 Oct 2026 — do not start on 15 |
| React | 19.x (bundled with Next 16) | |
| TypeScript | 5.x (latest stable) | `strict: true` |
| Node.js | 24 LTS | build + `next start` runtime |
| Tailwind CSS | 4.x | CSS-first config, tokens as CSS variables |
| shadcn/ui | latest CLI | components copied into repo and restyled to our design system |
| FFmpeg / ffprobe | distro package (6.x+) | **New on the VPS because of D1** — video transcoding |
| PostgreSQL | 17 (or 18 if the VPS image ships it) | extensions: `pg_trgm`, `unaccent`, `citext` |
| Redis | 7.4+ / 8.x | |
| Laravel Horizon / Sanctum | versions matching Laravel 13 | |

Exact patch versions are locked by `composer.lock` / `pnpm-lock.yaml` at scaffold time.

### Proposed libraries (packages only, no new infrastructure) — need your OK

**Backend**
| Package | Why |
|---|---|
| `laravel/sanctum`, `laravel/horizon` | In spec |
| `spatie/laravel-permission` | Roles & permissions storage; we still enforce with Policies/Gates |
| `dedoc/scramble` | Generates OpenAPI 3.1 from Form Requests + API Resources (keeps docs truthful). Alternative: `darkaonline/l5-swagger` with annotations (more manual) |
| `razorpay/razorpay` | Official SDK, signature verification helpers |
| `barryvdh/laravel-dompdf` | Invoices & certificates as PDF without installing headless Chrome on the VPS |
| `pestphp/pest`, `laravel/pint`, `larastan/larastan` | Tests, formatting, static analysis |
| `sentry/sentry-laravel` | Monitoring (spec) |

**Frontend**
| Package | Why |
|---|---|
| `react-hook-form`, `zod`, `@hookform/resolvers` | In spec |
| `@tanstack/react-query`, `zustand` | In spec (Zustand only for cart drawer / player UI state) |
| `openapi-typescript` + `openapi-fetch` | Typed REST client generated from the Laravel OpenAPI spec |
| `hls.js` + custom player UI | Plays our encrypted HLS (Safari uses native HLS); custom UI gives the premium look + precise progress events |
| `@tiptap/react` | Rich text for course descriptions / article lectures (sanitised server-side) |
| `vitest`, `@testing-library/react`, `@playwright/test`, `eslint`, `prettier` | In spec |
| `@sentry/nextjs` | Monitoring |

---

## 4. Repository & folder structure

```
erudsway/
├─ apps/
│  ├─ api/                         # Laravel 13
│  │  ├─ app/
│  │  │  ├─ Modules/
│  │  │  │  ├─ Identity/          # auth, users, profiles, roles, instructor applications
│  │  │  │  ├─ Catalog/           # categories, courses (public read side), search
│  │  │  │  ├─ Authoring/         # instructor course editing, curriculum, submissions
│  │  │  │  ├─ Moderation/        # course review queue, instructor approval
│  │  │  │  ├─ Media/             # VideoProvider, video_assets, file uploads
│  │  │  │  ├─ Enrollment/        # entitlements
│  │  │  │  ├─ Learning/          # progress, notes, Q&A, announcements
│  │  │  │  ├─ Assessment/        # quizzes, assignments
│  │  │  │  ├─ Certificate/
│  │  │  │  ├─ Commerce/          # cart, wishlist, orders, invoices, pricing tiers
│  │  │  │  ├─ Payment/           # gateway abstraction, Razorpay, webhooks, refunds
│  │  │  │  ├─ Promotion/         # coupons, campaigns
│  │  │  │  ├─ Review/
│  │  │  │  ├─ Finance/           # ledger, commission rules, payouts, reconciliation
│  │  │  │  ├─ Notification/
│  │  │  │  ├─ Reporting/
│  │  │  │  ├─ Admin/             # admin-only endpoints that orchestrate other modules
│  │  │  │  └─ Platform/          # settings, audit log
│  │  │  │     each module: Models/ Http/{Controllers,Requests,Resources}/ Policies/
│  │  │  │                  Actions/ Services/ Events/ Listeners/ Jobs/ Enums/
│  │  │  │                  routes.php  ModuleServiceProvider.php  Database/{migrations,factories,seeders}
│  │  │  └─ Support/              # Money value object, ApiResponse, base classes
│  │  └─ tests/{Unit,Feature}/<Module>/
│  ├─ web/                         # Next.js 16
│  │  ├─ src/app/
│  │  │  ├─ (marketing)/           # home, category, course landing, search, instructor profile, teach
│  │  │  ├─ (auth)/                # login, register, forgot/reset, verify
│  │  │  ├─ (student)/my/          # my learning, wishlist, purchases, account, certificates
│  │  │  ├─ learn/[courseSlug]/    # immersive player (own layout)
│  │  │  ├─ (checkout)/cart, checkout
│  │  │  ├─ instructor/            # instructor workspace
│  │  │  └─ admin/                 # admin console
│  │  ├─ src/components/{ui,brand,course,player,forms,layout}/
│  │  ├─ src/lib/{api,auth,format,validation}/
│  │  ├─ src/stores/               # zustand (cart drawer, player prefs)
│  │  └─ tests/ (vitest) · e2e/ (playwright)
│  └─ mobile/                      # Expo — after web MVP
├─ packages/
│  └─ api-client/                  # generated OpenAPI types + fetch client (shared by web & mobile)
├─ docs/                           # this plan, ADRs, runbooks, API conventions
├─ infra/                          # nginx, supervisor, systemd units, backup scripts, docker-compose.dev.yml
└─ .github/workflows/
```

JS side uses **pnpm workspaces**; Laravel is a normal Composer project inside `apps/api`.

---

## 5. Design direction — premium, not "AI-template"

### Brand tokens (from the logo)
| Token | Value (to be sampled exactly from the vector logo) | Use |
|---|---|---|
| `--ink-navy` | ≈ `#13284A` | primary brand, headers, primary buttons |
| `--navy-900` | ≈ `#0B1830` | footer, player chrome, dark surfaces |
| `--gold-500` | ≈ `#C9A14A` | accents only: ratings, "Bestseller" marks, focus rings, key underline |
| `--gold-300` | ≈ `#E4C982` | gradient end, highlights on dark |
| `--paper` | `#FAF8F3` | warm off-white page background (not pure white) |
| `--line` | `#E7E2D6` | hairline borders |
| neutrals | warm greys, not blue-greys | text hierarchy |

Gold is used sparingly (< 5% of any screen). Over-using gold is what makes "premium" look cheap.

### What makes it *not* look AI-generated
Things we will **avoid**: purple/indigo gradients, glassmorphism cards, blurry blobs, emoji icons, giant centered hero with "Unlock your potential" copy, identical rounded cards with drop shadows everywhere, Inter-everywhere, stock 3D illustrations.

Things we will **do**:
- **Editorial typography:** a serif display face for headings (proposal: *Fraunces* or *Source Serif 4*) paired with a precise sans for UI (proposal: *Figtree* or *IBM Plex Sans*), with a real type scale and tabular numerals for prices/stats.
- **Information density like Udemy:** course cards that show title, instructor, rating, learners, hours, level and price at a glance; a strict 4/8px grid; hairline rules instead of heavy shadows.
- **Real photography** of Indian learners/instructors (commissioned or licensed), consistent colour grade (navy shadows, warm highlights).
- **One signature motif from the logo:** the winding "S" road — used as a subtle progress path in the learner dashboard and section dividers, not plastered everywhere.
- **Restrained motion:** 150–200ms ease-out on hover/press, no bouncing.
- **Accessible:** WCAG 2.2 AA contrast, full keyboard support (shadcn/Radix primitives), visible gold focus ring.
- **Phase 0 deliverable:** design tokens + component sheet + 4 key screens (Home, Course landing, Player, Instructor curriculum editor) for your approval *before* building pages.

### Key screens (mirroring Udemy's proven IA)
Home · Category/Topic · Search results with filters · Course landing (sticky purchase card, curriculum accordion, preview modal, instructor block, reviews) · Cart & Checkout · My Learning · Player (video + curriculum sidebar + tabs: Overview, Q&A, Notes, Announcements, Reviews) · Instructor dashboard (Courses, Performance, Revenue, Students, Reviews, Q&A) · Course builder (Plan → Create content → Landing page → Pricing → Promotions → Submit) · Admin console.

---

## 6. Video & files

### Decision D1 — approved 2026-10-18: videos on the VPS for now, behind `VideoProvider`
All video code talks to a `VideoProvider` interface (`createUpload`, `ingest`, `status`, `playbackFor(user, asset)`, `delete`). The launch implementation is **`LocalHlsProvider`**; a `CloudflareStreamProvider` can be added later and assets migrated one by one (each `video_assets` row records its `provider`), with no change to courses, lectures, the player API or progress tracking.

### Upload (instructor → VPS)
1. Builder requests `POST /instructor/lectures/{id}/video-upload` → creates `video_assets` row (`status=uploading`) and returns an upload session.
2. Browser sends the file in **resumable 8 MB chunks** (`PUT /instructor/uploads/{session}/chunks/{n}`), so a flaky connection or a 4 GB file never needs one giant request. Nginx `client_max_body_size` stays small (≈ 10 MB).
3. On the last chunk Laravel assembles the file into the private `video-sources` disk, validates it with `ffprobe` (real container/codec, duration, max size, e.g. 4 GB / 4 h), and dispatches `TranscodeVideo` on the `media` queue.

### Transcoding (queue worker on the VPS)
- `ffmpeg` (system package, invoked via Symfony Process) produces **HLS with 3 renditions** — 360p, 720p, 1080p (only up to the source resolution), H.264/AAC, 6-second segments, plus a master playlist, poster image and duration.
- Segments are **AES-128 encrypted** with a random per-video key. The key is stored encrypted in the database (`encrypted` cast), never as a file in any web-served directory.
- The `media` queue runs with **concurrency 1** and `nice`/`ionice` so transcoding can never starve the website or API. Expect roughly 0.5–2× the video's length to process on a 4 vCPU VPS; instructors see "Processing…" and get a notification when ready.
- On failure: retried twice, then `status=failed` with the reason shown to the instructor.
- The original upload is **kept** (private disk) so we can re-encode later or push it to Cloudflare Stream when we migrate.

### Playback security
1. Player calls `POST /api/v1/lectures/{id}/playback` → Laravel checks the lecture is a free preview **or** the user has an active enrollment (or is the owner/admin).
2. Laravel returns a short-lived URL to a **per-user master playlist** (`/api/v1/stream/{asset}/master.m3u8?token=…`, token = signed, user-bound, ~2 h TTL).
3. That playlist is generated on the fly: segment URLs are **Nginx `secure_link` URLs** (HMAC + expiry), and the key URI points to `/api/v1/stream/{asset}/key?token=…`, which re-checks entitlement before returning the 16-byte key.
4. **Segments are served by Nginx directly** from the private HLS directory (no PHP per segment) — only requests with a valid, unexpired signature are served; everything else is 403. No permanent video URL ever reaches the browser.
5. Player shows a faint moving watermark (learner's email) to discourage screen recording. Note: AES-128 HLS — like any non-DRM stream — stops casual downloading and link sharing, not a determined screen recorder.

### Capacity & cost implications (please read)
- **Disk:** original + 3 renditions ≈ 2–2.5× the source size. 100 hours of course video ≈ 150–250 GB. Plan a VPS with an attachable block volume (start ~500 GB) mounted for `video-sources` and `video-hls`, and monitor usage with alerts at 70%.
- **CPU/RAM:** minimum 4 vCPU / 8 GB RAM for web + API + PostgreSQL + Redis + one transcode worker.
- **Bandwidth:** every minute watched leaves the VPS. 720p ≈ 1.2–1.5 GB per viewing hour. Check the VPS plan's monthly transfer cap/overage. When it becomes a problem the first step is putting Cloudflare (CDN, already "plan later" in the spec) in front of segment URLs; the second is switching new uploads to Cloudflare Stream via the interface.
- **Backups:** HLS renditions are reproducible from originals, so backups include originals + DB key data, not the renditions.

### Migration path to Cloudflare Stream (later)
Add `CloudflareStreamProvider` → set it as the default for new uploads → background job uploads each kept original to Stream, waits for `ready`, flips `video_assets.provider`, then deletes local renditions. The player already switches source type per asset.

### Non-video files on VPS
- Disk `public` (thumbnails, avatars) → served by Nginx with long cache headers.
- Disk `private` (resources, PDFs, certificates, invoices) → outside web root; downloaded via `GET /api/v1/files/{id}` that authorizes, then responds with `X-Accel-Redirect` so **Nginx** streams the bytes (PHP isn't tied up). Signed, expiring links for email delivery.
- Upload validation: MIME sniffing + extension allowlist + size limits; images re-encoded; filenames randomised.

---

## 7. Payments, commissions & marketplace finance

### Checkout flow (Razorpay)
```
Cart ──POST /checkout──▶ Laravel: re-price every item server-side (tier price, coupon, tax)
                         create Order(pending) + Razorpay Order (amount, receipt=order.number)
                       ◀─ razorpay_order_id
Browser: Razorpay Checkout ──success──▶ POST /orders/{id}/verify (payment_id, signature)
                         verify HMAC signature → mark "verifying"; fetch payment from Razorpay API
Razorpay ──webhook payment.captured/order.paid──▶ /webhooks/razorpay
                         verify X-Razorpay-Signature → insert webhook_events (unique event id)
                         → queue ProcessPaymentCaptured (idempotent)
                         DB TRANSACTION: order=paid, payment row, enrollments, ledger entries,
                                         coupon redemption, invoice job, receipt email job
```
- Whichever arrives first (verify call or webhook) completes the order; the other becomes a no-op thanks to a `SELECT … FOR UPDATE` on the order and unique constraints on `gateway_payment_id` and `(user_id, course_id)` enrollment.
- Front-end success never grants access by itself; a nightly **reconciliation job** compares Razorpay settlements/payments with our orders and flags mismatches.
- Free courses and 100%-off coupons go through the same Order pipeline with `total=0` (so reporting stays uniform).

### Commission model (decision D2 — proposed defaults, all configurable by admin)
| Sale channel | Instructor share | Platform share |
|---|---|---|
| Organic (marketplace discovery) | 50% | 50% |
| Instructor's own referral link / coupon | 90% | 10% |
| Platform-paid promotion / ads | 30% | 70% |
- Base = **net revenue** = amount paid − GST − gateway fee (decision D3).
- Per-instructor override possible (for star instructors).
- The applied rate is **snapshotted on `order_items`** at sale time, so later changes never rewrite history.

### Ledger & payouts
- On capture: `ledger_entries` credit instructor (state `pending`, `available_at = paid_at + refund window`, e.g. 30 days) and credit platform.
- On refund: equal-and-opposite debit entries; if the instructor share was already paid out, the balance goes negative and is netted from the next payout.
- Monthly payout run (scheduled job): build `payouts` (draft) per instructor from available entries ≥ minimum threshold → admin reviews & approves → paid (manual bank transfer with UTR reference initially; RazorpayX automation later) → `payout_items` link entries so nothing is paid twice.
- **India compliance to confirm with your CA:** GST on course sales (OIDAR rules for foreign buyers), GST-compliant sequential invoice numbering per financial year, TDS under section 194-O on payments to instructors, PAN/GSTIN collection and KYC before first payout. The schema includes the fields; the rates go in admin settings, not code.

### Refunds
Student requests within the refund window (default 30 days, and e.g. < 30% consumed — policy D4) → auto-approve or admin review → Razorpay refund API → webhook `refund.processed` confirms → enrollment `refunded`, ledger reversal, audit log.

### International (decision D5)
Launch with Razorpay (INR, plus Razorpay international cards if enabled on your account). The `PaymentGateway` interface lets us add Stripe later without touching orders/ledger.

---

## 8. Database schema (PostgreSQL)

Conventions: `bigint` identity PKs internally + `uuid` public IDs where exposed in URLs; `timestamptz`; `citext` for emails/codes; FKs with explicit `ON DELETE` (mostly `RESTRICT` for financial tables); soft deletes only where business-meaningful; enums as `varchar` + `CHECK` constraints (easier migrations than PG enums).

### Identity
- `users` (id, uuid, name, email citext **unique**, password, email_verified_at, status[active,suspended,banned], last_login_at, timestamps, deleted_at)
- `user_profiles` (user_id PK/FK, headline, bio, avatar_path, locale, timezone, country, phone)
- spatie tables: `roles`, `permissions`, `model_has_roles`, `role_has_permissions`, …
- `instructor_applications` (id, user_id, status[pending,approved,rejected], answers jsonb, reviewed_by, reviewed_at, notes)
- `instructor_profiles` (user_id PK, slug unique, display_name, bio, commission_override jsonb null, kyc_status, pan_encrypted, gstin, payout_details_encrypted, verified_at)
- `personal_access_tokens`, `sessions`, `password_reset_tokens` (framework)

### Catalog & authoring
- `categories` (id, parent_id FK self, slug unique, name, icon, position, is_active)
- `price_tiers` (id, currency, amount, label, is_active) — instructors can only pick these
- `courses` (id, uuid, instructor_id FK users, category_id, subcategory_id, slug unique, title, subtitle, description_html, language, level[beginner,intermediate,advanced,all], status[draft,in_review,changes_requested,published,unpublished,archived], price_tier_id null (null = free), thumbnail_path, promo_video_asset_id, outcomes jsonb, requirements jsonb, audience jsonb, published_at, **denormalised:** rating_avg numeric(3,2), rating_count, enrollment_count, duration_seconds, lecture_count; `search_vector tsvector` generated; timestamps, deleted_at)
  - indexes: GIN(search_vector), GIN(title gin_trgm_ops), (status, category_id, published_at), (instructor_id), (status, rating_avg desc), (status, enrollment_count desc)
- `course_submissions` (id, course_id, submitted_by, status[pending,approved,rejected], reviewer_id, checklist jsonb, feedback, decided_at)
- `sections` (id, course_id FK cascade, title, position) unique(course_id, position) deferrable
- `lectures` (id, course_id, section_id, title, type[video,article,quiz,assignment], position, is_free_preview, duration_seconds, article_html, video_asset_id null, published bool)
- `video_assets` (id, uuid, owner_id, provider[local_hls,cloudflare_stream], provider_uid null unique, status[uploading,processing,ready,failed], source_disk, source_path, source_size_bytes, hls_path, encryption_key (encrypted), renditions jsonb, duration_seconds, poster_path, failure_reason, meta jsonb)
- `upload_sessions` (id, uuid, user_id, video_asset_id, total_bytes, chunk_size, received_chunks int[], expires_at) — resumable chunk uploads
- `lecture_resources` (id, lecture_id, disk, path, original_name, mime, size_bytes)

### Learning
- `enrollments` (id, user_id, course_id, source[purchase,free,coupon,admin_grant], order_item_id null, status[active,refunded,revoked], granted_at, revoked_at) **unique(user_id, course_id)**
- `lecture_progress` (PK(user_id, lecture_id), course_id, last_position_seconds, max_position_seconds, completed_at, updated_at)
- `course_progress` (enrollment_id PK, completed_count, percent, last_lecture_id, completed_at)
- `notes` (id, user_id, lecture_id, at_seconds, body)
- `questions` / `answers` (course Q&A), `announcements`

### Assessment & certificates
- `quizzes` (id, lecture_id unique, pass_percent, time_limit_seconds null, shuffle)
- `quiz_questions` (id, quiz_id, type[single,multiple], prompt, explanation, position)
- `quiz_options` (id, question_id, body, is_correct, position)
- `quiz_attempts` (id, quiz_id, user_id, score, passed, started_at, submitted_at), `quiz_attempt_answers`
- `assignments` (id, lecture_id, instructions_html), `assignment_submissions` (id, assignment_id, user_id, body, file_path, feedback, graded_by, status)
- `certificates` (id, uuid, enrollment_id **unique**, serial unique, verification_code unique, file_path, issued_at)

### Commerce & payments
- `carts` (id, user_id null unique, guest_token null unique, updated_at), `cart_items` (cart_id, course_id) unique
- `wishlists` (user_id, course_id) PK
- `coupons` (id, code citext unique, kind[percent,fixed,fixed_price], value, owner_type[platform,instructor], instructor_id null, course_id null, channel, max_redemptions, per_user_limit, starts_at, ends_at, is_active, created_by)
- `coupon_redemptions` (id, coupon_id, user_id, order_id) unique(coupon_id, order_id)
- `orders` (id, uuid, number unique, user_id, status[pending,paid,failed,cancelled,partially_refunded,refunded], currency, subtotal, discount_total, tax_total, total, gateway, gateway_order_id unique, paid_at, billing_snapshot jsonb, ip, user_agent)
- `order_items` (id, order_id, course_id, instructor_id, list_price, discount, tax, net_amount, coupon_id null, channel, commission_rate_bps, instructor_amount, platform_amount, refunded_amount)
- `payments` (id, order_id, gateway, gateway_payment_id unique, status, amount, fee, tax_on_fee, method, raw jsonb, captured_at)
- `refunds` (id, order_item_id, payment_id, amount, gateway_refund_id unique, status, reason, requested_by, approved_by, processed_at)
- `webhook_events` (id, gateway, event_id **unique**, type, payload jsonb, signature_valid, status, attempts, last_error, received_at, processed_at)
- `invoices` (id, order_id unique, number unique, financial_year, file_path, issued_at, seller/buyer GST snapshot jsonb)

### Finance
- `commission_rules` (id, channel, instructor_id null, instructor_bps, effective_from, effective_to, created_by)
- `ledger_entries` (id, txn_uuid, account[instructor,platform,tax,gateway_fee], instructor_id null, amount (signed), currency, order_item_id null, refund_id null, payout_id null, status[pending,available,paid], available_at, created_at) — **insert-only** (enforced by app + DB trigger that rejects UPDATE/DELETE on amount fields)
- `payouts` (id, instructor_id, period_start, period_end, gross, tds, net, currency, status[draft,approved,processing,paid,failed], method, reference, approved_by, paid_at)
- `payout_items` (payout_id, ledger_entry_id unique)

### Engagement & platform
- `reviews` (id, user_id, course_id, rating smallint CHECK 1–5, body, status[visible,hidden,flagged], instructor_reply, replied_at) unique(user_id, course_id)
- `notifications` (Laravel database channel), `notification_preferences`
- `audit_logs` (id, actor_id, actor_role, action, subject_type, subject_id, before jsonb, after jsonb, ip, user_agent, created_at) — insert-only, indexed (subject_type, subject_id), (actor_id, created_at)
- `settings` (key PK, value jsonb, updated_by, updated_at)

### Migration plan
Migrations live per module and are ordered by phase (Identity → Catalog → Media → Enrollment/Learning → Commerce/Payment → Finance → Assessment/Certificate → Engagement → Platform). Every migration is reversible, runs in CI against a real PostgreSQL service, and production migrations are applied by the deploy pipeline before switching the release symlink. Seeders provide categories, price tiers, an admin, demo instructors/courses for staging only.

---

## 9. API design

**Conventions:** `/api/v1`, JSON, `snake_case` fields, UUIDs/slugs in URLs, cursor or page pagination (`?page=&per_page≤50`), `?sort=-rating`, `?filter[level]=beginner`. Uniform envelope:
```json
{ "data": {...}, "meta": {"page":1,"per_page":20,"total":312} }
{ "error": { "code": "COURSE_NOT_PURCHASED", "message": "…", "fields": {"email": ["…"]} } }
```
Idempotency-Key header accepted on `POST /checkout`, `POST /refunds`, `POST /admin/payouts/*`.

### Endpoints (abridged)
| Area | Endpoints | Auth |
|---|---|---|
| Auth | `GET /sanctum/csrf-cookie`, `POST /auth/register`, `/auth/login`, `/auth/logout`, `/auth/forgot-password`, `/auth/reset-password`, `/auth/email/verify/{id}/{hash}`, `/auth/email/resend`, `GET /me`, `PATCH /me`, `PUT /me/password` | public / user |
| Catalog | `GET /categories`, `GET /courses` (search + filters: q, category, language, level, rating≥, price free/paid/range, duration; sort), `GET /courses/{slug}`, `GET /courses/{slug}/curriculum`, `GET /courses/{slug}/reviews`, `GET /instructors/{slug}` | public |
| Cart & wishlist | `GET/POST/DELETE /cart/items`, `POST /cart/merge`, `POST /cart/apply-coupon`, `GET/POST/DELETE /wishlist` | guest token / user |
| Checkout | `POST /checkout`, `POST /orders/{uuid}/verify`, `GET /orders`, `GET /orders/{uuid}`, `GET /orders/{uuid}/invoice` | verified user |
| Learning | `GET /my/courses`, `GET /learn/{course}` (curriculum + progress), `POST /lectures/{id}/playback`, `PUT /lectures/{id}/progress`, `POST /lectures/{id}/complete`, notes, Q&A, `GET /files/{id}` | enrolled |
| Assessment | `POST /quizzes/{id}/attempts`, `PUT /attempts/{id}`, `POST /attempts/{id}/submit`, `POST /assignments/{id}/submissions` | enrolled |
| Certificates | `GET /my/certificates`, `GET /certificates/verify/{code}` | user / public |
| Reviews | `POST/PUT/DELETE /courses/{slug}/review`, `POST /reviews/{id}/report` | enrolled |
| Refunds | `POST /order-items/{id}/refund-request` | owner |
| Instructor | `POST /instructor/apply`, `GET/POST /instructor/courses`, `GET/PATCH/DELETE /instructor/courses/{uuid}`, sections & lectures CRUD + reorder, `POST /instructor/lectures/{id}/video-upload` (starts upload session), `PUT /instructor/uploads/{session}/chunks/{n}`, `GET /instructor/video-assets/{uuid}` (processing status), `POST /instructor/courses/{uuid}/submit`, coupons, `GET /instructor/analytics/*`, `GET /instructor/earnings`, `GET /instructor/payouts`, `PATCH /instructor/payout-details`, Q&A replies, review replies | instructor (own resources) |
| Admin | users (list, suspend, roles, impersonate), instructor applications (approve/reject), course moderation queue (approve / request changes / unpublish), categories, price tiers, commission rules, coupons, orders, refunds (approve/process), payouts (generate/approve/mark paid), reviews moderation, reports, settings, audit logs | admin permissions |
| Streaming | `GET /stream/{asset}/master.m3u8?token=`, `GET /stream/{asset}/{rendition}.m3u8?token=`, `GET /stream/{asset}/key?token=` (segments served by Nginx `secure_link`) | signed token + entitlement |
| Webhooks | `POST /webhooks/razorpay` | signature only |

### Authorization rules
- **Roles:** `student` (default), `instructor`, `admin`, plus fine-grained staff permissions (`moderate_courses`, `manage_finance`, `manage_users`, `view_reports`, `manage_settings`) so you can hire support staff without giving finance access.
- **Policies (examples):**
  - `CoursePolicy@update`: `user.id == course.instructor_id` and status not `in_review` — or admin.
  - `CoursePolicy@publish`: admin with `moderate_courses` only.
  - `LecturePolicy@play`: `lecture.is_free_preview` **or** active enrollment **or** course owner **or** admin.
  - `ReviewPolicy@create`: active enrollment, one per course.
  - `RefundPolicy@request`: order owner, within window, not already refunded.
  - `PayoutPolicy@approve`: `manage_finance`; maker-checker — the admin who generated a payout batch cannot approve it (optional, recommended).
- Suspended users: global middleware blocks authenticated actions. Impersonation is read-mostly, banner-shown, and audit-logged.
- **Rate limits:** login 5/min per email+IP, register 3/min/IP, password reset 3/hour, checkout 10/min/user, playback tokens 60/min/user, key requests 120/min/user, upload chunks 600/min/user, general API 120/min.

---

## 10. Security checklist (OWASP-aligned)
HTTPS + HSTS · Secure/HttpOnly/SameSite cookies · CSRF via Sanctum · bcrypt/argon2id passwords, breached-password check optional · email verification required before purchase · Form Request validation everywhere, API Resources for output (no mass-assignment of `role`, `status`, `price`) · HTML from rich text sanitised server-side (allowlist) · CSP, X-Frame-Options, Referrer-Policy headers via Nginx/Next middleware · signed webhook verification + idempotency · encrypted columns for PAN/bank details (`encrypted` cast) · secrets only in `.env` / secret manager, never in repo · least-privilege DB user for the app · Sentry with PII scrubbing · audit logs for admin, finance, moderation, and course-pricing changes · dependency scanning (`composer audit`, `pnpm audit`, Dependabot) in CI.

---

## 11. Search & discovery
- Phase 2: PostgreSQL full-text search — generated `tsvector` (title weight A, subtitle/outcomes B, description C, instructor name B) with `unaccent`, plus `pg_trgm` similarity for typo tolerance and autocomplete. Filters map to indexed columns; facet counts computed with grouped queries and cached in Redis (60s).
- Search lives behind a `CourseSearch` interface so Meilisearch can be swapped in later (spec) when catalog/traffic demands — that would be a new service and will come to you for approval first.

## 12. Notifications & background jobs
- Queues (Redis, Horizon): `critical` (payments/webhooks), `default` (emails, enrollments), `media` (ffmpeg transcoding — concurrency 1, low CPU priority, long timeout), `reports` (exports, certificates PDFs). Retries with exponential backoff; failed jobs logged + Sentry alert.
- Emails (queued, Markdown mailables in brand style): welcome/verify, password reset, purchase receipt + invoice, refund status, course approved/changes requested, new review/question (instructor), payout processed, course update announcements.
- Scheduled: release pending earnings → available (daily), payout draft generation (monthly), Razorpay reconciliation (daily), abandoned-cart reminder (optional), denormalised stats refresh, backups verification.
- SMS/WhatsApp: interface only for now; provider chosen later (approval needed).

## 13. Testing, quality & delivery

**Testing pyramid**
- Pest unit tests: Money, pricing/coupon calculator, commission splitter, ledger balance, refund math.
- Pest feature/API tests per endpoint: happy path + authorization matrix (guest / student / other instructor / owner / admin) + validation.
- Payment tests: signed webhook fixtures, duplicate delivery, out-of-order events, verify-then-webhook and webhook-then-verify races, refund webhooks, failed payments.
- Vitest + RTL: forms, cart, player controls, price display.
- Playwright E2E (against staging-like stack with Razorpay test mode): register → verify → buy → watch → progress → certificate; instructor create → submit → admin approve → visible in catalog; refund.
- Quality gates in CI: Pint, Larastan (level 6+), ESLint, Prettier, `tsc --noEmit`, OpenAPI client regen diff check, test coverage report on Finance/Payment/Enrollment modules (target ≥ 90% there).

**CI/CD (GitHub Actions):** PR → lint + tests (PostgreSQL & Redis service containers) → build. Merge to `develop` → deploy staging. Tag/release → deploy production (zero-downtime release folders + symlink switch, `php artisan migrate --force`, `horizon:terminate`, Next build & restart via systemd).

**Environments:** local (Docker Compose for Postgres/Redis/Mailpit only — app runs natively), staging (own DB, Razorpay test keys, sample test videos), production. Completely separate credentials.

**VPS layout (initial):** min 4 vCPU / 8 GB RAM + ~500 GB block volume for video. Nginx (TLS via Certbot auto-renew, `secure_link` for HLS segments) → PHP-FPM 8.4 for `api.`, Node 24 `next start` (systemd) for `www.`; ffmpeg for the media worker; disk-usage and bandwidth alerts; PostgreSQL + Redis on the same VPS initially (managed Postgres later); Supervisor running Horizon; nightly `pg_dump` + storage sync to off-server backup, monthly restore test; UptimeRobot-style uptime checks + Sentry.

---

## 14. Delivery phases

Each phase ends with: passing tests, deployed to staging, demo to you, sign-off before the next.

| # | Phase | Main outputs | Est.* |
|---|---|---|---|
| 0 | **Foundation & design system** | Monorepo, tooling, CI, dev environment, Laravel + Next skeletons, API conventions, OpenAPI → typed client pipeline; brand tokens, component sheet and 4 key screen designs for approval | 2 wk |
| 1 | **Identity & access** | Register/login/logout, verify email, reset password, profiles, roles/permissions, instructor application + admin approval, rate limiting, audit log foundation | 1.5 wk |
| 2 | **Catalog & discovery (public site)** | Categories, course model, price tiers, home, category pages, course landing page, PG full-text search with filters/sorting/pagination, instructor public profile, SEO (metadata, sitemap, JSON-LD) | 2.5 wk |
| 3 | **Instructor course builder + media** | Course CRUD, curriculum editor (drag reorder), `VideoProvider` + `LocalHlsProvider`: resumable chunk upload, ffmpeg HLS transcoding with AES-128, processing status; resources upload, landing page & pricing step, submit for review, admin moderation queue | 4 wk |
| 4 | **Enrollment & learning player** | Entitlements, per-user signed playlists + key endpoint + Nginx `secure_link`, hls.js player UI with watermark, progress tracking/resume, My Learning, free courses & admin grants, notes | 2.5 wk |
| 5 | **Cart, checkout & payments** | Cart (guest + merge), wishlist, coupons, Razorpay checkout + verify + webhooks, orders, GST invoices, receipts, refund request/approval flow, reconciliation job | 3 wk |
| 6 | **Marketplace finance** | Commission rules, ledger, instructor revenue dashboard, payout generation/approval/marking paid, KYC/payout details, finance reports | 2 wk |
| 7 | **Reviews, Q&A, notifications** | Ratings & reviews + moderation, course Q&A, announcements, email notification set, preferences | 1.5 wk |
| 8 | **Assessments & certificates** | Quizzes, assignments, completion rules, PDF certificates + public verification page | 2 wk |
| 9 | **Admin console & analytics** | Full admin dashboards, user management, impersonation, reports (sales, instructors, courses, refunds), settings UI, audit log viewer, CSV exports | 2 wk |
| 10 | **Hardening & launch** | Security review, load test of catalog/playback/checkout, performance budgets (LCP < 2.5s on course page), backups + restore drill, production deploy, monitoring alerts | 1.5 wk |
| 11 | **Mobile app (Expo)** | After web MVP: auth, browse, My Learning, player, progress, offline downloads (encrypted HLS download within the app, policy-compliant), purchases per App/Play Store rules | separate plan |

\*Single experienced full-stack developer working with Claude, sequential. Total for web ≈ 25–27 weeks (includes ~1.5 wk for self-hosted video, D1); **MVP that can take real money = Phases 0–7 (~19.5 weeks)**, with assessments/certificates and the full admin console following.

---

## 15. Decisions (all accepted 2026-10-18 — D1 as VPS video, D2–D13 as recommended)

| ID | Decision | Recommendation |
|---|---|---|
| D1 | Videos on Cloudflare Stream now, or temporarily on VPS? | ✅ **Decided 2026-10-18: VPS for now** (`LocalHlsProvider` behind `VideoProvider`; Stream later) |
| D2 | Commission percentages per channel | 50/50 organic, 90/10 instructor-referral, 30/70 platform-promoted — your call |
| D3 | Commission base: gross or net of GST + gateway fees? | Net |
| D4 | Refund policy | 30 days, auto-approve if < 30% consumed, otherwise admin review |
| D5 | International payments at launch? | INR-only via Razorpay at launch; Stripe evaluated after company registration check |
| D6 | Who can become an instructor? | Application + manual approval (matches "full control") |
| D7 | Pricing freedom | Admin-defined price tiers only |
| D8 | Payout method | Monthly, manual bank transfer recorded in system; automate with RazorpayX later |
| D9 | Library list in §3 | Approve as listed |
| D13 | VPS size & monthly bandwidth allowance (needed for D1 capacity planning) | ≥ 4 vCPU / 8 GB / 500 GB volume; share the provider plan |
| D10 | Domains | `www.erudsway.com` + `api.erudsway.com` (confirm actual domain) |
| D11 | Fonts | Pick display + UI pair from the Phase 0 type specimens |
| D12 | Source of high-quality original logo (SVG/AI) | Needed to extract exact brand colours |
