<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\SearchController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/search', [SearchController::class, 'search']);

Route::get('/debug', function() {
    return response()->json([
        'cert_exists' => file_exists('/etc/ssl/certs/ca-certificates.crt'),
        'openssl_loaded' => extension_loaded('openssl'),
        'php_version' => PHP_VERSION,
    ]);
});

