# ENLIXO Tutor

Tutor LMS for Sri Lankan tuition classes: classes, timetables, recordings with watch-time limits, Zoom auto-scheduling,
custom staff roles, and START / GROW / PRO packages with premium locks.

- **Plan:** [docs/PLAN.md](docs/PLAN.md) (what is done, what is next)
- **Product plan (tutor-first):** [docs/TUTOR-PRODUCT-PLAN.md](docs/TUTOR-PRODUCT-PLAN.md)
- **Research:** [docs/RESEARCH.md](docs/RESEARCH.md) (how other LMS / SaaS products do it, and the rules we follow)

## Layout

The existing app (PHP pages in `tutor/`, `portal/`, `admin/`, `includes/`) stays as it is. New code sits beside it:

| Path | What |
|---|---|
| `api/v1/` | JSON API front controller (uses the existing login session) |
| `src/` | API code, namespace `Enlixo\` (Controller → Service → Repository) |
| `assets/js/enlixo/`, `assets/css/enlixo-ui.css` | Reusable UI kit. All classes start with `ex-` so they never clash with Bootstrap |
| `includes/enlixo/` | Small PHP helpers the old pages include (e.g. the recording watch-time guard, the dashboard parts in `dashboard.php`) |
| `design-demo/` | The new screens on demo data, no database needed |

## Run locally

```bash
cp config/config.php config/config.local.php   # set DB details + APP_KEY
php -S 127.0.0.1:8080 bin/dev-router.php       # mimics the .htaccess rules
# app:   http://127.0.0.1:8080/
# demo:  http://127.0.0.1:8080/design-demo/   (?plan=start|grow|pro to see locks change)
```

## Run the tests

```bash
php tests/run.php
```

No packages to install. Tests use in-memory fakes, so they don't need MySQL.

## Install on a server

1. Run the files in `database/migrations/` in order (`001_…`, `002_…`) on the existing `enlixo_tutor` database.
   It only **adds** tables and columns; nothing existing is renamed or dropped. Take a backup first.
2. Copy `config/config.php` to `config/config.local.php` (or set env vars) with DB details, `APP_KEY`
   (`php -r "echo base64_encode(random_bytes(32));"`) and Zoom keys.
3. `php bin/seed.php` writes the package features and permission list to the database.
4. Upload `api/v1/` with its `.htaccess` (it sends every `/api/v1/*` request to `index.php`).
5. Cron, once a day: `php bin/generate-sessions.php` (creates timetable sessions + Zoom meetings for the next 2 weeks).

## Code map

| Folder | What |
|---|---|
| `src/Core` | Router, Request, JsonResponse, Database (PDO), Crypto (AES-256-GCM), Clock |
| `src/Support/ListQuery.php` | One search / filter / sort / page object for every list endpoint |
| `src/Catalog` | **Single source of truth** for package features and staff permissions |
| `src/Entitlements` | What a tutor's package allows (`has`, `require`, student limit) |
| `src/Roles` | Custom roles, `PermissionService` (role ∩ package), audit log |
| `src/Recordings` | Watch-time limits: rules, overrides, server-counted heartbeats |
| `src/Students`, `src/Classes` | Tutor-scoped lists for the real pages |
| `src/Dashboard` | Numbers for the tutor, admin, student and parent dashboards (`DashboardRepository`) and date helpers (`Stats`) |
| `src/Scheduling` | Weekly slots → timetable sessions → Zoom meetings; Zoom OAuth / S2S clients; webhook verifier |
| `assets/js/enlixo/core` | `ApiClient`, `Component`, `UrlState`, DOM helpers |
| `assets/js/enlixo/components` | `DataView`, `SearchInput`, `FilterBar`, `Pagination`, `LoadingState`, `EmptyState`, `FeatureGate`, `Modal`, `Toast`, `Tabs`, `Meter` |
| `assets/js/enlixo/pages` | One small file per page, built from the components |
