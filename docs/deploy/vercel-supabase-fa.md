# اجرای آزمایشی ZeeDiet روی Vercel و Supabase

این راهنما برای **آزمایش با دادهٔ ساختگی** است، نه استفادهٔ درمانگاهی یا دادهٔ واقعی مراجعان. برنامه با Laravel/FrankenPHP روی Vercel اجرا می‌شود و PostgreSQL پایدار، نشست‌های ورود و cache را از Supabase می‌گیرد. صف‌ها در این محیط هم‌زمان اجرا می‌شوند؛ worker و scheduler دائمی در Vercel این مخزن نداریم.

## وضعیت آماده‌سازی

- اتصال Supabase فعال است؛ یک schema جدا به نام `zeediet` برای جلوگیری از برخورد با جدول‌های برنامه‌های دیگر در همان پروژه ساخته شده است. جدول‌های برنامه فقط داخل همین schema ساخته می‌شوند.
- کانتینر برای `pdo_pgsql`، اتصال TLS، نشست/cache دیتابیسی و اجرای sync صف تنظیم شده است.
- دسترسی توکن Vercel در این نشست برای فهرست پروژه‌ها رد شد (`Access denied`)؛ چون شناسهٔ پروژه در دسترس نبود، نتوانستم deployment بسازم یا secretها را در Vercel ثبت کنم. پروژه را در داشبورد Vercel از همین مخزن import کنید یا دسترسی پروژه را برای اتصال Vercel در Arena اصلاح کنید.

## ۱. تنظیم Supabase

در داشبورد Supabase همین پروژه، از **Connect**، مقادیر **Session Pooler** را بردارید (نه رمز دیتابیس را در Git یا چت بفرستید). مقادیر معمول شامل host، port، database، username و password هستند. اگر پروژهٔ دیگری انتخاب کردید، ابتدا schema جداگانه‌ای با SQL Editor بسازید:

```sql
CREATE SCHEMA IF NOT EXISTS zeediet AUTHORIZATION postgres;
```

در پروژهٔ متصل فعلی، این schema از قبل ساخته شده است. از اتصال pooler با SSL اجباری استفاده کنید. اگر ابزار اتصال/پلن پروژه اتصال مستقیم را به‌جای Session Pooler توصیه می‌کند، همان host و username نمایش‌داده‌شده در Connect را عیناً وارد کنید.

## ۲. اجرای migration و ساخت حساب‌های آزمایشی

بهتر است migration و seed را یک‌بار از کامپیوتر خودتان اجرا کنید؛ هنگام بالا آمدن هر instance در Vercel migration اجرا نمی‌شود. نسخهٔ PHP محلی باید افزونهٔ `pdo_pgsql` داشته باشد.

در فایل محلی `.env` (که در Git ثبت نمی‌شود) این مقادیر را قرار دهید؛ host/username/password را از Connect بردارید:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=
DB_CONNECTION=pgsql
DB_HOST=<session-pooler-host>
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=<username-from-connect>
DB_PASSWORD=<database-password>
DB_SSLMODE=require
DB_SEARCH_PATH=zeediet
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
```

سپس از ریشهٔ ریپو اجرا کنید:

```bash
composer install
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
```

مقدار `APP_KEY` همین نصب را بعداً در Vercel هم قرار دهید؛ **در هیچ deploy آن را عوض یا دوباره تولید نکنید**. دستور seed برای نقش‌های مدیر، منشی، اپراتور و متخصص رمز موقت می‌سازد و هر رمز را فقط یک‌بار در خروجی ترمینال نشان می‌دهد. آن‌ها را امن نگه دارید؛ در اولین ورود رمز جدید تعیین می‌شود. شماره‌های placeholder برای حساب‌های seed در `README.md` آمده‌اند.

> از `migrate:fresh` روی پروژهٔ Supabase استفاده نکنید؛ این دستور جدول‌های schema انتخاب‌شده را حذف می‌کند. هیچ‌وقت migration را با `DB_SEARCH_PATH=public` روی پروژهٔ مشترک اجرا نکنید.

## ۳. ساخت/تنظیم پروژه در Vercel

1. در Vercel، مخزن GitHub `alipourmo/ZEEDIET` و شاخهٔ فعلی را import کنید. ریشهٔ مخزن را به‌عنوان Root Directory نگه دارید؛ `Dockerfile` و `vercel.json` در ریشه هستند.
2. برای محیط **Preview** این متغیرها را بسازید:

   | نام | مقدار |
   |---|---|
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | `false` |
   | `APP_KEY` | همان کلید ساخته‌شده در مرحلهٔ قبل |
   | `APP_URL` | آدرس HTTPS همین deployment/project در Vercel |
   | `APP_TIMEZONE` | `Asia/Tehran` |
   | `APP_LOCALE` | `fa` |
   | `DB_CONNECTION` | `pgsql` |
   | `DB_HOST` | host نمایش‌داده‌شده در Supabase Connect (Session Pooler) |
   | `DB_PORT` | port نمایش‌داده‌شده در Connect (معمولاً `5432`) |
   | `DB_DATABASE` | database نمایش‌داده‌شده (معمولاً `postgres`) |
   | `DB_USERNAME` | username نمایش‌داده‌شده در Connect |
   | `DB_PASSWORD` | رمز دیتابیس Supabase؛ در Vercel به‌شکل secret ذخیره شود |
   | `DB_SSLMODE` | `require` |
   | `DB_SEARCH_PATH` | `zeediet` |
   | `SESSION_DRIVER` | `database` |
   | `CACHE_STORE` | `database` |
   | `QUEUE_CONNECTION` | `sync` |
   | `SMS_DRIVER` | `log` |

3. پس از ذخیرهٔ متغیرها redeploy کنید. `/healthz` باید `200` و `{"status":"ok"}` بدهد؛ این endpoint اتصال دیتابیس را هم آزمایش می‌کند. `/login` باید فرم ورود را نشان دهد.
4. با شمارهٔ مدیر seed‌شده و رمز موقت وارد شوید و فوراً رمز را عوض کنید.

اگر بعداً URL پروژه عوض شد، `APP_URL` را هم اصلاح و دوباره deploy کنید. `SESSION_DOMAIN` را خالی/null نگه دارید تا cookie به دامنهٔ deployment محدود باشد.

## محدودیت‌های محیط آزمایشی

- هیچ‌وقت دادهٔ واقعی بیمار/مراجع را وارد نکنید. این Supabase پروژهٔ مشترک است، اما schema برنامه از داده‌های دیگر جداست.
- OTP پرتال مراجع بدون SMS واقعی قابل آزمایش نیست: `SMS_DRIVER=log` در محیط production عمداً پیامک را رد می‌کند و کد را افشا نمی‌کند. اگر Kavenegar را روشن کنید پیامک واقعی ارسال می‌شود و ممکن است هزینه داشته باشد.
- فایل‌سیستم محلی کانتینر و logها ماندگار نیستند؛ قابلیت backup محلی، upload و scheduler را روی این deployment حساب نکنید.
- این تنظیمات جایگزین استقرار production نیست. برای production، backup خارج از سرور، worker/scheduler پایدار، SMS، مانیتورینگ و بررسی امنیتی جداگانه لازم است.
