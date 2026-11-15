# Deployment, Operations & Backups

## Production requirements

* PHP 8.3+ with these extensions: pdo_mysql, mbstring, openssl, fileinfo, gd, intl, bcmath, redis (optional)
* MySQL 8.0+ (utf8mb4)
* Node 20+ (build only)
* A web server (nginx + PHP-FPM) with HTTPS
* Optional: Redis for cache, queue and rate limiting

## First deploy

```bash
git clone <repo> bhatkal-mobile-hub && cd bhatkal-mobile-hub
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && php artisan key:generate
# edit .env: APP_ENV=production, APP_DEBUG=false, APP_URL, DB_*, OTP_EXPOSE_CODE=false,
#            ADMIN_EMAIL / ADMIN_PASSWORD (used once by the seeder), SMS_DRIVER
php artisan migrate --force
php artisan db:seed --force        # roles, catalogue, admin, templates — sample data is skipped in production
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Change the seeded admin password right away.** Create support operators from **Admin → Users → Add staff**.

### nginx essentials

```nginx
root /var/www/bhatkal-mobile-hub/public;
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ { include fastcgi_params; fastcgi_pass unix:/run/php/php8.3-fpm.sock; fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name; }
client_max_body_size 30M;   # multi-image uploads
```

### Workers and scheduler

```cron
* * * * * cd /var/www/bhatkal-mobile-hub && php artisan schedule:run >> /dev/null 2>&1
```

Run the queue worker under Supervisor or systemd:

```
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

## Configuration reference

| Variable | Purpose |
|---|---|
| `OTP_EXPOSE_CODE` | **Must be `false` in production.** Local-only convenience; it is also forced off when `APP_ENV=production`. |
| `OTP_TTL_SECONDS`, `OTP_RESEND_COOLDOWN`, `OTP_MAX_SENDS_PER_PHONE`, `OTP_MAX_SENDS_PER_IP` | OTP security tuning |
| `SMS_DRIVER` | `log` by default. To add a gateway, implement `App\Services\Sms\SmsSender` and bind it in `AppServiceProvider`. |
| `BUSINESS_TIMEZONE` | Default `Asia/Kolkata`. Used for "open now" and the ticket SLA. |
| `QUEUE_CONNECTION` / `CACHE_STORE` | Use `redis` in production if it is available |

Platform settings (support contacts, stock staleness, review auto-approval, order-request limits) are edited in **Admin → Settings**.

## Backup procedure

| What | How | Frequency | Retention |
|---|---|---|---|
| MySQL | `mysqldump --single-transaction --routines --triggers bhatkal_mobile_hub \| gzip > db-$(date +%F-%H%M).sql.gz` | Every 6 hours, plus before each deploy | 14 daily + 8 weekly |
| Private files (`storage/app/private`: verification documents, repair photos, complaint evidence) | `rsync -a` or `restic backup` to encrypted off-site storage | Daily | 30 days |
| Public files (`storage/app/public`: product images, logos) | Same as above | Daily | 30 days |
| `.env` / `APP_KEY` | Store in a password manager or secrets vault. **Without the APP_KEY, encrypted IMEIs cannot be recovered.** | On change | — |

**Restore drill (monthly):** restore the latest dump into a staging database, run `php artisan migrate:status`, sign in as admin and open a few orders and repairs.

Encrypt backups at rest, because they contain personal data such as phone numbers and addresses. Limit who can access them, and delete them when their retention period ends.

## Privacy-conscious operations

* Collect the IMEI only when the customer chooses to share it (network, warranty or lock issues). It is encrypted at rest, and every admin reveal is audit-logged.
* Technicians see customer contact details only after they accept a job. The repair request form tells customers to back up their data and states that technicians must not access personal data.
* Verification documents never sit on the public disk, and every admin view is logged.
* Review **Admin → Audit log** regularly for suspensions, refunds, settlement payments and document access.

## Health and monitoring

* `GET /up` is Laravel's health endpoint.
* Watch these: failed jobs (`php artisan queue:failed`), overdue tickets (Admin → Complaints → Overdue), and stale stock counts (merchant dashboards).
