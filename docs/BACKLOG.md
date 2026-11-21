# Backlog

Ideas and deferred work. Items that belong to a later sprint are listed with their sprint. Anything not in the spec goes here, not into the code.

## S0 (remaining)

- Commit `composer.lock` after the first `composer install` (see DECISIONS, 2026-11-17).
- Vercel: grant the token project permissions, or import the repo in the Vercel dashboard (see DECISIONS).
- Verify the first CI run and the first Vercel build; fix what they report.
- Components from spec section 11 still to write: table, modal, pagination, jalali-date-input, time-select, empty-state, stat-tile, chart-line. Card, button, badge and flash exist.
- Chart.js self-hosted in `public/vendor` (needed in S3/S4).

## S1 (next)

- Replace the skeleton `users` table and `User` model with the staff schema (mobile, role, is_active, last_login_at) (S1-T1).
- `/healthz` should check the DB and queue lag (spec 16). The preview endpoint returns `{"status":"ok"}` only.

## Deployment

- Hosting: the spec targets a VPS in Iran (section 16). The Vercel preview uses the container runtime for testing only. Production hosting is still open (spec section 17, question 10).
- Supabase is connected, but the spec requires MySQL 8, so it is not used for the app database.
- Vercel preview needs a stable `APP_KEY` and, later, a database. Both are open.

## Later (not in MVP)

- Use `morilog/jalali` behind `App\Support\Jalali` if the team prefers the library (see DECISIONS).
- Client portal "request change/cancel" (V1.0, S8).
- Online booking `/book` (V1.0, S8).
- Diet plans and PDF generation (V1.0, S6).
- Invoices, wallet, CRM, Excel import (V1.5).
- Body-analysis device file import (V2.0).
