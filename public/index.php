<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Shared hosting (e.g. Hostinger): when the whole project sits in public_html — or in a sub-folder
// such as public_html/easymobile — the root .htaccess rewrites requests into public/. The server then
// reports SCRIPT_NAME as ".../public/index.php", which doesn't match the public URL. Present the script
// as living in the install folder so Laravel computes the correct base path for routes and assets.
$script = $_SERVER['SCRIPT_NAME'] ?? '';
if (str_ends_with($script, '/public/index.php')) {
    $installDir = substr($script, 0, -strlen('/public/index.php'));
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (! str_starts_with($uri, $installDir.'/public/')) {
        $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $installDir.'/index.php';
    }
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
