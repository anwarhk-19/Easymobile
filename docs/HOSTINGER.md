# Deploying to Hostinger (Git → hPanel)

The repository is set up to run on Hostinger shared/cloud hosting **without Node.js**: the compiled frontend (`public/build`) is committed, and a root `.htaccess` sends every request into Laravel's `public/` folder.

## Quickest setup — no SSH (recommended)

1. In hPanel → **Databases → MySQL Databases**, create a database and user, and note the name, username and password.
2. Open **`https://YOUR-DOMAIN/install`** on your phone or computer. Enter the database details and tap **Install**. Leave the admin fields blank to use the default login.
3. Done. The setup page locks itself afterwards.

**Default logins (change the passwords right away; they are public):**

| Who | Where | Login |
|---|---|---|
| Admin | `/staff` | `admin@bhatkalmobilehub.in` / `ChangeMe@2026` |
| Support | `/staff` | `support@bhatkalmobilehub.in` / `Support@2026` |
| Demo customer | `/login` | `9900000001`, OTP `998001` |
| Demo shop owner | `/login` | `9845000101`, OTP `998001` |
| Demo technician | `/login` | `9845000201`, OTP `998001` |

**Testing phase:** OTP `998001` signs in **any** phone number, so testers can register as customer, shop owner or technician with their own numbers.

### Before launch checklist
1. Connect an SMS provider for real OTPs (e.g. Twilio Verify or MSG91; SendGrid sends email only).
2. Set `OTP_TEST_CODE=off` in `.env`.
3. Change the admin and support passwords in **Admin → My account**.
4. Set `STATUS_PAGE=false` in `.env`.

The `/status` page lists items 2 and 3 as ⚠ reminders until they're done.

## Where to install: domain root or a folder

The app works in either place, with the same code:

| Install path in hPanel → Git | Site address | `APP_URL` in `.env` |
|---|---|---|
| *(empty)* → `public_html` | `https://devpulsetech.com` | `https://devpulsetech.com` |
| `easymobile` → `public_html/easymobile` | `https://devpulsetech.com/easymobile` | `https://devpulsetech.com/easymobile` |

If your main website already uses `devpulsetech.com`, install into a folder such as **`easymobile`** so the two sites don't overwrite each other. `APP_URL` **must include the folder**, otherwise image links break. The app detects the folder automatically for its pages, API calls and assets. The API is always at `<site address>/api/v1/...`; no separate subdomain is needed.

## 1. One-time hPanel setup

1. **PHP version** — hPanel → *Advanced → PHP Configuration* → select **PHP 8.3** (or newer). Enable the extensions `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `intl` and `bcmath`. Most of these are on by default.
2. **Database** — hPanel → *Databases → MySQL Databases*. Create a database and a user, and note the name, user and password. Hostinger prefixes them, e.g. `u123456789_bmh`.
3. **Git** — hPanel → *Advanced → Git*:
   * Repository: `https://github.com/anwarhk-19/Easymobile.git` (if the repo is private, add the SSH deploy key that hPanel shows you to GitHub → repo → Settings → Deploy keys)
   * Branch: `master`
   * Install path: **empty** to deploy into `public_html`, or **`easymobile`** to deploy into `public_html/easymobile` (see the table above)
   * After creating it, enable **Auto deployment** and add the webhook URL that hPanel gives you to GitHub → Settings → Webhooks.
4. **SSH** — hPanel → *Advanced → SSH Access* → enable it. You need SSH once for setup and after each deploy.

## 2. First deploy (over SSH)

```bash
cd ~/domains/YOUR-DOMAIN/public_html      # the folder the Git deploy wrote to (add /easymobile for a folder install)
cp .env.example .env
nano .env
```

Set these values in `.env`:

```dotenv
APP_NAME="Bhatkal Mobile Hub"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR-DOMAIN          # folder install: https://devpulsetech.com/easymobile

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u123456789_bmh
DB_USERNAME=u123456789_bmh
DB_PASSWORD=your-db-password

QUEUE_CONNECTION=sync        # no background worker on shared hosting
CACHE_STORE=database
SESSION_DRIVER=database

ADMIN_EMAIL=you@yourdomain.com
ADMIN_PASSWORD=a-strong-password   # required: the production seeder refuses to run without it
OTP_EXPOSE_CODE=false
```

Then run:

```bash
bash deploy.sh                # composer install, app key, migrations, storage link, caches
php artisan db:seed --force   # roles, Bhatkal areas, categories, brands/models, admin account, templates
```

Production seeding creates **no sample shops, products or demo accounts**. Sign in at `https://YOUR-DOMAIN/staff` with `ADMIN_EMAIL` / `ADMIN_PASSWORD`.

> If `composer` is not found, use `php ~/composer.phar` instead, or run `COMPOSER_BIN="php composer.phar" bash deploy.sh`.
> If `php` points to an older version over SSH, run `PHP_BIN=/opt/alt/php83/usr/bin/php bash deploy.sh` (check with `ls /opt/alt/`).

## 3. Every later deploy

Push to `master`. Hostinger pulls the code automatically if auto-deployment is on; otherwise click *Deploy* in hPanel. Then run this over SSH:

```bash
cd ~/domains/YOUR-DOMAIN/public_html && bash deploy.sh
```

The Git deploy only copies files. It does **not** run Composer or migrations, so always run `deploy.sh` after a deploy.

## 4. Cron job (scheduler)

hPanel → *Advanced → Cron Jobs* → **every minute**:

```
/usr/bin/php /home/u123456789/domains/YOUR-DOMAIN/public_html/artisan schedule:run
```

Replace the user and domain with yours, and use the PHP 8.3 binary path if `/usr/bin/php` is older. The scheduler escalates overdue complaints, sends stock reminders to merchants and cleans up old OTPs.

## 5. Uploaded images (`storage:link`)

`deploy.sh` creates the link `public/storage → storage/app/public`. If Hostinger blocks symlinks, the script prints a warning and product images won't load. In that case run:

```bash
ln -s ../storage/app/public public/storage
```

## Testing logins before SMS is connected

1. Load the demo accounts (shops, products, technicians, customers):
   ```bash
   php artisan db:seed --class=SampleMarketplaceSeeder --force
   ```
2. Add a test code of your choice to `.env`, then run `php artisan config:cache`:
   ```dotenv
   OTP_TEST_CODE=246810
   ```
3. Sign in at `/login` with a demo number (e.g. customer `9900000001`, shop `9845000101`, technician `9845000201`) and that code.

The test code works **only for the demo numbers**. To add your own test numbers, list them, including the demo ones you still want: `OTP_TEST_PHONES=9900000001,9845000101,98XXXXXXXX`. Every test-code login is written to the log. **Before real customers use the site, delete `OTP_TEST_CODE`** and run `php artisan config:cache`.

## ⚠️ Customer login needs an SMS provider

Customers, merchants and technicians sign in with an **OTP sent by SMS**. The project ships with a *log* SMS driver, which writes the OTP to `storage/logs/laravel.log` instead of sending it. So until a real SMS gateway (MSG91, Fast2SMS, Twilio, etc. — DLT-registered for India) is plugged in, **only the admin (email/password) can sign in on the live site**.

Never set `OTP_EXPOSE_CODE=true` on a public site. It would let anyone sign in as anyone.

## Troubleshooting

**Easiest check — no SSH needed:** open **`https://YOUR-DOMAIN/status`** on your phone. This page doesn't use the API, so it works even when the API is failing. It checks the database connection, tables, base data, cache, folder permissions, the uploads link and the built frontend. Every failed check (✗) comes with the exact fix. It never shows passwords or server paths. Once the site is live, hide it by setting `STATUS_PAGE=false` in `.env` and running `php artisan config:cache`.


**Run the health check first.** It checks PHP, `.env`, the database, migrations, folder permissions, the storage link, the built frontend, and whether the API answers. Each problem it finds comes with the fix:

```bash
php artisan bmh:doctor
```

**Live logs over SSH.** This works even when the website itself is down:

```bash
php artisan bmh:logs                      # last 50 entries
php artisan bmh:logs -f                   # follow live (Ctrl+C to stop)
php artisan bmh:logs -f --level=error     # only errors and worse
php artisan bmh:logs --grep=SQLSTATE --trace --lines=200
```

**Reading errors:**
- Once the site works, admins can read errors in **Admin → System logs**. That page can filter by level, search, show stack traces, download and clear the log.
- If the API is down, that page can't load. Read the log over SSH instead: `tail -100 storage/logs/laravel.log`.

**The app says "Couldn't reach the server":** open `https://YOUR-DOMAIN/api/v1/meta` directly in a browser. You should see JSON. Anything else (an error page, a blank page, or a download prompt) shows where the problem is. Then run `php artisan bmh:doctor`.


| Symptom | Fix |
|---|---|
| 500 error | Check `storage/logs/laravel.log`. Usually a missing `.env`, an empty `APP_KEY`, wrong DB credentials, or Composer was never run. Run `bash deploy.sh`. |
| "Permission denied" writing logs/cache | `chmod -R 775 storage bootstrap/cache` |
| Page loads but has no styling | Make sure `public/build/manifest.json` exists (it is committed) and that `APP_URL` matches your domain |
| Changed `.env` but nothing changed | Run `php artisan config:cache`; the config is cached |
| Domain shows a directory listing or Hostinger's default page | Delete Hostinger's default `public_html/default.php` / `index.html`, and make sure the repo's `.htaccess` is in `public_html` |
