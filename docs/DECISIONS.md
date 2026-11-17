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
