# Infrastructure — pd02-cms (Digital Violence Info backend)

Headless **Craft CMS 5** (PHP 8.5) serving content over GraphQL to a decoupled
frontend. Control panel is served from the site root (`CRAFT_CP` in
`web/index.php`); `/api` is the public GraphQL endpoint (`config/routes.php`).

## Hosting
Two Laravel Forge–provisioned VPSes:

| | Staging | Production |
|---|---|---|
| Server | `urmel` (178.105.243.166, shared with tv01-cms) | `ace` (159.69.187.131, shared with cms.wayout.ch) |
| SSH | `urmel` alias / `ssh urmel` | `ace` alias / `ssh ace` |
| Site path | `/home/forge/pd02.cms.dev.dil.uno/` | `/home/forge/cms.digital-violence.info/` |
| PHP / MySQL | 8.5.6 / 8.4 | 8.5.10 / 8.4.11 |

- Web root on both: `.../current/web`. Services: nginx, PHP-FPM 8.5, MySQL 8.4.
- Staging path was renamed from `pd02-cms.on-forge.com` on 2026-08-05.
- **SSH rate limiting:** both servers block the client IP on port 22 after
  a handful of quick new connections (seen 2026-10-16, ~10+ min to clear;
  ufw limit or fail2ban, not confirmed). `~/.ssh/config` uses
  `ControlMaster`/`ControlPersist` for both hosts — batch remote commands,
  and if port 22 times out, stop retrying.

## Deploys
- **Push-to-deploy via Forge:** push to `main` on
  `git@github.com:diluno/pd02-cms.git` → Forge builds a new
  `releases/<numeric-id>` and swaps the `current` symlink (zero-downtime).
  Staging auto-deploys; check in Forge whether production has Quick Deploy
  on too (not verified at launch).
  Deploy script lives in the Forge dashboard, not the repo.
- **Never hot-patch the server** — opcache runs with
  `validate_timestamps = 0`, so in-place PHP edits are invisible until
  `sudo service php8.5-fpm reload`. Always deploy through git.

## Environments
| Env | URL | Where |
|---|---|---|
| dev | `https://pd02-cms.ddev.site` (CP) | local DDEV |
| staging | `https://pd02.cms.dev.dil.uno` | urmel (`CRAFT_ENVIRONMENT=staging`) — **CP disabled** |
| production | `https://cms.digital-violence.info` (CP) | ace (`CRAFT_ENVIRONMENT=production`), live since 2026-10-16 |

- Production frontend: `PRIMARY_SITE_URL=https://digital-violence.info`.
- **Staging CP is disabled** since the prod launch (2026-10-16): a rule in
  the Forge nginx config for `pd02.cms.dev.dil.uno` returns 403 for every
  request except `^/api`, so the staging frontend still gets GraphQL data.
  Remove the `$cp_block` lines in Forge → Edit Nginx Configuration to
  re-enable.

- Locally the CP is reachable via `CRAFT_BASE_CP_URL`; `PRIMARY_SITE_URL`
  points at the frontend (`http://pd02.dev.dil.uno`).
- On staging the CP is at the site root; `/` redirects to `/login`.

## Domains & DNS
- `pd02.cms.dev.dil.uno` → 178.105.243.166 (urmel); `dil.uno` DNS is on
  **Cloudflare**. TLS via Forge/Let's Encrypt.
- `cms.digital-violence.info` → 159.69.187.131 (ace). TLS via Forge.

## Database
- MySQL 8.4 on localhost, database `pd02`, user `forge` — same names on
  both servers.
- Credentials: each server's site `.env`.
- Production DB was migrated from staging on 2026-10-16 (73 tables,
  1832 entries at launch). **Production is now the source of truth**;
  staging content is frozen and will drift.

## Asset storage
- **Cloudflare R2**, bucket `pd02`, public base URL
  `https://pub-c6d329c4abc4440cae59886a4775ec38.r2.dev`.
- ⚠️ Staging and production use the **same R2 account and bucket**.
  Fine while the staging CP is disabled, but an asset deleted on one env
  disappears for the other.

## Mail
- Postmark SMTP (`smtp.postmarkapp.com:2525`), set via `SMTP_*` in each
  server's `.env`. Staging and production use the same Postmark
  SMTP username.

## Cron / queue
- **Production (ace):** Forge daemon `daemon-1152197`,
  `php8.5 craft queue/listen --verbose` in `.../current/`, `stopwaitsecs=300` (raised from 15 on 2026-10-16), `stopasgroup=true` — same mid-deploy job-kill caveat as staging below. The deploy
  script must run `sudo -S supervisorctl restart daemon-1152197:*` — note
  the different daemon ID from staging. That line was missing at launch.
- On launch day (2026-10-16) the daemon was started before the DB import,
  crash-looped on missing tables and went **`FATAL`**; supervisor never
  retries from FATAL, so no jobs ran until a manual restart. If
  `sudo -n supervisorctl status` shows FATAL, check
  `~/.forge/daemon-1152197.log`, then restart.
- **Staging (urmel):** Forge background process `daemon-1099199` ("craft queue"):
  `php8.5 craft queue/listen --verbose` as `forge`, in `.../current`,
  graceful shutdown 310s. Added 2026-09-22 after a web-triggered
  propagation job hung and stalled the queue.
- `CRAFT_RUN_QUEUE_AUTOMATICALLY=false` in both server `.env`s.
- The daemon holds the release dir it started in — deploy script must run
  `sudo -S supervisorctl restart daemon-1099199:*`.
- No crontab for `forge` on urmel (ace has the backup cron only).
- Supervisor config has `stopasgroup=true`, so the deploy restart SIGTERMs
  the isolated `queue/exec` child immediately (despite `stopwaitsecs=310`).
  Any job running mid-deploy fails with `The process has been signaled with
  signal "15"` (seen 2026-10-15 on an UpdateSearchIndex job). Retry with
  `php8.5 craft queue/retry <id>`.

## Backups
- **Production (ace): covered.** Nightly cron `30 3 * * *` runs
  `~/bin/backup-sites.sh` (restic → Exoscale SOS, `diluno-backups`). It
  auto-discovers every site's `.env`, so the `pd02` DB is included from the
  first run after launch (2026-10-17 03:30). Log: `~/backups/backup.log`.
- **Staging (urmel): ⚠️ NOT covered.** urmel has no restic setup (`~/.restic-env` and `~/bin/backup-sites.sh`
  absent; no repo for `urmel` in the `diluno-backups` bucket as of
  2026-08-05). The staging `pd02` and `tv01` databases are unprotected. For pd02 this
  matters less now that production is the source of truth.
- To fix: follow the "Adding a new server" steps in the `vps-backups` skill
  (new restic password + Exoscale key, nightly cron at a staggered time).

## Known quirks
- `opcache.validate_timestamps = 0` (see Deploys).
- `CRAFT_DISALLOW_ROBOTS=false` in staging `.env`, though
  `.env.example.staging` says `true` (moot while the staging CP is 403'd).
- `license.key` and `license.key.1` both in `config/` — the `.1` is likely
  a leftover duplicate.
- Old nginx site config `pd02-cms.on-forge.com` still present in
  `/etc/nginx/sites-enabled/` after the rename.
- Secrets live in: local `.env` (gitignored), server `.env`s, Forge
  dashboard. Never committed.

_Last audited: 2026-10-16 (production launch on ace)_
