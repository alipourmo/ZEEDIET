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

## 2026-11-17 — Deployment target: Vercel not used

- **Question:** The user asked for deployment on Vercel. The spec (section 16) targets a Linux VPS in Iran with Nginx, PHP-FPM, MySQL 8, Supervisor and cron.
- **Choice:** No Vercel project is created yet and no deployment is made. The repository contains no provider-specific code (spec section 17, question 10 default).
- **Reason:** Vercel runs serverless functions. It does not give this app a persistent MySQL 8 database, a database queue worker, a scheduler, or a private disk for backups, and it is not in the spec's hosting plan. The Supabase project in the connected apps uses PostgreSQL, but the spec requires MySQL 8. Creating either would contradict the spec. **Open question for the user:** confirm the VPS plan, or explicitly approve a different host.

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
