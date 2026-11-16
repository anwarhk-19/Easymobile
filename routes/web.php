<?php

use App\Support\HealthCheck;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Server status page: plain HTML, independent of the API, so it works even when the API is failing.
// Shows pass/fail and fix hints only — never credentials, paths or raw errors. Disable with STATUS_PAGE=false.
// The SPA uses bearer tokens, not sessions, so pages and /status skip the session/CSRF middleware —
// they keep working even when the database (session storage) is unavailable.
$stateless = [
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
];

Route::get('/status', function () {
    abort_unless(config('app.status_page'), 404);
    $head = @file_get_contents(base_path('.git/HEAD'));
    $commit = $head && str_starts_with($head, 'ref: ') ? @file_get_contents(base_path('.git/'.trim(substr($head, 5)))) : $head;

    return response()->view('status', ['checks' => HealthCheck::run(), 'version' => $commit ? substr(trim($commit), 0, 7) : 'unknown'])
        ->header('Cache-Control', 'no-store');
})->withoutMiddleware($stateless);

// Single-page app: every other non-API route renders the React shell.
Route::view('/{any?}', 'app')->where('any', '^(?!api|storage|up|build|status$).*$')->withoutMiddleware($stateless);
