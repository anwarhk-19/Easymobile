<?php

namespace App\Http\Controllers;

use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\PlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SampleMarketplaceSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Browser-based first-time setup for hosting without SSH (e.g. Hostinger Git deploys).
 *
 * Writes .env, generates the app key, connects to MySQL, runs migrations, seeds base data,
 * creates the admin account and links storage. It locks itself afterwards: the page returns 404
 * once storage/app/installed.lock exists, or whenever the database already has an admin account.
 */
class InstallController extends Controller
{
    public const LOCK = 'app/installed.lock';

    public static function isInstalled(): bool
    {
        if (is_file(storage_path(self::LOCK))) {
            return true;
        }
        try {
            return Schema::hasTable('users') && User::where('role', 'admin')->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function show(Request $request)
    {
        abort_if(self::isInstalled(), 404);

        return $this->form($request, [], [
            'app_url' => $request->getSchemeAndHttpHost().rtrim($request->getBasePath(), '/'),
            'db_host' => 'localhost',
            'db_port' => '3306',
            'demo_data' => true,
            'test_otp' => '998001',
        ]);
    }

    public function store(Request $request)
    {
        abort_if(self::isInstalled(), 404);
        @set_time_limit(300);

        $v = Validator::make($request->all(), [
            'app_url' => ['required', 'url', 'max:200'],
            'db_host' => ['required', 'string', 'max:120'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string', 'max:120'],
            'db_username' => ['required', 'string', 'max:120'],
            'db_password' => ['nullable', 'string', 'max:200'],
            'admin_email' => ['nullable', 'email', 'max:150'],
            'admin_password' => ['nullable', 'string', 'min:10', 'max:100', 'confirmed'],
            'test_otp' => ['nullable', 'digits:6'],
            'demo_data' => ['nullable', 'boolean'],
        ], [
            'admin_password.confirmed' => 'The two admin passwords do not match.',
            'admin_password.min' => 'Use at least 10 characters for the admin password.',
            'test_otp.digits' => 'The test OTP must be exactly 6 digits.',
        ]);
        $old = $request->except(['db_password', 'admin_password', 'admin_password_confirmation']);
        if ($v->fails()) {
            return $this->form($request, $v->errors()->all(), $old);
        }
        $d = $v->validated();
        // Blank admin fields → the default staff logins (see PlatformSeeder); change them later in Admin → My account.
        $d['admin_email'] = $d['admin_email'] ?: PlatformSeeder::DEFAULT_ADMIN_EMAIL;
        $d['admin_password'] = $d['admin_password'] ?: PlatformSeeder::DEFAULT_ADMIN_PASSWORD;
        $d['default_password'] = $d['admin_password'] === PlatformSeeder::DEFAULT_ADMIN_PASSWORD;
        $d['test_otp'] = $d['test_otp'] ?? null;

        // 1. Check the database credentials on a temporary connection before changing or writing anything.
        $mysql = array_merge(config('database.connections.mysql'), [
            'host' => $d['db_host'], 'port' => $d['db_port'], 'database' => $d['db_database'],
            'username' => $d['db_username'], 'password' => $d['db_password'] ?? '',
        ]);
        config(['database.connections.installer_check' => $mysql]);
        try {
            DB::connection('installer_check')->getPdo();
        } catch (Throwable $e) {
            return $this->form($request, [self::dbError($e)], $old);
        } finally {
            DB::purge('installer_check');
        }

        // 2. Write .env (keeping a backup of any previous one).
        $key = 'base64:'.base64_encode(random_bytes(32));
        $env = $this->buildEnv($d + ['app_key' => $key]);
        if (is_file(base_path('.env'))) {
            @copy(base_path('.env'), base_path('.env.backup-'.date('YmdHis')));
        }
        if (@file_put_contents(base_path('.env'), $env) === false) {
            return $this->form($request, ['Could not write the .env file. In hPanel → File Manager, make sure the site folder is writable (permissions 755) and try again.'], $old);
        }

        // 3. Apply the new settings to this request and build the database.
        @unlink(base_path('bootstrap/cache/config.php'));
        config(['database.connections.mysql' => $mysql, 'database.default' => 'mysql']);
        DB::purge('mysql');
        config(['app.key' => $key, 'app.url' => $d['app_url'], 'cache.default' => 'array', 'session.driver' => 'array']);
        foreach (['ADMIN_EMAIL' => $d['admin_email'], 'ADMIN_PASSWORD' => $d['admin_password']] as $k => $val) {
            $_ENV[$k] = $_SERVER[$k] = $val;
            putenv("{$k}={$val}");
        }

        $steps = [];
        try {
            Artisan::call('migrate', ['--force' => true]);
            $steps[] = 'Database tables created';
            Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
            Artisan::call('db:seed', ['--class' => CatalogueSeeder::class, '--force' => true]);
            $steps[] = 'Roles, Bhatkal areas, categories, brands and phone models added';

            $admin = User::firstOrNew(['email' => $d['admin_email']]);
            $admin->forceFill(['name' => 'Platform Admin', 'role' => 'admin', 'status' => 'active', 'password' => $d['admin_password']])->save();
            Artisan::call('db:seed', ['--class' => PlatformSeeder::class, '--force' => true]);
            $steps[] = 'Admin account, commission rules, message templates and settings created';

            if (! empty($d['demo_data'])) {
                Artisan::call('db:seed', ['--class' => SampleMarketplaceSeeder::class, '--force' => true]);
                $steps[] = 'Demo shops, products, technicians, customers and orders added';
            }
        } catch (Throwable $e) {
            report($e);

            return $this->form($request, ['Setup stopped while building the database: '.Str::limit($e->getMessage(), 300).' — fix the problem and submit again; completed steps are safe to repeat.'], $old);
        }

        try {
            if (! is_link(public_path('storage')) && ! is_dir(public_path('storage'))) {
                Artisan::call('storage:link');
            }
            $steps[] = 'Image uploads folder linked';
        } catch (Throwable) {
            $steps[] = 'Could not link the image uploads folder automatically (images may not show) — see docs/HOSTINGER.md';
        }

        foreach (['config', 'route', 'view'] as $c) {
            try {
                Artisan::call("{$c}:clear");
            } catch (Throwable) {
            }
        }
        @file_put_contents(storage_path(self::LOCK), 'Installed '.now()->toIso8601String()."\n");

        return response()->view('install', ['done' => true, 'steps' => $steps, 'data' => $d, 'errors' => [], 'old' => []])->header('Cache-Control', 'no-store');
    }

    private function form(Request $request, array $errors, array $old)
    {
        return response()->view('install', ['done' => false, 'errors' => $errors, 'old' => $old, 'steps' => [], 'data' => []], $errors ? 422 : 200)
            ->header('Cache-Control', 'no-store');
    }

    private function buildEnv(array $d): string
    {
        $template = is_file(base_path('.env.example')) ? file_get_contents(base_path('.env.example')) : '';
        $values = [
            'APP_NAME' => 'Bhatkal Mobile Hub',
            'APP_ENV' => 'production',
            'APP_KEY' => $d['app_key'],
            'APP_DEBUG' => 'false',
            'APP_URL' => rtrim($d['app_url'], '/'),
            'LOG_LEVEL' => 'warning',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $d['db_host'],
            'DB_PORT' => (string) $d['db_port'],
            'DB_DATABASE' => $d['db_database'],
            'DB_USERNAME' => $d['db_username'],
            'DB_PASSWORD' => $d['db_password'] ?? '',
            'SESSION_DRIVER' => 'database',
            'QUEUE_CONNECTION' => 'sync',
            'CACHE_STORE' => 'database',
            'OTP_EXPOSE_CODE' => 'false',
            'ADMIN_EMAIL' => $d['admin_email'],
            // Not kept in .env after setup: the account already exists with this password.
            'ADMIN_PASSWORD' => '',
            'OTP_TEST_CODE' => $d['test_otp'] ?: 'off',
        ];
        foreach ($values as $k => $val) {
            $line = $k.'='.self::quote($val);
            $template = preg_match("/^#?\s*{$k}=.*$/m", $template)
                ? preg_replace("/^#?\s*{$k}=.*$/m", str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $template, 1)
                : rtrim($template)."\n{$line}\n";
        }

        return $template;
    }

    private static function quote(string $v): string
    {
        if ($v === '' || preg_match('/^[A-Za-z0-9_.:\/@+=-]+$/', $v)) {
            return $v;
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $v).'"';
    }

    private static function dbError(Throwable $e): string
    {
        $m = $e->getMessage();

        return 'Could not connect to the database: '.match (true) {
            str_contains($m, 'could not find driver') => 'the PHP MySQL driver is missing. Enable pdo_mysql in hPanel → PHP Configuration → PHP Extensions.',
            str_contains($m, 'Access denied') => 'wrong username or password. Copy them exactly from hPanel → Databases → MySQL Databases (the username includes the u123456789_ prefix).',
            str_contains($m, 'Unknown database') => 'the database name is wrong. Copy it exactly from hPanel → Databases, including the u123456789_ prefix.',
            str_contains($m, 'getaddrinfo') || str_contains($m, 'Name or service not known') || str_contains($m, 'php_network_getaddresses') => 'the host name is wrong. On Hostinger use localhost.',
            default => 'the server refused the connection. Use host localhost (or 127.0.0.1) and port 3306.',
        };
    }
}
