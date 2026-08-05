# pd02-cms

> Headless [Craft CMS 5](https://craftcms.com/) backend for **Digital Violence Info** —
> content is managed in Craft and delivered to a decoupled frontend over a public
> GraphQL API.

## How it's put together

```
                    ┌──────────────────────────────┐
   Editors ────────▶│  Craft control panel  (/)    │
                    │                              │
   Frontend ───────▶│  GraphQL API  (/api)         │
                    └──────────────────────────────┘
                         headless Craft CMS 5
```

- **Headless mode** — Craft renders no front-end pages
  (`headlessMode(true)` in [`config/general.php`](config/general.php)).
- **Control panel at the site root** — no `/admin` prefix. `web/index.php`
  sets the `CRAFT_CP` constant for every request *except* `/api`, which stays
  a site request so the GraphQL route keeps working.
- **GraphQL** — `/api` (routed in [`config/routes.php`](config/routes.php)),
  served from Craft's **public schema**. Anything the frontend needs must be
  enabled on that schema.

## Local development

Requires [DDEV](https://ddev.com/) (PHP 8.4, MySQL — all containerized).

```bash
git clone git@github.com:diluno/pd02-cms.git && cd pd02-cms
cp .env.example.dev .env        # then fill in CRAFT_SECURITY_KEY etc.
ddev start
ddev composer install
ddev craft install              # first time only
```

| What | Where |
|---|---|
| Control panel | https://pd02-cms.ddev.site |
| GraphQL API | https://pd02-cms.ddev.site/api |
| Craft CLI | `ddev craft <command>` |

The local `.env` sets `CRAFT_BASE_CP_URL` to the DDEV hostname and
`PRIMARY_SITE_URL` to the frontend URL — that split is what makes the CP and
the API coexist. Environment templates for each stage live in
`.env.example.{dev,staging,production}`.

## Environments & deploys

| Env | URL | Deploys |
|---|---|---|
| dev | `pd02-cms.ddev.site` | your machine |
| staging | `pd02.cms.dev.dil.uno` | push to `main` → Forge auto-deploy |
| production | — | not live yet |

Deploys are **git-only**: push to `main` and Laravel Forge builds a fresh
release and swaps the symlink. Never edit code on the server — opcache there
never revalidates timestamps, so hot-patches silently don't apply. Details,
credentials locations, and known quirks: [`infra.md`](infra.md).

## Project config

Craft's schema (sections, fields, GraphQL schemas, …) is tracked in
[`config/project/`](config/project). Staging runs with
`CRAFT_ALLOW_ADMIN_CHANGES=false`, so **schema changes are made locally**,
committed, and applied on deploy — content lives in the database, structure
lives in git.

```bash
ddev craft up                   # apply migrations + project config after a pull
```

## Useful commands

```bash
ddev craft clear-caches/all     # when things look stale
ddev craft graphql/list-schemas # inspect GraphQL schemas
ddev craft db/backup            # local database snapshot
```
