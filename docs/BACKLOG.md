# Backlog

Ideas and deferred work. Items that belong to a later sprint are listed with their sprint. Anything not in the spec goes here, not into the code.

## S0 (remaining)

- Laravel 11 project skeleton: `artisan`, `bootstrap/app.php`, `public/index.php`, `config/*` (app, database, session, queue, logging), `routes/web.php`. Needs Composer (see DECISIONS, 2026-11-17).
- `pint.json`, `phpstan.neon` (level 6), `phpunit.xml` for Pest.
- GitHub Actions workflow: pint, phpstan, pest on every push (S0-T1).
- `preventLazyLoading()` in `AppServiceProvider` for non-production.
- Base layout, Vazirmatn woff2 (self-hosted), CSS tokens, Blade component skeleton, flash messages, `/_styleguide` (local only) (S0-T2).
- Blade directives `@jdate`, `@jdatetime`, `@money`, `@fa`, input-normalising middleware (S0-T3).
- Chart.js self-hosted in `public/vendor` (needed in S3/S4).

## Deployment

- Hosting decision: the spec targets a VPS in Iran (section 16). Vercel was requested by the user but does not fit the spec's stack (see DECISIONS, 2026-11-17). Waiting for the user's decision.
- Supabase connector is available, but the spec requires MySQL 8, so it is not used for the app database.

## Later (not in MVP)

- Use `morilog/jalali` behind `App\Support\Jalali` if the team prefers the library (see DECISIONS).
- Laravel version review: spec says 11; upstream is on 13.x (see DECISIONS).
- Client portal "request change/cancel" (V1.0, S8).
- Online booking `/book` (V1.0, S8).
- Diet plans and PDF generation (V1.0, S6).
- Invoices, wallet, CRM, Excel import (V1.5).
- Body-analysis device file import (V2.0).
