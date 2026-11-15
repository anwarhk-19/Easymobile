<?php

use Illuminate\Support\Facades\Route;

// Single-page app: every non-API route renders the React shell.
Route::view('/{any?}', 'app')->where('any', '^(?!api|storage|up|build).*$');
