# ZeeDiet Clinic Management System

A Persian (RTL) web application for a nutrition and weight-loss clinic in Rafsanjan, Iran. Four role-based areas on one database: admin and secretary (`/admin`), device operator (`/operator`), nutrition specialist (`/specialist`), and client portal (`/my-account`).

The full build specification is in [docs/SPEC.md](docs/SPEC.md). Read it before changing code.

## Status

**Sprint S0 is complete** (pending CI on its branch). The Laravel 13 project, the Persian layout, helpers, input normalisation and preview pages are in place. S1 (identity and clients) is done: S1-T1 to S1-T7 are complete (CI green on `0139170`). S2 (scheduling) is done: S2-T1 to S2-T6 are complete (CI green on `a3ec3d9`). S3 (operator, packages, measurements) is done: S3-T1 to S3-T5 are complete (CI green on `36d07b5`). Deferred items are listed in the sprint notes. Sprint notes: [docs/sprints/S3.md](docs/sprints/S3.md). Sprint notes: [docs/sprints/S2.md](docs/sprints/S2.md).

| Area | State |
|---|---|
| Specification | Done (`docs/SPEC.md`) |
| Laravel 13 project (PHP 8.4, owner approved) | Done, first CI run pending |
| Date, digit and mobile helpers (`app/Support`) | Done and verified |
| Blade directives `@fa`, `@jdate`, `@jdatetime`, `@money` | Done |
| Input normalisation middleware, flash messages | Done |
| RTL layout, Vazirmatn, CSS tokens, card/button/badge | Done |
| Preview pages (home, Jalali tool, mobile tool, `/_styleguide` local) | Done |
| Vercel container preview | Files ready, deployment blocked (see DECISIONS) |
| S1-T1: staff schema, permission config and Gates, panel middleware | Done (CI green) |
| S1-T2: staff login, rate limit, forced password change, seeded staff | Done (CI green) |
| S1-T3: audit logger, login/logout audit, admin audit viewer | Done (CI green) |
| S1-T4: clients and medical record, codes, consent, Jalali birth date, duplicate mobile, soft delete | Done (CI green) |
| S1-T5: alert badges, medical tab restricted to medical.view | Done (CI green) |
| S1-T6: catalog CRUD for services, devices and rooms, example seeder | Done (CI green) |
| S1-T7: staff accounts (create, deactivate, reset password) | Done (CI green) |
| S2-T1: schedules and holidays, admin CRUD, Saturday–Thursday template | Done (CI green) |
| S2-T2: availability rules (holiday, weekend, hours, overlap) with unit tests | Done (CI green) |
| S2-T3: booking service with locks, reschedule, cancel, status history | Done (CI green; race test on MySQL) |
| S2-T4: appointment form, resource pickers, free-slot endpoint | Done (CI green) |
| S2-T5: calendar day, week and month in Jalali, filters | Done (CI green) |
| S2-T6: transition actions (confirm, arrived, no-show, cancel with reason) | Done (CI green) |
| S3-T1: session packages and device sessions schema; package pages; remaining sessions | Done (CI green) |
| S3-T2: operator's list for today (device appointments, last 4 digits, alert flags) | Done (CI green) |
| S3-T3: session recording (contraindication refusal, numbering under lock, package link, finish) | Done (CI green) |
| S3-T4: measurement types and the operator's measurement form (range validation, Persian digits) | Done (CI green) |
| S3-T5: operator's own entries, last seven days (read-only) | Done (CI green) |
| S4-T1: report workflow (publish, return, correct; immutable published sets) | Done (CI green on `42a9ce6`) |
| S4-T2: specialist inbox and review page (compare, edit, publish, return) | Done (CI green on `42f9093`) |
| S4-T3: progress service (first and latest, deltas) and line chart component (Chart.js, published only) | Done (CI green on `cfc2035`) |
| S4-T4: specialist client file (notes, next follow-up, measurement history and chart) and follow-ups due page | Done (CI green on `fe1f03b`) |
| S4-T5: payments (record, list with Jalali date filter, client ledger, admin-only reversal; no edit or delete) | Done (CI green on `9a57227`) |
| S4-T6: admin dashboard cards and daily report (admin only) | Done (CI green on `c8c3807`) |
| S5-T1: SMS gateway (Kavenegar, log), dispatcher with retries, templates, sms_log with resend, appointment events, reminder command | Done (CI green on `f63751c`) |
| S5-T2: event wiring (report_ready, appointment events) and reminder scheduler | Done with S5-T1 (CI green on `f63751c`) |
| S5-T3: client OTP login (5-digit code, 3-minute expiry, rate limits, client guard) | Built, CI pending |
| S5-T4: client portal (five tiles, reports, appointments read-only, sessions, payments; `ClientOwnedScope`) | Built, CI pending |
| S5-T5 to S5-T7: hardening, performance, release checklist | Not yet |

Decisions and open questions: [docs/DECISIONS.md](docs/DECISIONS.md). Deferred work: [docs/BACKLOG.md](docs/BACKLOG.md). Sprint notes: [docs/sprints/](docs/sprints/).

## Requirements

- PHP 8.4 with `mbstring`, `intl`, `zip`, `pdo_sqlite` (or `pdo_mysql` for MySQL), `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`
- Composer 2
- MySQL 8 (utf8mb4) for the real application. The local default is SQLite.

## Local setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan serve
```

Open `http://127.0.0.1:8000`. In `local` environment the style guide is at `/_styleguide`.

## Checks

```bash
composer lint        # Pint (style)
composer stan        # PHPStan level 6 (Larastan)
composer test        # Pest
php scripts/verify-support.php   # dependency-free checks for app/Support (any PHP 8.3+)
```

CI (`.github/workflows/ci.yml`) runs Pint, PHPStan and Pest on every push.

## Environment variables

See `.env.example` for the full list with comments. The important ones:

| Variable | Purpose |
|---|---|
| `APP_KEY` | Encryption key. Required in production. Set it in the Vercel project, never in the repository. |
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | Environment. Production uses `APP_DEBUG=false`. |
| `APP_TIMEZONE` | `Asia/Tehran` |
| `APP_LOCALE` | `fa` |
| `DB_*` | Database. The Vercel preview runs without one. |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | The container preset sets `array`, `array` and `sync`. |
| `LOG_CHANNEL` | `stderr` in the container |
| `SMS_DRIVER` | `log` (local/testing only; OTP codes are redacted) or `kavenegar` |
| `KAVENEGAR_API_KEY`, `KAVENEGAR_SENDER` | SMS provider credentials. Never commit. |
| `CLINIC_NAME`, `CLINIC_REMINDER_HOURS_BEFORE` | Clinic settings (`config/clinic.php`) |
| `BACKUP_ENCRYPTION_KEY`, `BACKUP_DISK` | Backup encryption and off-server disk (S5) |

## Deployment

### Vercel preview (feedback only)

The preview runs as a container on Vercel (FrankenPHP, PHP 8.4), following Vercel's Laravel guide. Files: `Dockerfile`, `Caddyfile`, `vercel.json`, `.dockerignore`. It has no database, and sessions and cache are not kept between requests.

1. In the Vercel dashboard, import the GitHub repository `alipourmo/ZEEDIET` and the branch with these files. Vercel detects the root `Dockerfile` and `vercel.json`. (The Vercel token used by the sandbox cannot yet create projects. See DECISIONS.)
2. Set `APP_KEY` in the project environment variables. Generate it locally with `php artisan key:generate --show`.
3. Deploy. Check `/healthz` and `/`.

Do not put real client data into the preview.

### Production (planned, spec section 16)

Target: a Linux VPS in Iran with Nginx, PHP-FPM 8.4, MySQL 8, Supervisor (queue worker) and cron (`* * * * * php artisan schedule:run`). The hosting decision is still open. The release checklist (`docs/RELEASE.md`) is written in S5.

## Commands planned for later sprints

- `php artisan appointments:send-reminders` (every 15 minutes, via scheduler)
- `php artisan backup:run`, `php artisan backup:restore-test` (S5)
- `php artisan clients:purge {id}` (admin-confirmed hard delete after retention, S5)

## Security

- No secrets in the repository. `.env` is git-ignored.
- The preview is `noindex`. `robots.txt` disallows all.
- All user-facing text is Persian and lives in `lang/fa/`.
