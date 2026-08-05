# Infrastructure — pd02-cms (Digital Violence Info backend)

Headless **Craft CMS 5** (PHP 8.5) serving content over GraphQL to a decoupled
frontend. Control panel is served from the site root (`CRAFT_CP` in
`web/index.php`); `/api` is the public GraphQL endpoint (`config/routes.php`).

## Hosting
- **Provider:** Laravel Forge–provisioned VPS (shared with tv01-cms)
- **SSH alias:** `urmel` (`ssh forge@178.105.243.166`, from `~/.zshrc`)
- **Server hostname:** `urmel`
- **OS services:** nginx (1.31), PHP-FPM 8.5, MySQL 8.4
- **PHP:** 8.5.6 (server) — local DDEV; Node v22 on server (unused by this app)
- **Site path:** `/home/forge/pd02.cms.dev.dil.uno/`
  (renamed from `pd02-cms.on-forge.com` on 2026-08-05)
- **Web root:** `.../current/web`

## Deploys
- **Push-to-deploy via Forge:** push to `main` on
  `git@github.com:diluno/pd02-cms.git` → Forge builds a new
  `releases/<numeric-id>` and swaps the `current` symlink (zero-downtime).
  Deploy script lives in the Forge dashboard, not the repo.
- **Never hot-patch the server** — opcache runs with
  `validate_timestamps = 0`, so in-place PHP edits are invisible until
  `sudo service php8.5-fpm reload`. Always deploy through git.

## Environments
| Env | URL | Where |
|---|---|---|
| dev | `https://pd02-cms.ddev.site` (CP) | local DDEV |
| staging | `https://pd02.cms.dev.dil.uno` | urmel (`CRAFT_ENVIRONMENT=staging`) |
| production | — | not deployed yet (`.env.example.production` prepared) |

- Locally the CP is reachable via `CRAFT_BASE_CP_URL`; `PRIMARY_SITE_URL`
  points at the frontend (`http://pd02.dev.dil.uno`).
- On staging the CP is at the site root; `/` redirects to `/login`.

## Domains & DNS
- `pd02.cms.dev.dil.uno` → 178.105.243.166 (urmel); `dil.uno` DNS is on
  **Cloudflare**. TLS via Forge/Let's Encrypt.

## Database
- MySQL 8.4 on localhost, database `pd02`, user `forge`.
- Credentials: server `.env` (`/home/forge/pd02.cms.dev.dil.uno/.env`).

## Asset storage
- **None configured yet** — no filesystems/volumes in project config
  (unlike tv01's R2 setup). Decide before content entry starts.

## Mail
- Craft's **Sendmail** transport, from `sam@diluno.com` — i.e. whatever the
  server's local MTA does. No real provider configured; set one up before
  production.

## Cron / queue
- **No crontab** for `forge` — Craft's queue runs via web requests
  (`runQueueAutomatically`). Add a queue runner before production.

## Backups — ⚠️ NOT COVERED
- **urmel has no restic setup** (`~/.restic-env` and `~/bin/backup-sites.sh`
  absent; no repo for `urmel` in the `diluno-backups` bucket as of
  2026-08-05). The `pd02` and `tv01` databases are unprotected — the only
  copies are on this one VPS.
- To fix: follow the "Adding a new server" steps in the `vps-backups` skill
  (new restic password + Exoscale key, nightly cron at a staggered time).

## Known quirks
- `opcache.validate_timestamps = 0` (see Deploys).
- `CRAFT_DISALLOW_ROBOTS=false` in staging `.env`, though
  `.env.example.staging` says `true` — staging is on a public domain, so
  this should probably be flipped.
- `license.key` and `license.key.1` both in `config/` — the `.1` is likely
  a leftover duplicate.
- Old nginx site config `pd02-cms.on-forge.com` still present in
  `/etc/nginx/sites-enabled/` after the rename.
- Secrets live in: local `.env` (gitignored), server `.env`, Forge
  dashboard. Never committed.

_Last audited: 2026-08-05_
