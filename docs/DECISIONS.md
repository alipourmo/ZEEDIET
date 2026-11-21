# Decisions

Format: date, question, choice, reason. Newest entries at the bottom.

---

## 2026-11-17 — Build environment cannot install Laravel

- **Question:** The spec requires a Laravel 11 project with Pest, Pint and PHPStan (S0-T1). The build sandbox has no PHP and no Composer, and `repo.packagist.org` is unreachable from it (only github.com, codeload.github.com, api.github.com, registry.npmjs.org, pypi.org and files.pythonhosted.org are allowed).
- **Choice:** Build the framework-agnostic parts of S0 now (`app/Support/*`, `config/clinic.php`, `.env.example`, `composer.json`). Verify them with a dependency-free script (`scripts/verify-support.php`) executed on PHP 8.3 (run through a WebAssembly PHP build in the sandbox). Write the Pest tests in `tests/Unit/Support/` now, to be run in CI or locally once Composer is available. Do not claim Pest/Pint/PHPStan results until they have actually run.
- **Reason:** It is the only way to produce verified, working code in this sandbox without guessing the framework behaviour.

## 2026-11-17 — Laravel version

- **Question:** The spec says Laravel 11. On GitHub, `laravel/framework` tags currently go up to v13.x.
- **Choice:** Keep Laravel 11 (`"laravel/framework": "^11.0"`) as the spec states, with Pest 3 and pest-plugin-laravel ^3 (the Laravel 11-compatible line).
- **Reason:** Section 3 of the spec is marked non-negotiable. Laravel 11 may be out of security support by now; **this needs a decision from the clinic/maintainer before S1** (stay on 11 or move to a supported version and update section 3).
- **Status:** Superseded on 2026-11-17 by the owner's approval of Laravel 13 (see the Laravel 13 entry below).


## 2026-11-17 — Deployment target: Vercel not used

- **Question:** The user asked for deployment on Vercel. The spec (section 16) targets a Linux VPS in Iran with Nginx, PHP-FPM, MySQL 8, Supervisor and cron.
- **Choice:** No Vercel project is created yet and no deployment is made. The repository contains no provider-specific code (spec section 17, question 10 default).
- **Reason:** Vercel runs serverless functions. It does not give this app a persistent MySQL 8 database, a database queue worker, a scheduler, or a private disk for backups, and it is not in the spec's hosting plan. The Supabase project in the connected apps uses PostgreSQL, but the spec requires MySQL 8. Creating either would contradict the spec. **Open question for the user:** confirm the VPS plan, or explicitly approve a different host.
- **Status:** Partly superseded. The owner asked to deploy on Vercel for testing. Production hosting is still open (see the Vercel entries below).


## 2026-11-17 — Jalali conversion implemented in-house (for now)

- **Question:** Spec section 3 names `morilog/jalali` for Persian dates. It cannot be installed in this sandbox.
- **Choice:** `App\Support\Jalali` is a pure-PHP arithmetic converter with no dependencies. It is verified with Nowruz anchors (1399/12/30, 1403/12/30, 1404/01/01 and others), a round trip over every day from 1900 to 2100, and leap-year checks (1399 and 1403 are leap; 1404 is not). `composer.json` does not list `morilog/jalali` yet.
- **Reason:** The contract (`fromGregorian`, `toGregorian`, `parseToGregorian`, `formatGregorianAsJalali`) is small and tested. The converter can be swapped for morilog behind the same API once Composer works, if the team wants the library. Tests guard the behaviour either way. Record the final choice in S1.

## 2026-11-17 — Mobile normalizer accepts bare 9xxxxxxxxx and strips hyphens

- **Question:** Section 7.9 says to strip spaces and +98/0098 prefixes. It does not say how to treat hyphens, or a bare 10-digit number that starts with 9.
- **Choice:** Hyphens are stripped as well as spaces. A bare `9xxxxxxxxx` (10 digits) is accepted and gets a leading 0. Input is returned as `09xxxxxxxxx` or `null`.
- **Reason:** Staff and clients often type numbers this way. Accepting them has no security cost, and the output format is fixed.

## 2026-11-17 — Working-hours placeholder

- **Question:** The spec does not give clinic working hours. Section 6.2 and S2-T1 need a template.
- **Choice:** `config/clinic.php` has a placeholder of 09:00–20:00 for the seeded schedule template, marked "confirm with the clinic".
- **Reason:** The placeholder lets scheduling work. Holidays are an empty stub, as the spec allows.

## 2026-11-17 — Support namespace and layout

- **Question:** Where do the helpers live?
- **Choice:** `app/Support/{Digits,Jalali,Mobile}.php` in namespace `App\Support`, as section 4 specifies. Mobile normalisation is a separate class from `Digits` (section 4 does not list it, but section 7.9 describes it as a helper).
- **Reason:** Matches the layout in section 4, with one extra file that keeps the mobile rule testable on its own.

## 2026-11-17 — LogGateway redacts OTP codes

- **Question:** Section 7.5 says "Codes are never logged", but also says that in local and testing, LogGateway writes messages to `storage/logs/sms.log`. An OTP SMS contains the code.
- **Choice:** LogGateway replaces the `{code}` value with `[redacted]` before writing. The file is written only when `SMS_DRIVER=log` and `APP_ENV` is local or testing. Production config must not allow it. This will be implemented and tested in S5-T1.
- **Reason:** The two rules conflict only for OTP messages. Redaction keeps developer visibility for other messages and keeps the "never logged" rule for codes.

## 2026-11-17 — Laravel skeleton, CI and styleguide deferred

- **Question:** Which S0 tasks are done in this turn?
- **Choice:** S0-T3 (helpers, partly: no Blade directives or middleware yet), S0-T1 partly (config, env example, composer.json), and S0-T4 are done. The Laravel project skeleton (artisan, bootstrap, public/index.php, Pint/PHPStan configs), the GitHub Actions workflow, and S0-T2 (layout, CSS, style guide) are not. The CI workflow is deferred until there is an app to test. See `docs/sprints/S0.md`.
- **Reason:** These cannot be verified in this sandbox. Adding unverified scaffolding would make the repository look runnable when it is not.
- **Status:** Superseded. The Laravel skeleton, CI workflow and layout are now in place (see the S0 entries below).

## 2026-11-17 — Laravel 13 and PHP 8.4 (owner approved)

- **Question:** Which Laravel version should the project use now that Laravel 11 is outdated?
- **Choice:** Laravel 13 (`laravel/framework ^13.0`), taken from the official `laravel/laravel` skeleton. The owner approved Laravel 13.
- **PHP version (owner decision: 8.4):** `pest-plugin-laravel` 5.x is the only plugin line that supports Laravel 13. It needs Pest 5, which needs PHP 8.4 (`"php": "^8.4"`). The spec (v1.0) said PHP 8.3. This is a deliberate deviation from the spec, approved by the owner. The alternative was PHP 8.3 with Laravel 12 and Pest 4, which would have reversed the Laravel 13 approval.
- **Other tools:** Larastan ^3.15 for PHPStan level 6, and Pint ^1.27.

## 2026-11-17 — Vercel deployment uses the Container runtime, not vercel-php (proposed, pending owner approval)

- **Question:** How to host the Laravel app on Vercel?
- **Proposed choice:** Vercel's official Laravel guide (FrankenPHP in a container): root `Dockerfile`, `Caddyfile`, and `vercel.json` with a `container` service and a catch-all rewrite. The community `vercel-php` runtime is not used.
- **Reason:** The `vercel-php` README says it is community-maintained and recommends a container runtime for new projects. Vercel's own guide uses the container approach.

## 2026-11-17 — Vercel access blocker (no project created yet)

- **Finding:** The connected Vercel token can read the user (`get_authenticated_user`) and sees one team, but `list_projects` and `get_team` return "Access denied" (`limited: true` on the account). `create_deployment` needs an existing project ID. No project was created and no deployment was made.
- **Next step for the owner:** Grant the token project and deployment permissions, or import the GitHub repository in the Vercel dashboard. Vercel will detect the root `Dockerfile` and `vercel.json`. Then add `APP_KEY` as an environment variable.

## 2026-11-17 — GitHub push blocked on the earlier branch

- **Finding:** The earlier working branch `arena/993ed89b-zeediet` was reported as "merged or closed" by the GitHub connection, and pushes to it were refused. Pull request #1 is merged into `main`. The earlier local commits (Laravel 13 skeleton and preview pages) were lost when the workspace was re-created from GitHub. They were rebuilt on `arena/f5a2b741-zeediet`.

## 2026-11-17 — Preview runs without a database, sessions or cache

- **Choice:** `CACHE_STORE=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `LOG_CHANNEL=stderr`, `APP_ENV=production`, `APP_DEBUG=false` (set in the Dockerfile). The preview has no database. Only stateless pages are included (home, Jalali and mobile tools, `/healthz`).
- **Reason:** The spec requires MySQL 8, which Vercel does not provide. Adding a database for a feedback preview would mean choosing an external provider without approval. The preview is ephemeral and shows no client data.

## 2026-11-17 — APP_KEY fallback in the container

- **Choice:** The container start command uses `APP_KEY` from the environment. If it is missing, it generates a random key, so the preview still starts. Sessions and encrypted cookies reset on restart. Set a stable `APP_KEY` in the Vercel project for any shared testing.
- **Reason:** A missing key would otherwise make every request fail. A random key is not a secret and is never committed.

## 2026-11-17 — Preview is kept out of search engines

- **Choice:** `public/robots.txt` disallows all, and Caddy sends `X-Robots-Tag: noindex, nofollow` on every response. The layout also has `<meta name="robots" content="noindex, nofollow">`.
- **Reason:** Spec section 10.12 requires noindex on every route. It is applied now because the preview is public.

## 2026-11-17 — /_styleguide is local only

- **Choice:** `/_styleguide` returns 404 unless `APP_ENV=local` (spec section 8, S0-T2). It is not shown on the Vercel preview.

## 2026-11-17 — Timezone and locale defaults

- **Choice:** `config/app.php` uses `APP_TIMEZONE` (default `Asia/Tehran`), `APP_LOCALE=fa`, and `APP_FAKER_LOCALE=fa_IR`. The skeleton had UTC and `en`.
- **Reason:** Spec section 3 requires Asia/Tehran and Persian UI.

## 2026-11-17 — Frontend build tooling removed

- **Choice:** The Laravel skeleton's Vite, npm, `package.json`, `vite.config.js` and `resources/js` are not copied. CSS is hand-written in `public/css/app.css`.
- **Reason:** Spec section 3 requires hand-written CSS and no framework. There is no JavaScript build step.

## 2026-11-17 — composer.lock is not committed yet

- **Problem:** The sandbox cannot run Composer (Packagist is blocked), so no `composer.lock` exists. The Dockerfile copies `composer.lock*`, so the build still works.
- **Action:** Generate and commit `composer.lock` once Composer is available (locally, or from a CI job that commits it). Spec section 16 requires pinned installs.

## 2026-11-17 — Skeleton users table kept for now

- **Choice:** The Laravel skeleton's `users` migration, `User` model, factory and seeder stay unchanged. Spec S1-T1 replaces them with the staff `users` table (mobile, role, is_active).

## 2026-11-17 — Verification status of this build

- Verified in this sandbox: all support helpers (`scripts/verify-support.php`, 38 checks on PHP 8.3, including the new `Display` checks). All 38 PHP files parse (`TOKEN_PARSE`).
- Not verified here: Blade rendering, Pest, Pint, PHPStan, the Docker image and the Vercel build. The GitHub Actions CI and the Vercel build are the first real checks.

## 2026-11-17 — PHP 8.4 confirmed by the owner

- **Question:** Keep PHP 8.4 (Pest 5, Laravel 13) or switch to PHP 8.3 (Laravel 12, Pest 4)?
- **Choice:** PHP 8.4, as chosen by the owner. The Dockerfile, CI workflow and `composer.json` already target 8.4.
- **Reason:** Owner choice. Keeps Laravel 13, the approved version.

## 2026-11-17 — Input normalisation skips password fields

- **Question:** Spec section 7.9 says to normalise Persian and Arabic digits in all POST/PUT/PATCH input. Normalising a password would change it, so a user who types Persian digits would get a different password from the one they set.
- **Choice:** `NormalizeInput` skips any field whose name contains `password`. Other text fields, query strings and JSON bodies are normalised.
- **Reason:** The password the user types is the one that is checked. Digit conversion only matters for search and unique fields.

## 2026-11-17 — Date-time display: strings are clinic local time

- **Question:** `@jdatetime` may receive a DateTime (with a time zone) or a plain string. Which time zone applies to a string?
- **Choice:** A DateTime is converted to Asia/Tehran. A string is read as clinic local time and is not converted.
- **Reason:** The clinic enters and reads times in Tehran time. Converting plain strings would shift times. Callers that hold UTC values must pass a DateTime.

## 2026-11-17 — S0 tasks completed

- Added the missing S0 items: `@jdatetime` directive, input-normalising middleware, flash messages in the layout, and `Model::preventLazyLoading()` outside production.
- S0 is complete when the CI run on this branch passes (spec S0 acceptance: CI green on a fresh clone). The four acceptance checks themselves were already covered by the support tests and `scripts/verify-support.php`.

## 2026-11-17 — S1-T1: users table rewritten in place

- **Question:** The S0 skeleton created `users` with `email`, `email_verified_at` and a `password_reset_tokens` table. Spec section 6.1 needs mobile login and a role column.
- **Choice:** The `users` migration is rewritten for the staff schema (name, mobile unique, password, role, is_active, last_login_at). `password_reset_tokens` is dropped. The `sessions` table stays, because the database session driver is needed to cut sessions on deactivation (spec section 7.6).
- **Reason:** No database has ever been migrated outside the test suite, so no deployed schema is changed. Staff passwords are reset by an admin (section 7.6), not by email.

## 2026-11-17 — S1-T1: panel access uses area Gates and an area: middleware alias

- **Question:** Spec section 10 asks for "middleware group per area". Spec section 5 says every check goes through the Gates in config/permissions.php.
- **Choice:** Each panel has an `area.*` Gate (`area.admin`, `area.operator`, `area.specialist`), listed in config/permissions.php. The route middleware `area:admin` (and `area:operator`, `area:specialist`) checks the matching Gate. Admin and secretary share the admin area. Admin is not granted the operator or specialist area.
- **Reason:** One mechanism (Gates) for both panel access and feature access. Route groups alone would not express per-role rules.
- **Open:** Guests get 403 for now. S1-T2 adds the login route and redirects guests to it.

## 2026-11-17 — S1-T1: sms.templates.manage is admin-only

- **Question:** Spec section 5 lists `sms.templates.manage` in the permission keys, but not in the matrix. Section 8.2 puts SMS templates in the admin area.
- **Choice:** Admin only.
- **Reason:** Conservative default. The clinic can widen it later in config/permissions.php.

## 2026-11-17 — S1-T1: audit log immutability is enforced in the model

- **Question:** Spec section 6.5 says audit_logs is append-only, with no update or delete path. The database could enforce this with triggers, but triggers differ between SQLite (tests) and MySQL (production).
- **Choice:** `AuditLog` throws on `update()` and `delete()`. There is no `updated_at` column. Code must not use raw query-builder updates or deletes on audit_logs. Code review checks this, and a later task can add a test that scans for them.
- **Reason:** Covers the normal code paths now. Full database enforcement can be added with MySQL-only migrations later, recorded here as an open item.

## 2026-11-17 — S1-T1: Feature tests migrate a fresh database

- **Choice:** `tests/Pest.php` applies `RefreshDatabase` to every test in `tests/Feature`. Every Feature test therefore runs all migrations on an in-memory SQLite database.
- **Reason:** A broken migration fails the suite. This is the check on "migrations are reversible on a fresh database" in spec section 19, for the up direction. Reversibility (down) is checked by hand until a rollback test is added.

## 2026-11-17 — S1-T1: mockery/mockery added as a dev dependency

- **Reason:** With the first Feature tests that boot the application through Laravel's test case, the Pest run failed on `Class "Mockery" not found` (28 Feature tests). The Laravel skeleton ships `mockery/mockery` in `require-dev`, and our `composer.json` had dropped it.
- **Scope:** dev dependency only (`require-dev`). Not used by app code. The runtime is unchanged.
- **Alternative rejected:** Removing the Mockery call would mean patching the framework or a Pest hook; the skeleton default is the least risky route.

## 2026-11-17 — S1-T1: CI debugging notes

- A Pest step failure gave no usable log via `gh` in this sandbox. Diagnosis used check-run annotations: the 3 KB limit means long output needs base64 chunks, one per notice. The temporary diagnostic steps were removed; `ci.yml` is back to the S0 version.

## 2026-11-17 — S1-T2: staff login uses Auth::attempt with is_active in the query

- **Choice:** `LoginController::store` calls `Auth::attempt(['mobile', 'password', 'is_active' => true])`. A deactivated account and a wrong password produce the same message (`auth.failed`), so the login form does not reveal which mobiles exist.
- **Throttle:** `RateLimiter` in the controller, not the `throttle` middleware, because the key needs the normalised mobile. Key: mobile + IP. 5 failed attempts, decay 60 seconds (spec section 7.6). A successful login clears the key.

## 2026-11-17 — S1-T2: deactivated sessions end on the next request

- **Choice:** `EnsureStaffIsActive` runs in the `web` group. If the signed-in user has `is_active = false`, it logs them out, invalidates the session and redirects to login with a message.
- **Reason:** With the database session driver, deleting another user's session rows needs their session ids. Checking on each request covers the rule in spec section 7.6 ("logged out on next request") and is the same mechanism S1-T7 relies on when an admin deactivates a user.

## 2026-11-17 — S1-T2: /password/change route added

- **Reason:** Spec section 7.6 requires a forced password change, but section 8.1 lists only the login and logout routes. `EnsurePasswordIsChanged` (in the `web` group) sends a user with `must_change_password` to `/password/change` before any other page. That route, and logout, are the only pages allowed meanwhile.

## 2026-11-17 — S1-T2: seeded staff accounts

- **Choice:** `StaffSeeder` creates one account per role. Mobiles are placeholders `09000000001`–`09000000004`, to be replaced by the clinic before go-live. Each password is `Str::random(16)`, printed once with `$this->command->warn` and not stored in plain text. `must_change_password` is true. An existing mobile is skipped, so re-running the seeder does not reset passwords.
- **Names:** from `lang/fa/seeders.php`, not hard-coded.

## 2026-11-17 — S1-T2: lang/fa/validation.php added (partial)

- The S0 notes listed this file as missing. It now holds the messages for the rules used so far (required, string, max, min, confirmed, different, current_password). Add a line when a new rule is used. Laravel's own English messages are not loaded, because `APP_FALLBACK_LOCALE=fa`.

## 2026-11-17 — S1-T2: login and logout audit entries are deferred to S1-T3

- Spec section 7.8 asks for audit rows on sensitive actions. `AuditLogger` is built in S1-T3, so login, logout and password-change events are not yet audited. This is an open item in `docs/sprints/S1.md`.

## 2026-11-17 — S1-T2: route protection test added early

- `tests/Feature/RouteProtectionTest.php` (spec section 10.5) walks all routes. Every route must be on the public allowlist or carry `auth` or an `area:` middleware. The allowlist is in the test, so adding a public route is an explicit change.

## 2026-11-17 — S1-T2: the private disk does not serve files

- **Choice:** `config/filesystems.php` sets `'serve' => false` on the `local` disk (storage/app/private). The framework then does not register the `storage/{path}` GET and PUT routes.
- **Reason:** Those routes would serve private files with only a signed-URL check. Spec section 10.6 wants uploads served by an authorized controller with short-lived signed URLs. That controller is built with uploads in V1.0. Until then, no route should expose the disk.
- **Found by:** `RouteProtectionTest`, which failed on the two framework routes.

## 2026-11-17 — S1-T3: AuditLogger signature and actor rules

- **Choice:** `AuditLogger::log(AuditAction $action, Model $subject, ?int $clientId, ?array $changes, ?User $actor)`. The actor defaults to the signed-in staff member, or to `system` when nobody is signed in. Client actors (the portal, S5) will need a parameter added then.
- **Changes:** Stored as given, with any key containing `password`, `token` or `secret` removed at every depth (spec section 6.5). Convention: `['old' => [...], 'new' => [...]]`.
- **Spec 7.8 deviation:** Auth events (login, logout, password change) are logged by their controllers, because the auth flow has no service of its own. Business services (clients, sessions, payments and so on) call the logger from the service.
- **Not logged:** Failed login attempts. The subject is unknown and no account is changed. Logging them would help investigate guessing, so this is an open question for the clinic.

## 2026-11-17 — S1-T3: action list extended

- The spec's action list (login, logout, view, create, update, delete, publish, export) is the enum `App\Enums\AuditAction`. `password_change` and `password_reset` are added, because spec section 7.8 asks for password resets to be logged.

## 2026-11-17 — S1-T3: append-only is enforced in three places

- The `AuditLog` model refuses `update()`, `delete()` and `forceDelete()` (model events).
- `AuditLogBuilder` (used by `AuditLog::query()`) refuses bulk `update()` and `delete()`.
- Open item: raw `DB::table('audit_logs')` queries are not blocked. A database-level trigger on MySQL (production only) would close that. Add it to the S5 hardening task.

## 2026-11-17 — S1-T3: audit viewer

- Route `GET /admin/audit` (`admin.audit.index`) is inside the admin panel group and also has `can:audit.view`. The Gate allows the admin role only (spec section 5).
- Filters: actor, client ID, action, and a Jalali date range (`YYYY/MM/DD`, both ends inclusive, Tehran time). Persian digits are accepted (NormalizeInput).
- 50 entries per page. Actor names come from one extra query per page, not one per row.

## 2026-11-17 — S1-T4: where client screens live

- **Admin and secretary** use `/admin/clients` (list, create, show, edit, update, delete). Route middleware is `area:admin`, then `can:` for each action (`client.view`, `client.create`, `client.update`, `client.delete`). The secretary has no `client.delete`, so delete returns 403 for them.
- **Medical record, admin:** `/admin/clients/{client}/medical`, behind `can:medical.view` and `can:medical.update`. Only the admin has these in the panel, because the secretary has no medical permissions (spec section 5).
- **Specialist:** `/specialist/clients/{client}` (file) and `/specialist/clients/{client}/medical` (edit), behind `area:specialist` and the same `can:` checks. Spec section 8.4 names this as `specialist.clients.show`.
- **Why a separate group:** `area:admin` on `/admin/clients` would block a specialist, who has `client.view` and `medical.view`. The specialist routes therefore sit in the specialist panel.
- **Gaps:** The matrix grants the specialist `client.create` and `client.update`, but no route in S1 uses them, so the specialist cannot edit core client fields. The operator has `client.view`, but no client route in S1 is reachable by the operator. The operator's today-only scope is enforced in S2.
- The views in `resources/views/clients/` receive their URLs and flags (`$editUrl`, `$medicalUrl`, `$deleteUrl`, `$formAction`, `$backUrl`) from the controllers. Views never check the role.

## 2026-11-17 — S1-T4: medical access and audit detail

- **Every read is audited.** Opening the medical page writes a `view` row on `ClientMedical` with the client ID. A refused read (403) writes nothing.
- **Medical updates are audited by field name.** The row stores `changed_fields` (e.g. `["food_allergies"]`) and never the values. The text is never copied into `audit_logs`. A save with no change writes no row.
- **Core client edits** store `old` and `new` values for the changed fields only. These fields hold identity and contact data, not medical text, so the secretary can see them in the audit viewer.
- **Consent:** Data consent is required on create (`consent_data_at`). It is shown on the file and not editable afterwards. Photo consent: ticking the box sets `consent_photos_at` once, and a later save keeps the original time. Unticking clears it. The edit form pre-fills the box, so a plain save does not revoke consent.
- **Mobile:** normalised (Persian digits, `+98`, `0098`, dashes, bare 10-digit) before validation, using `App\Support\Mobile`. Format must be `09` followed by nine digits. Uniqueness covers archived clients too.
- **Duplicates:** An active duplicate returns an error with a link to the existing file (session flash `duplicate_client_id`, rendered in the form). An archived duplicate returns an error with no link.
- **Birth date** is typed in Jalali (`YYYY/MM/DD`), validated, and stored as a Gregorian `date`.
- **Codes:** `C-000001` format from the `sequences` row `client_code`, locked with `lockForUpdate` during the increment. Codes are never reused, even after a soft delete.
- **Soft delete only.** `Client::forceDelete()` throws. `ClientMedical` refuses `delete()` and its foreign key is `restrictOnDelete`. Clients have exactly one medical row, created in the same transaction.
- **Search** (`q`) matches first name, last name, code and mobile with `LIKE`. `%`, `_` and `\` are escaped so a search cannot act as a wildcard. Persian and ASCII digits are the same after `NormalizeInput`.
- **Policies:** none. Authorisation uses the Gates from `config/permissions.php` (`can:` middleware and `FormRequest::authorize()`), as decided in S1-T1.
- **Contraindications** are stored as a JSON list of keys from `config/clinic.php`, and labels come from `lang/fa/clients.php`.

## 2026-11-17 — S1-T5: alert badges and the medical tab

- **Badges are flags only.** `App\Support\ClientAlertBadges` gives allergy yes/no, the contraindication keys, and the pregnancy status (spec section 7.7). The allergy text is reduced to a boolean in SQL (`medicalSelect()`), so the free text never reaches PHP for badge rendering. A test checks this.
- **Visibility:** the badges need `medical.view_alerts`. All four roles have it, so the secretary and the specialist see them, and the operator will see them on its screens in S2. The free text needs `medical.view`.
- **Medical tab:** the file page has a tab bar (Summary and Medical). The Medical tab appears only when the viewer holds `medical.view`. The SPEC §8.2 wording says "admin only". The permission matrix (§5) also grants the specialist `medical.view`, so the specialist sees the tab on their own file at `/specialist`. The Gate decides, not the role name.
- **No audit row for badges.** Badges show no medical text, and a list page would write one row per client on every load. Opening the medical record is still audited (S1-T4). If the clinic wants list views logged, that is a change for S5.
- **List:** the client list loads the medical flags in a single query, not one per row (tested).

## 2026-11-17 — S1-T6: catalog

- **Scope:** services, devices and rooms (spec section 6.2), admin only (`catalog.manage`, routes under `/admin/services`, `/admin/devices`, `/admin/rooms`). Schedules and holidays are also under `catalog.manage` in §6.2, but they belong to the availability work in S2, so they are not built here.
- **No delete.** Catalog items are deactivated (`is_active`), never deleted. The routes have no `destroy`, and a DELETE request returns 405 (tested). Appointments (S2) will refer to services, devices and rooms, so deleting one would break history. Inactive items are not offered for new bookings (S2).
- **Catalog changes are not audited.** Spec section 7.8 lists the events to audit, and the catalog is not among them. Adding it is a small change if the clinic wants it.
- **Prices** are whole Toman in `default_price`. Separators (`150,000`, `150٬000`) are removed before validation. An empty price means "not set yet".
- **Example seed data** (`CatalogSeeder`, idempotent, found by name): three services from spec section 15 with durations 30, 20 and 45 minutes, two example devices with contraindications, two rooms. Names, durations, device contraindications and room names are examples for the clinic to confirm. Prices are left empty.
- **Spec references:** §12 says the seeders are in "section 14". The seed list is in §15.
- **Checkboxes after a failed save.** `old()` falls back to the saved value when a checkbox is absent from the submitted form. An unticked box therefore came back ticked after a validation error elsewhere on the form. Convention now used in every form with checkboxes: `$errors->any() ? (bool) old('x') : <saved value>`. Fixed on the photo consent and contraindication boxes (S1-T4) and on the catalog forms (tested).

## 2026-11-17 — S1-T7: users (staff accounts)

- **Admin only**, via `user.manage` (spec §5 and §8.2). Routes `/admin/users` (list, create, edit, update) and `POST /admin/users/{user}/reset-password`.
- **No delete.** Accounts are deactivated (`is_active`). The audit history keeps pointing at real users.
- **Passwords are generated, not typed.** A 16-character random password is created when an account is made or reset. It is shown once on the response page and is not flashed to the session or written to the log. Only its hash is stored. Closing that page loses it, so the admin must reset the password again. Passwords are never written to the audit trail.
- **Forced change.** A new or reset account has `must_change_password = true`. The existing `EnsurePasswordIsChanged` middleware sends the user to the password change page at their next sign-in.
- **Reset ends sessions.** Resetting a password deletes that user's `sessions` rows at once. Deactivating does the same, so the user is signed out now, not on their next request. `EnsureStaffIsActive` still covers any row not removed.
- **Self-protection.** An admin cannot deactivate their own account or change their own role. An admin resets their own password through the password change page, not the user screen. This stops the last working admin from being locked out.
- **Audit.** Create, update (old and new values, minus password) and `password_reset` each write one row (spec §7.8).
- **Mobile** is normalised (Persian digits and `+98` accepted) and must be unique and match `09xxxxxxxxx`.
- **Validation messages.** `lang/fa/validation.php` now covers the rules and attribute names the new forms use. Before this, unlisted rules would show raw English keys.
- **Service prices and durations** now convert Persian digits with `Digits::toAscii`. This closes the S1-T6 gap.

## 2026-11-17 — S2-T1: schedules and holidays

- **Weekday numbering** follows spec 6.2: 0 = Saturday … 6 = Friday. `App\Support\Weekday::fromDate()` shifts Carbon's Sunday-first numbering.
- **Schedule rows are replaced as a set** in one transaction (`ScheduleService::replace`), so a saved week never mixes old and new hours. A resource with no rows is unavailable.
- **Times** are stored as `HH:MM:SS` and compared as minutes (`SlotMath`), so string ordering of times is never relied on.
- **Who has hours:** specialists (role `specialist`), devices and rooms. Admin and secretary accounts cannot be given hours.
- **Default hours** (`config/clinic.php` `working_hours`, 09:00–20:00) are seeded Saturday to Thursday for every active specialist, device and room. Friday is the weekend by config, so the seeder does not add it. A resource that already has hours is left alone.
- **Holidays:** config `clinic.holidays` is **empty**. I did not guess official holiday dates. The clinic enters them in `/admin/holidays`. Spec 17 allows a placeholder list; an empty one is safer than wrong dates.
- **Holiday dates** are typed in Jalali (`۱۴۰۵/۰۱/۰۱`) and stored as Gregorian. Each date is unique. Editing keeps the record's own date.
- **Hard delete is allowed** for schedule rows and holidays. Neither is a medical or financial record (spec 10).
- **Not audited yet.** Spec 7.8 does not list schedules or holidays. Catalog edits are not audited either (S1-T6). Adding them is a small change if the clinic wants it.
- **Access:** `catalog.manage` (admin only), as spec 8.2 lists schedules and holidays under the catalog.

## 2026-11-17 — S2-T3: booking service, status history, concurrency

- **Locks come first, then reads.** Under MySQL REPEATABLE READ the snapshot is taken at the first ordinary read. A read before the lock could miss a booking that committed a moment earlier. Every write transaction therefore locks, then reads.
- **Lock order is fixed:** client, device, room, specialist. A reschedule locks the appointment first, then the same rows in the same order. A status change locks only the appointment. One order everywhere means no lock cycle, so no deadlock between two bookings.
- **Resource row, not overlapping rows.** The spec suggests `SELECT ... FOR UPDATE` on overlapping appointments. I lock the resource row instead. That serialises every booking of the resource, including a booking into a gap where no row exists yet. Locking only existing rows misses that case.
- **Reschedule** is allowed only for `scheduled` and `confirmed`. Spec 7.2 does not say which statuses allow it; this is my default. It re-runs every check, ignoring the appointment itself. It writes an audit row with old and new times but **no history row**, because it is not a status transition.
- **Transitions** follow spec 7.2 exactly (`AppointmentStatus::allowedNext`). Every move writes one history row and one audit row. Cancelling needs a non-blank reason, trimmed. `in_progress` and `completed` are in the state machine, but the admin UI does not offer them. The operator screens in S3 do.
- **History is append-only in code:** the service only creates rows. A database trigger is deferred to S5, as with the audit log (S1-T3).
- **Concurrency is tested on MySQL.** `tests/Concurrency/BookingRaceTest.php` starts two PHP processes that book the same device slot at the same moment, in three rounds. Exactly one must succeed. SQLite has no row locks and cannot share committed data with a second process, so the test is skipped there. The new CI job `mysql` runs the full suite on MySQL 8.0 (spec section 3).
- **CI now annotates Pest failures.** On failure, each failing test appears as an annotation, because the raw log is not downloadable through the API.

## 2026-11-17 — S2-T4: appointment form and free-slot endpoint

- **Slots are computed in memory, by the same rules as `isFree`.** `SlotFinder` reads each table once for the whole range, rather than running `isFree` per candidate (hundreds of queries a week). Its tests use the same boundary cases as `AvailabilityServiceTest`: a booking may end exactly when another starts, and a slot may end exactly at the window's end.
- **The start grid is 15 minutes** (`config('clinic.slot_step_minutes')`). A slot needs the whole service length to be free. The grid is aligned to clock times.
- **Past times are hidden from the picker only.** `BookingService` still does not reject a booking in the past. Whether staff may book the past is an open question for the clinic.
- **The picker shows free times only.** It returns no client data, covers at most 14 days, and needs at least one of specialist, device or room. Access is `appointment.manage` (admin and secretary). Other roles get 403, checked on the route and in the FormRequest.
- **The end time is not an input.** It comes from the service's default length in `AppointmentRequest::bookingData()`, so a form cannot book a wrong-length slot.
- **Edit is reschedule.** `/admin/appointments/{id}/edit` uses the same form. A cancelled, arrived or completed appointment shows a notice and no form. The status actions come in S2-T6.
- **Plain JavaScript, not Alpine.** The form needs one behaviour: show the pickers a service needs, and fetch slots. That is about 150 lines of self-hosted vanilla JS (`public/js/appointment-form.js`), so no dependency was added. Without JavaScript, every picker is shown and the start time can be typed (Gregorian, `YYYY-MM-DD HH:MM`).
- **The picker's date is Jalali.** The API parses `from_jalali` with `Jalali::parseToGregorian`. It does not accept Gregorian, because a Gregorian `2026-11-17` also parses as a valid Jalali year.
- **Known limit: the client picker is a plain select** of every active client, ordered by name, with no search. This is fine for a small clinic. A client search picker is a follow-up before the list grows into the thousands.
- **CI runs the MySQL suite as a separate job** and reports Pest totals and skipped tests on every run, so a skipped concurrency test is visible rather than hidden.

## 2026-11-17 — S2-T2: availability rules

- **Order of checks** in `AvailabilityService::isFree`: working day first (holiday, then weekend from config), then the schedule window, then conflicts. The first failure decides the reason shown, so a holiday is named as a holiday even when it has no hours.
- **Schedule window:** the whole interval must lie inside one schedule row for that resource and weekday. Two adjacent rows do not make a window, because the spec says "inside a schedules row".
- **Overlap is strict** (spec 7.1): `existing.starts_at < new.ends_at AND existing.ends_at > new.starts_at`. An appointment that ends exactly when the next starts does not conflict. This is tested at the boundary.
- **Blocking statuses:** every status except `cancelled` and `no_show` blocks time, including `completed` (spec 7.1 names only those two as non-blocking).
- **Client overlap** uses the same rule and statuses, so one client cannot hold two active appointments at once (spec 7.2).
- **`problems()`** returns messages keyed by form field (`starts_at`, `device_id`, `specialist_id`, `room_id`, `client_id`). `BookingService` shows them on the field at fault.
- **Read only.** `isFree` never writes. `BookingService` runs it again inside its locked transaction, so a slot checked here can still be taken by the time a booking is saved.

## 2026-11-17 — S2-T5: calendar views

- **Ranges are half-open** `[from, to)`. A week runs Saturday to Friday, matching the working week. The month grid is six full Saturday-start weeks, so every day of the month sits in a row and the grid height never changes.
- **Closed days** are holidays (`holiday`) and `config('clinic.weekend_days')` (`weekend`). Both are shown on the day, and both are labelled in Persian, not by colour alone.
- **Day and week show blocks**, one per appointment, coloured by status. The status label is always printed in the block, so colour is not the only cue.
- **Month shows counts** per day, from one grouped query. It does not load appointment rows.
- **Filters:** specialist, device, room and status. The date is a Jalali string, parsed by `Jalali::parseToGregorian`.
- **Access:** `appointment.view_all`, held by admin and secretary. `CalendarService` checks the same permission, so a call from another path also raises `AuthorizationException`. Admin and secretary both see every appointment. No operator or specialist calendar exists yet (S3 and S4).
- **Query budget:** day and week eager-load five relations (client, service, specialist, device, room) and run one holiday query and three filter-dropdown queries. Month runs one grouped count. Tests assert the cap of 15 with `DB::getQueryLog()` for day, week and month.
- **No JavaScript.** Navigation is links that keep the filters, so the calendar works without JS.
- **Persian digits and titles** come from `lang/fa/calendar.php` and `Digits::toPersian`.

## 2026-11-17 — S2: migration reversibility check in CI

- **Why:** `RefreshDatabase` runs the migrations up, but nothing ran them down. Spec 19 asks for reversible migrations on a fresh database. This was the open item in S1-T1 and S2-T1.
- **How:** both CI jobs run `migrate:fresh`, then `migrate:reset` (which calls every `down()` in reverse), then check that `migrate:status` shows no migration as run, then `migrate` again. SQLite runs in the `checks` job and MySQL 8 in the `mysql` job.
- **Status:** green on `d10fb03` in both jobs. This is the first run that includes it.

## 2026-11-17 — S2-T6: transition actions on the appointment page

- **One route, four offered moves.** `POST /admin/appointments/{appointment}/status` (`admin.appointments.transition`, `appointment.manage`) takes `to` and, for a cancel, `cancel_reason`. The staff screen offers `confirmed`, `arrived`, `no_show` and `cancelled`. Any other value is refused at the HTTP layer, so `in_progress` and `completed` cannot be set from the staff screen until the S3 operator screens exist (see S2-T3). The state machine still allows them, so S3 only needs to add them to `AppointmentTransitionRequest::OFFERED`.
- **The state machine decides.** `BookingService::transition()` locks the appointment row, reads the current status and refuses a move that `AppointmentStatus::canMoveTo()` does not allow. A stale page therefore gets a validation error, not a second move. Every accepted move writes one history row and one audit row.
- **The cancel reason is required in the service,** not only in the form. A blank or spaces-only reason is refused (`cancel_reason`). The form field is optional at the HTTP layer, so there is one rule and one message.
- **Cancel reason and the audit trail.** The reason is stored on the appointment and in the audit row's `changes`. This is a short staff note. The form hint asks staff not to write medical details there. The audit log must not hold medical text (spec 7.8). If the clinic wants the reason kept out of the audit row, it is a one-line change in `BookingService::transition()`. **Open question for the clinic.**
- **Where the buttons are.** The edit page shows an action panel above the form, for every saved appointment. Reschedule stays in the form, which is still shown only for scheduled and confirmed appointments. Buttons are plain POST forms, so no JavaScript is needed.
- **Errors** from the three keys (`status`, `to`, `cancel_reason`) show in the panel, in Persian. Messages come from `lang/fa/appointments.php` and `lang/fa/scheduling.php`.
- **Not done:** buttons on the calendar blocks. Clicking a block already opens the appointment edit page, which has the panel.

## 2026-11-17 — CI: the Pest step must fail when Pest fails

- **What went wrong:** the Pest step pipes its output into `tee` so the log can be shown on failure. A pipeline's exit status is that of its last command, so `tee` hid Pest's failure, and the step reported success. The runner's default shell does not set `pipefail`.
- **Fix:** both Pest steps run `set -o pipefail` first. The summary step still runs on failure (`if: always()`), so the cause shows as an annotation.
- **Check:** a green Pest step now means Pest passed. Before trusting a green run, check the totals notice (`0 failing`).

## 2026-11-17 — S3-T5: operator's entries, last seven days

- **Read-only.** `GET /operator/entries` (`operator.entries`), in `area:operator` with `can:session.view`. No forms on the page.
- **Own entries only.** Device sessions with `operator_id` = the user, and measurement sets with `recorded_by` = the user. The window is the last seven clinic days, from midnight, seven days back, to now.
- **Measurement rows show the values** the operator entered, and the set's status (pending review or published). Client names only: no phone numbers and no medical free text.
- **No pagination.** One operator's seven days is bounded by the clinic's day. If it grows, add paging with the existing pager component.

## 2026-11-17 — S3-T4: measurement form (operator)

- **Schema from spec 6.4.** `measurement_types` (seeded, idempotent: `firstOrCreate` by key, so an admin's changes survive a re-seed), `measurement_sets` (client, date, recorder, status, reviewer, publish time, supersedes, superseded, note) and `measurements` (decimal(6,2), unique per set and type). Sets cannot be deleted in the model.
- **Ranges by unit** in `config('clinic.measurement_ranges')`: kg 20–400, cm 10–300, % 1–70 (spec 7.4). Out of range is rejected, never clamped, and nothing is saved (the whole set is refused). The range check is by unit, so muscle mass uses the kg range.
- **Precision.** At most four digits before the point and two after. Persian digits and Persian or comma decimal separators are accepted.
- **Operator form.** Weight and the seven circumferences only (spec 8.3). Body fat and muscle mass are for the specialist, and any other key posted is ignored.
- **Status.** The operator's set is `pending_review`. The specialist's review and publish are not built yet (S4).
- **Override.** Spec 7.4 lets an admin override a range error. It is not built, so there is no override path. Deferred.
- **Audit.** One create row per set, with the type keys and never the values.
- **Access.** `measurement.record` (operator, admin, specialist per spec 5); the operator's route is in `area:operator`, so only operators can reach it from the panel.

## 2026-11-17 — S3-T3: device session recording

- **Refusal is audited with its own action.** `AuditAction::SessionRefused` (`session_refused`) is added, following the S1 precedent (PasswordChange and PasswordReset). The subject is the appointment. The changes hold the device id and the matched contraindication keys only, never medical text. Chosen by the user over reusing `view`.
- **The refusal row is written outside the transaction.** A refusal throws a validation error. If the audit row were inside the transaction, the rollback would remove it. The check and the audit write therefore run before `DB::transaction`.
- **Operators cannot override a contraindication.** This is the MVP simplification in spec 7.3. The override path for admin and specialist, the `override:{client}:{device}` setting, and the override checkbox are not built yet. Deferred, not dropped.
- **Walk-in sessions are not built.** Spec 7.3 allows a walk-in only for admin and specialist. The operator can only record on today's appointments. Deferred.
- **Numbering under lock.** The session takes the client's row lock, then the appointment's (the same order as the booking code). `session_number` is the highest for the client and device plus one. The unique key `(client_id, device_id, session_number)` is the backstop. A MySQL race test (`tests/Concurrency/SessionRaceTest.php`, `record-session-once.php`) checks three rounds of two simultaneous sessions.
- **Package choice.** The oldest package of the appointment's service that still has remaining sessions, by `purchased_at` then id. `PackageService::create` now takes the same client lock, so a package cannot appear between a choice and its save.
- **Status moves.** The first session moves an arrived appointment to in_progress. `finish` moves in_progress to completed, and from arrived it goes through in_progress first. Each move goes through `BookingService::transition`, so it is written to the history and audited.
- **Only done sessions are recorded.** Cancelled and missed sessions are not offered on the operator page yet. The enum has them, and they do not use a package (S3-T1).
- **Parameters.** Only `intensity`, `area` and `duration_min` are stored (spec 6.3), plus the note. Empty values are dropped. Measurements are S3-T4.
- **Access.** The record page and its actions are in `area:operator` with `can:session.record`. The controller returns 404 unless the appointment is open today, needs a device, has a device, and is not cancelled or no-show. The same rule as the list (`TodayAppointments::canOpen`).

## 2026-11-17 — S3-T2: operator list for today

- **Today is the clinic day.** `TodayAppointments::today()` is midnight in `Asia/Tehran`. The list uses the same comparison as S2 (clinic-local `Y-m-d H:i:s` against `starts_at`).
- **What is listed.** Appointments that start today, need a device (`service.requires_device`), and are not cancelled or no-show. Consultations and body analyses are not listed. Soft-deleted clients are left out.
- **Phone digits.** The client's mobile shows only the last four digits, as `پایان شماره`. The full number is never rendered.
- **Alert badges.** Only the computed flags (allergy yes or no, device contraindications, pregnancy), read through `ClientAlertBadges::medicalSelect()`. Free-text medical fields are not loaded, and the badges are not shown to a role without `medical.view_alerts`. As on the client file, this read is not audited because it is a flag, not medical text.
- **Route.** `GET /operator/today` (`operator.today`) is inside `area:operator` and `can:appointment.view_today`. The operator home page links to it.
- **Touch targets.** Rows are at least 44px high, with status chips from `components/badge`.
- **Not yet.** Opening a record from the list is S3-T3. `TodayAppointments::isToday()` is the check it will use.

## 2026-11-17 — S3-T1: session packages and device sessions

- **Schema from spec 6.3.** `session_packages` and `device_sessions` are created now. The device session rows are written in S3-T3, but the remaining-sessions count needs the table to exist now.
- **Unique (client, device, session_number).** `SessionRecorder` computes the next number inside a transaction (S3-T3). The unique key is the last line of defence: two sessions for the same client and device cannot share a number.
- **Remaining sessions are computed, never stored.** `SessionPackage::remainingSessions()` is total minus done sessions. The package list loads the done count with `withCount`, so one query covers the page. Cancelled and missed sessions do not use a package (spec 6.3).
- **Only device services are sold as packages.** The form lists active services of kind `device_session`. A consultation cannot be sold as a package.
- **No edit and no delete in S3.** A package is a financial record. `SessionPackage` and `DeviceSession` refuse delete in the model, like `ClientMedical`. Editing a package (for example to correct the price) is not offered yet.
- **Price** is optional and in Toman. Payments come in S4, so the price is recorded only.
- **Purchase date** is typed in Jalali and stored as Gregorian in `purchased_at`.
- **Audit:** each package creation writes an audit row (service, total, price, date). No client medical data is written.
- **Access:** `package.manage` (admin and secretary), as in spec 5.
- **Known limit:** a package for a client with several device services is chosen by service at recording time (S3-T3), not on the package page.

## 2026-11-17 — S4-T1: report workflow (publish, return, correct)

- **One service for the state changes.** `ReportWorkflow` owns publish, return to operator and correct. Each checks `measurement.review` itself, not only the route, so a service caller cannot skip it. Admin and specialist hold the permission; operator and secretary do not.
- **Publish.** A pending set becomes published with `reviewed_by` and `published_at` (Tehran time). It writes one `publish` audit row and dispatches `ReportPublished`. S5 attaches the `report_ready` SMS to the event. S4 queues nothing yet and sends nothing.
- **Immutability is in the models, not only the services.** `MeasurementSet` refuses any change to a published set except setting `superseded_at` (once). `Measurement` refuses create, update and delete on a published set. The service checks are for clear messages. A bulk `query()->update()` still bypasses model events, so no code path uses one on sets.
- **Correction flow.** `correct()` creates a new pending set with `supersedes_id` pointing at the published set. The old set stays current until the correction is published. On publish the old set gets `superseded_at` in the same transaction. Only one pending correction may exist per set. The chart and client views use `MeasurementSet::current()` (published and not superseded), so they only show the latest version.
- **Return to operator.** The set stays pending and the comment is stored in `measurement_sets.note` (spec 8.4). This replaces the operator's note. The comment is required and is not written to the audit log, since it may name medical detail. The audit row records that the set was returned.
- **Specialist edit before publish.** `MeasurementRecorder::replaceValues()` changes values of a pending set only. It uses the same range and precision checks as recording.
- **Open:** whether the operator's note should be kept separately from the specialist's return comment. The spec puts the comment in `note`, so this build follows it.

## 2026-11-17 — S4-T2: specialist inbox and review page

- **Routes.** `specialist.reviews.index`, `.show`, `.update` (PUT), `.publish` (POST) and `.return` (POST). All sit in `area:specialist` and `can:measurement.review`. The secretary and the operator get 403 on each one (tested).
- **Inbox.** Pending sets only, oldest first (`created_at`, then id). Shows client name, measured date and the recorder's name. No phone, no medical text.
- **Review page.** Pending sets only; a published set returns 404. Opening the page is audited as a `view` of the set, because it reads medical measurements (spec 7.8).
- **Previous values.** A correction compares with the set it supersedes. Any other set compares with the client's latest current published set (`MeasurementSet::current()`), never another client's. The table shows previous, this report, and the change (current minus previous, signed, two decimals).
- **Edit before publish.** The form lists every active type, so the specialist can record body fat and muscle mass as well as the operator's types. A blank field removes that value. The same range rules apply (spec 7.4).
- **Return.** The comment is required (max 500). The set stays pending and the comment replaces `note` (see S4-T1).
- **Corrections on the page.** Not in this task. The correction form goes on the client file in S4-T4, so this page only handles pending sets.
- **Panel link.** The specialist home links to the inbox.

## 2026-11-21 — S4-T3: progress service and chart component

- **One source for progress.** `ProgressService` reads `MeasurementSet::current()` only (published and not superseded), oldest first. A correction replaces the value it corrects, so the chart and summary show the latest version. Pending sets never appear (tested).
- **Summary.** Per active type: first and latest published value and the change (latest minus first, signed, two decimals). A type's first value is taken from the earliest set that has it. The summary is for the client file (S4-T4) and the client portal (V1.0). Only the chart is shown on the review page in this task.
- **Chart data.** One label per current set (Jalali date), oldest first. One series per key, with null where a set has no value (drawn with a gap). At most four series per chart (spec 11), enforced by an exception.
- **Default series.** Weight, waist, abdomen and hip (the spec says "weight and selected circumferences"). The selection is fixed in this task.
- **Colours.** Weight is the brand colour (`#E5546B`). Circumferences use muted tones. The hex values come from the spec tokens; they are in `ProgressService`.
- **Chart.js.** Version 4.6.1 (MIT), self-hosted at `public/vendor/chartjs/chart.umd.js` with its licence. No CDN at runtime (spec 4).
- **RTL.** The time axis is reversed (oldest on the right) and the value axis sits on the right. Legend and tooltip use RTL. Digits in ticks and tooltips are Persian, from the `data-digits` attribute, so no Persian is written in the script.
- **Empty state.** With no values at all, the component shows «هنوز گزارشی منتشر نشده» and loads no script.
- **Review page.** The chart shows the client's published history, not the pending set. The chart script is loaded only when there is data (`@push('scripts')` with `@once`).
- **Not yet.** The chart on the client file (S4-T4) and the "My progress" tile (V1.0). Visual check at 375 px and 1280 px is still to be done in a browser.


## 2026-11-21 — S4-T4: specialist client file, notes and follow-ups

- **Notes are append-only.** `specialist_notes` (spec 6.5) rows cannot be updated or deleted (the model throws). A correction is a new note. Notes are written only by `note.manage` (specialist; admin and secretary cannot reach the panel and the service refuses too).
- **The next follow-up is the latest note's date.** A client's follow-up is the `next_follow_up_on` of its most recent note, even if that is empty. The note form is pre-filled with the current follow-up, so adding a note keeps it. Clearing the date on a new note removes the follow-up. Dates are typed in Jalali and stored as Gregorian.
- **Follow-ups due.** `specialist.followups` lists, for each client, the latest note when its date is within seven days or already past (overdue is marked). Soft-deleted clients are left out. Oldest date first. Only names and dates are shown, so the list is not audited, like the operator's list.
- **Audit.** Adding a note writes a `create` row with the follow-up date only, never the text. Opening the client file writes one `view` row for the client with `sections: [notes, measurements]`. Each page load is audited, as the medical read already is.
- **Client file sections.** Below the details card, on the shared client view: follow-up, notes (newest first, with author and date), the first and latest published values with the change, the trend chart (S4-T3, published sets only), and the published history (newest first). Pending sets are never shown.
- **Medical tab.** Unchanged from S1: the medical record is edited on its own page (`specialist.clients.medical.edit`), audited, and was already editable.
- **Not in this task.** The appointments list on the client file (spec 8.4) and the "My progress" tile (V1.0). The client's own notes are not in the portal (notes are staff-only).
- **Routes.** `specialist.notes.store` (POST `/specialist/clients/{client}/notes`) and `specialist.followups` (GET `/specialist/followups`), both behind `area:specialist` and `can:note.manage`. The follow-ups link is on the specialist home page.
