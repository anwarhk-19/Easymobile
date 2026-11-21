<?php

use App\Http\Controllers\InstallController;
use App\Support\HealthCheck;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// The SPA uses bearer tokens, not sessions or cookies, so these pages skip the cookie/session/CSRF
// middleware. They keep working when the database (session storage) is down — and even before
// setup, when no APP_KEY exists yet to encrypt cookies.
$stateless = [
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
];

// First-time setup in the browser (no SSH needed). Locks itself once installed.
Route::get('/install', [InstallController::class, 'show'])->withoutMiddleware($stateless);
Route::post('/install', [InstallController::class, 'store'])->withoutMiddleware($stateless);

// Server status page: plain HTML, independent of the API, so it works even when the API is failing.
// Shows pass/fail and fix hints only — never credentials, paths or raw errors. Disable with STATUS_PAGE=false.
Route::get('/status', function () {
    abort_unless(config('app.status_page'), 404);
    $head = @file_get_contents(base_path('.git/HEAD'));
    $commit = $head && str_starts_with($head, 'ref: ') ? @file_get_contents(base_path('.git/'.trim(substr($head, 5)))) : $head;

    return response()->view('status', ['checks' => HealthCheck::run(), 'version' => $commit ? substr(trim($commit), 0, 7) : 'unknown', 'installed' => InstallController::isInstalled()])
        ->header('Cache-Control', 'no-store');
})->withoutMiddleware($stateless);

// Single-page app: every other non-API route renders the React shell.
Route::view('/{any?}', 'app')->where('any', '^(?!api|storage|up|build|status$|install$).*$')->withoutMiddleware($stateless);
