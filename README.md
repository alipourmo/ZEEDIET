# ZeeDiet Clinic Management System

A Persian (RTL) web application for a nutrition and weight-loss clinic in Rafsanjan, Iran. Four role-based areas on one database: admin and secretary (`/admin`), device operator (`/operator`), nutrition specialist (`/specialist`), and client portal (`/my-account`).

The full build specification is in [docs/SPEC.md](docs/SPEC.md). Read it before changing code.

## Status

**Sprint S0 is partly done. The application cannot be run yet.**

| Area | State |
|---|---|
| Specification | Done (`docs/SPEC.md`) |
| Date, digit and mobile helpers (`app/Support`) | Done and verified |
| Clinic config and env example | Done |
| Laravel project skeleton, Pint, PHPStan, CI | Not yet |
| UI layout, styleguide | Not yet |
| Clients, scheduling, operator, specialist, portal, SMS | Not yet (S1–S5) |

Decisions and open questions: [docs/DECISIONS.md](docs/DECISIONS.md). Deferred work: [docs/BACKLOG.md](docs/BACKLOG.md). Sprint notes: [docs/sprints/](docs/sprints/).

## Requirements (target)

- PHP 8.3 with the `pdo_mysql`, `mbstring`, `intl`, `curl`, `openssl` and `json` extensions
- Composer 2
- MySQL 8 (utf8mb4)
- Node.js 20+ (only if front-end assets are built later; Chart.js and Vazirmatn are self-hosted)

## Setup (after the Laravel skeleton lands in S0)

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed        # idempotent; prints one random password per seeded staff user once
php artisan serve
```

Then open `/login`.

## Environment variables

See `.env.example` for the full list with comments. The important ones:

| Variable | Purpose |
|---|---|
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | Environment. Production forces secure cookies and HSTS (spec section 10). |
| `APP_TIMEZONE` | `Asia/Tehran` |
| `APP_LOCALE` | `fa` |
| `DB_*` | MySQL 8 connection. Use a least-privilege user in production. |
| `SESSION_DRIVER`, `QUEUE_CONNECTION` | `database` |
| `SMS_DRIVER` | `log` (local/testing only; writes to `storage/logs/sms.log` with OTP codes redacted) or `kavenegar` |
| `KAVENEGAR_API_KEY`, `KAVENEGAR_SENDER` | SMS provider credentials. Never commit. |
| `CLINIC_NAME`, `CLINIC_REMINDER_HOURS_BEFORE` | Clinic settings (`config/clinic.php`) |
| `BACKUP_ENCRYPTION_KEY`, `BACKUP_DISK` | Backup encryption and off-server disk |

## Verifying the helpers without Composer

The date, digit and mobile helpers in `app/Support` have no dependencies. They can be checked with any PHP 8.3+ CLI:

```bash
php scripts/verify-support.php
```

## Tests (target, from S0 onward)

```bash
composer install
composer lint        # pint --test
composer stan        # phpstan level 6
composer test        # pest
```

Pest tests for the helpers are in `tests/Unit/Support/`.

## Commands planned for later sprints

- `php artisan appointments:send-reminders` (every 15 minutes, via scheduler)
- `php artisan backup:run`, `php artisan backup:restore-test` (S5)
- `php artisan clients:purge {id}` (admin-confirmed hard delete after retention, S5)

## Deployment

Target: a Linux VPS in Iran with Nginx, PHP-FPM 8.3, MySQL 8, Supervisor (queue worker) and cron (`* * * * * php artisan schedule:run`). Steps are in spec section 16. The release checklist (`docs/RELEASE.md`) is written in S5. No hosting provider is chosen yet (see DECISIONS).

## Security

- No secrets in the repository. `.env` is git-ignored.
- All user-facing text is Persian and lives in `lang/fa/` (once created).
- Robots: the whole app is `noindex`, `robots.txt` disallows all (S5).
