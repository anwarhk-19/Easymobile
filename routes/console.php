<?php

use App\Models\Category;
use App\Models\OtpCode;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\PlatformNotification;
use App\Services\SupportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('bmh:escalate-tickets', function (SupportService $support) {
    $this->info('Escalated '.$support->escalateOverdue().' overdue ticket(s).');
})->purpose('Escalate support tickets not acknowledged within one business day');

Artisan::command('bmh:prune-otps', function () {
    $n = OtpCode::where('expires_at', '<', now()->subDay())->delete();
    $this->info("Pruned {$n} expired OTP code(s).");
})->purpose('Delete expired OTP codes');

Artisan::command('bmh:stock-reminders', function () {
    $days = (int) Setting::get('stock_stale_days');
    $sent = 0;
    Shop::published()->with('merchant')->each(function (Shop $shop) use ($days, &$sent) {
        $stale = Product::where('shop_id', $shop->id)->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('stock_confirmed_at')->orWhere('stock_confirmed_at', '<', now()->subDays($days)))->count();
        if ($stale > 0) {
            $shop->merchant?->notify(new PlatformNotification('Confirm your stock', "{$stale} product(s) have not had stock confirmed in {$days}+ days. Customers see this as stale stock.", '/merchant/products?status=stale', 'warning'));
            $sent++;
        }
    });
    $this->info("Sent {$sent} stock reminder(s).");
})->purpose('Remind merchants to confirm stale stock');

Schedule::command('bmh:escalate-tickets')->hourly();
Schedule::command('bmh:prune-otps')->daily();
Schedule::command('bmh:stock-reminders')->dailyAt('03:30'); // 09:00 IST
Schedule::command('queue:prune-failed --hours=168')->daily();

/*
 * Server health check for shared hosting (Hostinger etc.): php artisan bmh:doctor
 * Checks the things that most often break a fresh deploy and prints a fix for each failure.
 */
Artisan::command('bmh:doctor', function () {
    $ok = 0;
    $fail = 0;
    $check = function (string $label, bool $pass, string $fix = '') use (&$ok, &$fail) {
        $pass ? $ok++ : $fail++;
        $this->line(($pass ? '<fg=green>✓</>' : '<fg=red>✗</>')." {$label}".(! $pass && $fix ? "\n    → {$fix}" : ''));
    };

    $check('PHP '.PHP_VERSION.' (need 8.3+)', version_compare(PHP_VERSION, '8.3.0', '>='), 'Select PHP 8.3 in hPanel → Advanced → PHP Configuration (and use that binary over SSH).');
    foreach (['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'tokenizer', 'ctype', 'json'] as $ext) {
        $check("PHP extension {$ext}", extension_loaded($ext), "Enable {$ext} in hPanel → PHP Configuration → PHP Extensions.");
    }
    $check('.env file present', is_file(base_path('.env')), 'cp .env.example .env and fill it in (see docs/HOSTINGER.md).');
    $check('APP_KEY set', (bool) config('app.key'), 'php artisan key:generate --force');
    $check('APP_DEBUG is off', ! config('app.debug') || ! app()->isProduction(), 'Set APP_DEBUG=false in .env, then php artisan config:cache');
    $check('APP_URL = '.config('app.url'), ! app()->isProduction() || (str_starts_with((string) config('app.url'), 'https://') && ! str_contains((string) config('app.url'), 'localhost')), 'Set APP_URL=https://your-domain in .env, then php artisan config:cache');
    $check('APP_ENV = '.app()->environment(), app()->isProduction() || ! str_contains((string) config('app.url'), '.com'), 'Set APP_ENV=production in .env for the live site, then php artisan config:cache');

    try {
        DB::connection()->getPdo();
        $check('Database connection ('.config('database.default').' / '.config('database.connections.'.config('database.default').'.database').')', true);
        $pending = collect(File::files(database_path('migrations')))->map(fn ($f) => $f->getFilenameWithoutExtension())
            ->diff(DB::table('migrations')->pluck('migration'));
        $check('Migrations up to date', $pending->isEmpty(), 'php artisan migrate --force   (pending: '.$pending->implode(', ').')');
        $check('Base data seeded (roles, categories)', Role::count() > 0 && Category::count() > 0, 'php artisan db:seed --force');
        $check('Admin account exists', User::where('role', 'admin')->exists(), 'Set ADMIN_EMAIL/ADMIN_PASSWORD in .env, then php artisan db:seed --force');
    } catch (Throwable $e) {
        $check('Database connection', false, 'Check DB_HOST (usually localhost), DB_DATABASE, DB_USERNAME, DB_PASSWORD in .env, then php artisan config:cache. Error: '.$e->getMessage());
    }

    foreach (['storage/logs', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/app/public', 'bootstrap/cache'] as $dir) {
        $check("{$dir} writable", is_dir(base_path($dir)) && is_writable(base_path($dir)), "mkdir -p {$dir} && chmod -R 775 storage bootstrap/cache");
    }
    $check('public/storage link', is_link(public_path('storage')) || is_dir(public_path('storage')), 'php artisan storage:link   (or: ln -s ../storage/app/public public/storage)');
    $check('Compiled frontend (public/build/manifest.json)', is_file(public_path('build/manifest.json')), 'git pull — public/build is committed in the repo.');
    $check('No stale public/hot file', ! is_file(public_path('hot')), 'rm public/hot   (it makes pages load assets from a dev server)');
    $check('Root .htaccess routes into public/', is_file(base_path('.htaccess')) && is_file(public_path('.htaccess')), 'git pull — both .htaccess files are in the repo.');

    try {
        $res = app()->handle(Request::create('/api/v1/meta', 'GET', server: ['HTTP_ACCEPT' => 'application/json']));
        $check('API responds internally (GET /api/v1/meta → '.$res->getStatusCode().')', $res->getStatusCode() === 200, 'See the error in storage/logs/laravel.log (tail -50 storage/logs/laravel.log).');
    } catch (Throwable $e) {
        $check('API responds internally', false, $e->getMessage());
    }

    // Request the API through the real web server (domain → .htaccess → PHP), exactly like the browser does.
    $base = rtrim((string) config('app.url'), '/');
    if (str_starts_with($base, 'http') && ! str_contains($base, 'localhost') && ! str_contains($base, '127.0.0.1')) {
        foreach (['/', '/api/v1/meta'] as $path) {
            try {
                $r = Http::timeout(15)->withHeaders(['Accept' => 'application/json'])->withoutRedirecting()->get($base.$path);
                $type = (string) $r->header('Content-Type');
                $good = $path === '/' ? $r->status() === 200 && str_contains($type, 'text/html') : $r->status() === 200 && str_contains($type, 'application/json');
                $check("Public URL {$base}{$path} → HTTP {$r->status()} ({$type})", $good,
                    ($r->header('Location') ? 'Redirects to '.$r->header('Location').' — APP_URL/domain or https redirect mismatch. ' : '')
                    .'Server returned: '.Str::limit(preg_replace('/\s+/', ' ', strip_tags($r->body())), 300));
            } catch (Throwable $e) {
                $check("Public URL {$base}{$path}", false, 'Request failed: '.$e->getMessage().' — check the domain/subdomain DNS, SSL certificate and that it points at this folder.');
            }
        }
    }

    $this->newLine();
    $this->line($fail ? "<fg=red>{$fail} problem(s)</>, {$ok} OK. Fix the ✗ items above." : "<fg=green>All {$ok} checks passed.</> If the site still can't load data, open https://YOUR-DOMAIN/api/v1/meta in a browser and check storage/logs/laravel.log.");

    return $fail ? 1 : 0;
})->purpose('Diagnose common deployment problems (PHP, .env, database, permissions, storage link, API)');
