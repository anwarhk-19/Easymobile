# Bhatkal Mobile Hub

A local marketplace for mobile phones, accessories and repairs, built first for **Bhatkal, Karnataka**. It connects customers, mobile shops and repair technicians, with a central admin team running the marketplace.

* **Customers** browse and search live stock across shops and filter by brand, model, area, price and availability. They can send enquiries, request orders (pickup or delivery), track orders, book repairs, approve or reject quotations, see warranties, review completed transactions and raise complaints. WhatsApp click-to-chat is available everywhere.
* **Merchants** register their shop (it goes live after verification) and manage products, images, compatible models, prices, SKUs and stock. Stock changes are logged and customers see how fresh the stock is. Merchants answer enquiries, quote and confirm orders, record payments, and view reports and settlements.
* **Technicians** create a verified profile and handle repair jobs: accept or decline, diagnose, send versioned quotations, record parts and progress, run a functional-test checklist, hand over the device, and issue a warranty automatically.
* **Admins and support** verify shops and technicians, manage users and listings, oversee orders and repairs, moderate reviews, handle complaints (with an SLA and escalation), and manage refunds, settlements and commission rules. They also maintain catalogue master data, notification templates, reports, search insights, the audit log and system settings.

**Stack:** Laravel 13 · PHP 8.3 · MySQL · Sanctum · Queues and scheduler · React 18 + React Router · Tailwind CSS 4 · Vite · PWA.

See **[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)** for the architecture, ER diagram, roles and permissions, API map, workflows, security and milestones. See **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)** for production setup and backups. **Deploying to Hostinger? Follow [docs/HOSTINGER.md](docs/HOSTINGER.md).**

## Run without Node.js (PHP only)

The compiled frontend (`public/build`) is committed, so you only need **PHP 8.3+ and Composer**. Node.js is not required to run the app.

```bash
composer install
cp .env.example .env && php artisan key:generate
```

Edit `.env`: set `DB_CONNECTION=sqlite` (and remove or comment out the other `DB_*` lines), `OTP_EXPOSE_CODE=true`, and `QUEUE_CONNECTION=sync`. Then run:

```bash
touch database/database.sqlite        # Windows: type nul > database\database.sqlite
php artisan migrate --seed
php artisan storage:link
php artisan serve                     # open http://127.0.0.1:8000
```

You need Node.js only if you change files in `resources/js` or `resources/css`. In that case, run `npm install && npm run build` and commit the updated `public/build`.

## Quick start (local, for frontend development)

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
```

For a quick local setup with SQLite, set these in `.env`:

```dotenv
DB_CONNECTION=sqlite        # and remove/comment the other DB_* lines
OTP_EXPOSE_CODE=true        # shows the OTP on screen because no SMS is sent locally
QUEUE_CONNECTION=sync
```

Then create the database, seed it, build the frontend and start the server:

```bash
touch database/database.sqlite
php artisan migrate --seed  # roles, Bhatkal areas, categories, brands/models, admin, sample shops, products, technicians, orders, repairs
php artisan storage:link
npm run build               # or `npm run dev` alongside the server
php artisan serve
```

To use MySQL instead, keep the `DB_*` values from `.env.example` and create the `bhatkal_mobile_hub` database.

### Demo accounts (sample data)

| Role | Sign in |
|---|---|
| Admin | `/staff`, email `admin@bhatkalmobilehub.in`, password `ChangeMe@2026` |
| Support | `/staff`, email `support@bhatkalmobilehub.in`, password `Support@2026` |
| Customer | `/login`, phone `9900000001` (Ayesha). With `OTP_EXPOSE_CODE=true` the OTP is shown on screen. |
| Merchant | phone `9845000101` (Al-Noor Mobiles) |
| Technician | phone `9845000201` (Imran Mobile Repairs) or `9845000202` (Fix-It Fast) |

A pending shop (New Era Mobiles), a pending technician, an open order request, a repair waiting for quotation approval, a support ticket and some searches with no results are seeded, so every queue has something in it.

## Tests

```bash
php artisan test            # 34 feature tests: auth/OTP, RBAC, catalogue, search, order & repair workflows,
                            # stock, commission, reviews, tickets/SLA, refunds, settlements, admin moderation & audit
npm run build && npm run e2e  # browser smoke test across all four portals (needs the seeded app on :8011)
```

## Project layout

```
app/
  Http/Controllers/Api/{Admin,Merchant,Technician,Customer}   thin controllers
  Http/Requests     FormRequest validation
  Http/Middleware   role / permission / active-account / security headers
  Policies          ownership rules (orders, repairs, tickets, products)
  Services          business logic: OrderService, RepairService, CommissionService, SettlementService, …
  Models            Eloquent models with status constants
database/migrations module-grouped schema
database/seeders    roles, Bhatkal catalogue, platform defaults, sample marketplace
resources/js        React SPA: components/, lib/, pages/{public,customer,merchant,technician,admin}
routes/api.php      versioned REST API (/api/v1)
routes/console.php  scheduled jobs
tests/Feature       API feature tests
tests/e2e           Playwright smoke test
```

## Important behaviours

* **An enquiry is never an order.** An order request is confirmed only after the shop quotes the exact variant, colour, price and delivery fee, the customer accepts, and the shop confirms.
* **No repair work without approval.** A changed quotation pauses the job until the customer approves it again.
* **Payments are recorded, not processed.** Cash on delivery, payment at pickup and UPI references are recorded manually. Settlements track what each merchant is owed; funds are never transferred automatically.
* **WhatsApp click-to-chat is not automated messaging.** Prefilled `wa.me` links come from admin-editable templates. Automated delivery is planned for Phase 3 with an approved WhatsApp Business provider.
