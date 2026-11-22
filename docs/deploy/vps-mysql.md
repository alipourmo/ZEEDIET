# Release checklist

This is the checklist for a production release of ZeeDiet (see the deployment requirements in `docs/SPEC.md`, section 16). Follow it in order.

The production target is a Linux VPS in Iran: Nginx, PHP-FPM 8.4, MySQL 8, a Supervisor queue worker, and cron for the scheduler. A separate Vercel + Supabase setup is documented for experimental testing only; do not put real client data there. See [vercel-supabase-fa.md](vercel-supabase-fa.md).

## 1. Before you start

- [ ] The hosting domain is chosen, DNS points at the server, and the SSL certificate is issued (see section 6).
- [ ] The SMS sender and the Kavenegar API key are issued by the provider. Without them, keep `SMS_DRIVER=log`, which
      sends no messages. Do not use the log driver in production for real clients.
- [ ] CI is green on the commit to release (Pint, PHPStan and Pest on SQLite/MySQL; PostgreSQL migrations, seed and compatible tests).
- [ ] `main` contains the commit to release. The release is deployed from `main` only.

## 2. Environment variables

Set these in the server environment (for example the PHP-FPM pool or `/etc/zeediet.env`). Never commit them. The table is
the minimum. `.env.example` has comments for every variable.

| Variable | Production value | Notes |
|---|---|---|
| `APP_ENV` | `production` | Turns on secure cookies and HSTS (spec 10.1). |
| `APP_DEBUG` | `false` | Never `true` in production. |
| `APP_KEY` | a stable key | Generate once with `php artisan key:generate --show`. **Keep it.** Losing or changing it logs everyone out and makes encrypted data unreadable. |
| `APP_URL` | `https://<domain>` | |
| `APP_TIMEZONE` | `Asia/Tehran` | |
| `APP_LOCALE` | `fa` | |
| `DB_CONNECTION` | `mysql` | MySQL 8, `utf8mb4`. |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | | The backup restore test needs `CREATE` and `DROP` rights on the scratch schema (`<database>_backup_restore_test`). |
| `SESSION_DRIVER` | `database` | Sessions survive a PHP-FPM restart. |
| `QUEUE_CONNECTION` | `database` | The worker reads the `jobs` table. |
| `CACHE_STORE` | `database` or `file` | |
| `LOG_CHANNEL` | `stack` or `daily` | Logs must not contain codes or phone numbers. `storage/logs/sms.log` is never written in production. |
| `SMS_DRIVER` | `kavenegar` | The `log` driver refuses to send in production, so no message file is written. |
| `KAVENEGAR_API_KEY`, `KAVENEGAR_SENDER` | from the provider | |
| `CLINIC_NAME` | the clinic's name | Used in SMS bodies. |
| `CLINIC_RETENTION_DAYS` | the period the clinic chooses | Empty means `clients:purge` refuses to run. Set it before the first purge. |
| `BACKUP_ENCRYPTION_KEY` | `base64:` + 32 random bytes | Required. Without it `backup:run` refuses to run. Store a copy outside the server. Losing it means the backups cannot be read. |
| `BACKUP_DISK` | an off-server disk (for example an S3-compatible bucket configured in `config/filesystems.php`) | Must not be `local` in production. |

Generate the backup key once:

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"
```

## 3. Migration order

Run the migrations on an empty database, then seed. The migrations run in filename order, which is also the dependency
order. They are reversible (`migrate:rollback --step=N`). Do not edit a migration that has run in production. Add a new one.

```bash
php artisan migrate --force
php artisan db:seed --force
```

The seeders are idempotent. `DatabaseSeeder` runs `StaffSeeder`, `CatalogSeeder`, `ScheduleSeeder`,
`MeasurementTypeSeeder` and `SmsTemplateSeeder`. Running them twice does not create duplicates.

`StaffSeeder` creates one account per role. Each gets a random temporary password, printed once in the seeder output, and must
change it at the first sign-in. Record the printed passwords and change them before the clinic starts using the system.
If the output was lost, reset the password from `/admin/users`.

Order of the S5 migrations, for reference (all after the S1 to S4 tables):

1. `2026_11_22_000100_create_sms_tables` (templates and `sms_log`)
2. `2026_11_22_000200_create_otp_codes_table`
3. `2026_11_23_000100_add_starts_at_index_to_appointments` (index for calendar and day lists)

## 4. Application setup

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link
```

Run `php artisan config:cache` only after the environment variables are set. Run it again after any change to `.env`.

## 5. Supervisor and cron

### Queue worker (Supervisor)

SMS sending runs on the queue, so the worker must stay up. Create `/etc/supervisor/conf.d/zeediet-worker.conf`:

```ini
[program:zeediet-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/zeediet/artisan queue:work database --sleep=3 --tries=1 --max-time=3600
directory=/var/www/zeediet
user=www-data
numprocs=1
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/zeediet-worker.log
stopwaitsecs=3600
```

The worker's `--max-time` makes it restart hourly, so code changes are picked up. After each deploy run
`php artisan queue:restart`.

Then:

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl status zeediet-worker:*
```

### Scheduler (cron)

As the web user, add one cron line. The scheduler runs the SMS reminders every 15 minutes and the nightly backup:

```cron
* * * * * cd /var/www/zeediet && php artisan schedule:run >> /dev/null 2>&1
```

What the scheduler runs (`routes/console.php`):

| Command | When | Purpose |
|---|---|---|
| `appointments:send-reminders` | every 15 minutes | 24-hour reminders (spec 9) |
| `backup:run` | 02:30 daily (Tehran time) | Encrypted backup (spec 10.8). Retention: 14 daily and 8 weekly copies. |

Do not add a second backup cron. The scheduler is the only schedule. The `withoutOverlapping()` option stops a second
backup from starting while one is still running.

## 6. SSL and HTTP

- Nginx serves `/var/www/zeediet/public`. PHP requests go to PHP-FPM 8.4.
- Issue the certificate with Let's Encrypt (`certbot --nginx -d <domain>`) and keep the automatic renewal timer enabled.
- Redirect all HTTP to HTTPS. HSTS (`Strict-Transport-Security`) is sent by the application in production (spec 10.1),
  so do not add a conflicting header in Nginx.
- Check the headers after deploy: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy: same-origin`, `Content-Security-Policy`, `X-Robots-Tag: noindex, nofollow`, and
  `Strict-Transport-Security`.
- `/robots.txt` must disallow all crawlers, and every page must be `noindex`.

## 7. Backups

### Schedule and retention

- `backup:run` writes `backups/zeediet-YYYYMMDD-HHMMSS.zdb` to `BACKUP_DISK` every night at 02:30.
- Retention keeps the newest copy of each of the last 14 calendar days, then the newest copy of each of the 8 weeks before
  that.
- Each archive is encrypted with AES-256-GCM using `BACKUP_ENCRYPTION_KEY`. A damaged or wrong-key archive is refused.

### Restore test (required before go-live and after any change to backups)

```bash
php artisan backup:run
php artisan backup:restore-test
```

`backup:restore-test` restores the newest backup (or `--file=<name>`) into a scratch schema, not the live database. It
checks every table's row count against the backup's manifest, then drops the scratch schema. Run it on a seeded database
before go-live (MVP gate, spec 12, item 7), and once a month after that.

### Checks

- [ ] `backup:run` succeeds and the file appears on `BACKUP_DISK`, off the server.
- [ ] The archive cannot be read without `BACKUP_ENCRYPTION_KEY`. Check by downloading a copy and opening it in a text
      editor: no client names or phone numbers should be visible.
- [ ] `backup:restore-test` passes.
- [ ] The backup key is stored outside the server.

## 8. Client purge

`clients:purge {id}` hard-deletes a client. It needs three things: the client is soft-deleted, the retention period
(`CLINIC_RETENTION_DAYS`) has passed, and the command is run with `--confirm`.

```bash
php artisan clients:purge 123            # shows the rows that would be removed. Deletes nothing.
php artisan clients:purge 123 --confirm  # asks once more, then deletes and writes an audit row.
```

The audit row records the row counts, not the client's personal data. The purge is not reversible. Only purge after the
clinic's admin has confirmed it in writing.

## 9. Deploy steps

1. Put the site in maintenance mode if the migration is slow: `php artisan down`.
2. Take a backup first: `php artisan backup:run`. Confirm it succeeded.
3. Pull `main` and run `composer install --no-dev --optimize-autoloader`.
4. `php artisan migrate --force`.
5. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
6. `php artisan queue:restart`.
7. `php artisan up`.
8. Smoke test: sign in as staff, open the client list and the calendar, sign in to `/my-account` with a test client, and
   open a report.

## 10. Rollback

Rollback is for a bad release. Data is the priority, so the order matters.

1. Stop new writes: `php artisan down`.
2. Check out the previous release tag and run `composer install --no-dev --optimize-autoloader`.
3. If the release added migrations, roll them back one at a time, newest first. Each S5 migration is reversible:
   `php artisan migrate:rollback --step=1` (the starts_at index), then `--step=1` (OTP codes), then `--step=1`
   (SMS tables). **Rolling back the SMS tables deletes `sms_log` rows.** Take a backup first.
4. `php artisan config:clear && php artisan route:clear && php artisan view:clear`, then `php artisan config:cache`.
5. `php artisan queue:restart`, then `php artisan up`.
6. If the data is damaged, restore from the most recent good backup. Restore into a scratch database first with
   `backup:restore-test --file=<name>`, check the counts, then restore into production with the clinic's approval.

Keep `APP_KEY` and `BACKUP_ENCRYPTION_KEY` unchanged through a rollback. Changing either makes existing data unreadable.

## 11. Known limits

- SMS delivery depends on the provider. The app records each attempt in `sms_log`, and an admin can resend a failed message
  from `/admin/sms/log`.
- The OTP sign-in is limited per mobile and per IP (see `config/clinic.php`, `portal`). A determined attacker on many IPs
  can still try many numbers. Watch the audit log and the sms log for unusual volume.
- The experimental Vercel setup uses the external Supabase PostgreSQL database for application data, sessions and cache, with synchronous queue dispatch. Do not use it for production: Vercel storage is ephemeral and there is no persistent worker/scheduler or off-site backup configured.
