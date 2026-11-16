<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Checks everything the API needs, without revealing secrets (no passwords, hosts, paths or raw errors).
 * Used by the public /status page and safe to show to anyone.
 */
final class HealthCheck
{
    /** @return array<int, array{label:string, ok:bool, hint:?string}> */
    public static function run(): array
    {
        $out = [];
        $add = function (string $label, bool $ok, ?string $hint = null) use (&$out) {
            $out[] = ['label' => $label, 'ok' => $ok, 'hint' => $ok ? null : $hint];
        };

        $add('PHP 8.3 or newer', version_compare(PHP_VERSION, '8.3.0', '>='), 'Select PHP 8.3 in hPanel → Advanced → PHP Configuration.');
        $add('PHP MySQL driver (pdo_mysql)', extension_loaded('pdo_mysql'), 'Enable pdo_mysql in hPanel → PHP Configuration → PHP Extensions.');
        $add('App key configured', (bool) config('app.key'), 'Run: php artisan key:generate --force && php artisan config:cache');
        $add('Debug mode off', ! config('app.debug') || ! app()->isProduction(), 'Set APP_DEBUG=false in .env, then php artisan config:cache');
        $add('Site address (APP_URL) matches this domain', self::sameHost(), 'Set APP_URL=https://'.request()->getHost().' in .env, then php artisan config:cache');

        $dbOk = false;
        try {
            DB::connection()->getPdo();
            $dbOk = true;
            $add('Database connection', true);
        } catch (Throwable $e) {
            $add('Database connection', false, self::dbHint($e));
        }

        if ($dbOk) {
            $tables = ['migrations', 'users', 'roles', 'categories', 'products', 'shops', 'cache', 'personal_access_tokens'];
            $missing = array_values(array_filter($tables, fn ($t) => ! Schema::hasTable($t)));
            $add('Database tables created', $missing === [], 'Run: php artisan migrate --force   (missing: '.implode(', ', $missing).')');
            if ($missing === []) {
                $pending = collect(File::files(database_path('migrations')))->map(fn ($f) => $f->getFilenameWithoutExtension())
                    ->diff(DB::table('migrations')->pluck('migration'))->count();
                $add('All migrations applied', $pending === 0, "Run: php artisan migrate --force   ({$pending} pending)");
                $add('Base data loaded (roles, categories)', Role::count() > 0 && Category::count() > 0, 'Run: php artisan db:seed --force');
                $add('Admin account exists', User::where('role', 'admin')->exists(), 'Set ADMIN_EMAIL and ADMIN_PASSWORD in .env, then: php artisan db:seed --force');
            }
        }

        try {
            Cache::put('health-check', 'ok', 10);
            $add('Cache store ('.config('cache.default').')', Cache::get('health-check') === 'ok', 'Run: php artisan migrate --force (creates the cache table), or set CACHE_STORE=file');
        } catch (Throwable $e) {
            $add('Cache store ('.config('cache.default').')', false, 'Cache is not working. If CACHE_STORE=database, fix the database first; otherwise set CACHE_STORE=file and run php artisan config:cache');
        }

        foreach (['storage/logs', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'bootstrap/cache'] as $dir) {
            $add("Folder {$dir} writable", is_dir(base_path($dir)) && is_writable(base_path($dir)), 'Run: mkdir -p storage/framework/{cache,sessions,views} storage/logs && chmod -R 775 storage bootstrap/cache');
        }
        $add('Uploaded images link (public/storage)', is_link(public_path('storage')) || is_dir(public_path('storage')), 'Run: php artisan storage:link');
        $add('Built frontend present', is_file(public_path('build/manifest.json')), 'Run: git pull (public/build is in the repository)');

        return $out;
    }

    private static function sameHost(): bool
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return ! app()->isProduction() || ($host && strcasecmp($host, request()->getHost()) === 0);
    }

    /** Maps a database error to a plain-language hint without echoing credentials. */
    private static function dbHint(Throwable $e): string
    {
        $m = $e->getMessage();

        return match (true) {
            str_contains($m, 'could not find driver') => 'The PHP MySQL driver is missing. Enable pdo_mysql in hPanel → PHP Configuration → PHP Extensions.',
            str_contains($m, 'Access denied') => 'Wrong database username or password. Copy DB_USERNAME and DB_PASSWORD exactly from hPanel → Databases → MySQL Databases into .env, then php artisan config:cache',
            str_contains($m, 'Unknown database') => 'The database name is wrong. Copy DB_DATABASE exactly (including the u123456789_ prefix) from hPanel, then php artisan config:cache',
            str_contains($m, 'getaddrinfo') || str_contains($m, 'Name or service not known') || str_contains($m, 'php_network_getaddresses') => 'The database host is wrong. On Hostinger use DB_HOST=localhost, then php artisan config:cache',
            str_contains($m, 'Connection refused') || str_contains($m, 'No such file or directory') => 'Cannot reach the database server. Use DB_HOST=localhost (try 127.0.0.1 if that fails) and DB_PORT=3306, then php artisan config:cache',
            str_contains($m, 'database.sqlite') || str_contains($m, 'Database file at path') => 'DB_CONNECTION is still sqlite. Set DB_CONNECTION=mysql and the DB_* values in .env, then php artisan config:cache',
            default => 'The database refused the connection. Check every DB_* value in .env against hPanel, then php artisan config:cache',
        };
    }
}
