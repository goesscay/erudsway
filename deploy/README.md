# Deploying Erudsway

The app runs on the existing VPS next to Moodle (erudsway.com) and the e-Filing simulator
(simulator.erudsway.com), without touching either. Layout on the server:

```
/var/www/erudsway/
  repo.git/            bare repository that deploys are pushed to
  releases/<stamp>/    one directory per release (last 5 kept)
  current -> releases/<stamp>
  shared/api.env       Laravel settings and secrets (600, erudsway)
  shared/web.env       Next.js public URLs
  shared/storage/      uploads, video, logs — survives releases
  bin/                 release, rollback, backup, restore-drill
```

| Piece | How it runs |
|---|---|
| Web app (Next.js) | `erudsway-web.service` on 127.0.0.1:3200 (the simulator uses 3100) |
| API (Laravel) | php-fpm pool `erudsway` (`/run/php/php8.5-fpm-erudsway.sock`) |
| Queues | `erudsway-horizon.service` — payments/emails/invoices, plus one ffmpeg job at a time at low priority |
| Scheduled tasks | `erudsway-scheduler.timer` (every minute): reconciliation, earnings release, monthly payouts, `ops:check` |
| Nginx | `/etc/nginx/sites-available/erudsway`; Cloudflare-only origin lock; HLS segments served directly with `secure_link` |
| Redis | sessions, cache, queues (`erudsway_` key prefix) |
| PostgreSQL 18 | database `erudsway`, role `erudsway` |

## First-time setup

1. **DNS** — in Cloudflare add `A  new  200.234.46.64`, proxied (orange cloud).
2. **Provision** (root, from a copy of this repo on the server):
   ```bash
   sudo DOMAIN=new.erudsway.com ALERT_EMAIL=you@example.com deploy/server/bin/provision
   sudo certbot certonly --webroot -w /var/www/html -d new.erudsway.com
   sudo DOMAIN=new.erudsway.com ALERT_EMAIL=you@example.com deploy/server/bin/provision   # again, now with TLS
   ```
3. **Deploy** from your machine: `deploy/push` (pushes `main` and runs `bin/release` on the server).
4. **Enable services** once the first release is live:
   `sudo systemctl enable --now erudsway-web erudsway-horizon erudsway-scheduler.timer`
5. **First admin** — register on the site with your own password, then on the server:
   `sudo -u erudsway php /var/www/erudsway/current/apps/api/artisan identity:promote-admin you@example.com --mark-verified`
   (`--mark-verified` is only needed while the server can't send the verification email).

## Every deploy

```bash
deploy/push            # or: deploy/push <branch-or-sha>
```

The release script builds in a new directory, runs migrations, switches `current`, restarts
the web app and queue workers, and checks `/api/v1/health` and the home page. If the new
release isn't healthy it switches straight back (migrations stay applied — write migrations
that only add, so the previous release still works with the new schema).

Manual rollback: `ssh ria-vps 'sudo -u erudsway /var/www/erudsway/bin/rollback'`.
Deploy log: `/var/log/erudsway/deploy.log`.

## Backups

`/etc/cron.d/erudsway-backup` runs `bin/backup` at 03:45 (after Moodle's at 03:15):

- PostgreSQL dump → `/var/backups/erudsway/db/` (last 14)
- `storage/app` snapshot with hard links → `/var/backups/erudsway/files/` (last 7; unchanged videos
  cost no extra space; HLS output is left out because it can be rebuilt from the kept originals)

**Backups stay on this server** (decided 2026-10-19). They cover mistakes and bad deploys, not
losing the server. Revisit before go-live.

**Restore drill** (monthly, and before go-live): `sudo /var/www/erudsway/bin/restore-drill` restores
the newest dump into a throwaway database, compares row counts and drops it.

Full restore: stop `erudsway-web` and `erudsway-horizon`, `pg_restore --clean -d erudsway <dump>`,
`rsync -a <snapshot>/ /var/www/erudsway/shared/storage/app/`, start the services.

## Monitoring

- `ops:check` (every 5 minutes) emails `OPS_ALERT_EMAIL` about failed jobs, stuck payment webhooks,
  Horizon down, queue backlog, disk under 10% and backups older than 26 hours — once per problem
  every six hours. Try it: `php artisan ops:check --dry-run`.
- It needs working mail. Until a mail service is configured (`MAIL_MAILER=log`), alerts go to
  `storage/logs` only.
- The server can't report its own outage: add a free external monitor (e.g. UptimeRobot) on
  `https://new.erudsway.com/api/v1/health` — it answers 503 when the database or Redis is down.

## Moving a course from Moodle

1. Export it (root; read-only towards Moodle, copies its files out of moodledata):
   `/root/erudsway-deploy/server/bin/moodle-export --course=206 --out=/var/www/erudsway/shared/imports/moodle-206`
2. Import the course as a draft (as `erudsway`, in `current/apps/api`). Videos are *moved* out of
   the bundle into video storage and queued for transcoding (about 1 hour of CPU per 5 hours of
   footage, at low priority):
   `php artisan moodle:import-course /var/www/erudsway/shared/imports/moodle-206 --instructor=EMAIL --price=7800 --skip-sections=15,16,17`
   Re-running is safe: it continues where it stopped.
3. In Admin › Courses › Edit in builder, fill in the landing page (outcomes, requirements,
   audience), check the curriculum, and Publish now once every video is ready.
4. Bring the learners across (accounts keep their Moodle password until first sign-in; access,
   completed lessons and certificates are carried over):
   `php artisan moodle:import-learners /var/www/erudsway/shared/imports/moodle-206 --admin=EMAIL`
5. Delete the bundle (`rm -r shared/imports/moodle-206`) — it holds password hashes.

## Settings that need values before go-live

In `shared/api.env`: `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`, `RAZORPAY_WEBHOOK_SECRET`, and the
mail settings (`MAIL_MAILER=smtp`, host, user, password). GST, seller and TDS values are edited in
the admin console under Settings.
