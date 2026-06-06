<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\SearchController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/search', [SearchController::class, 'search']);

Route::get('/debug', function() {
    $certExists = file_exists('/etc/ssl/certs/ca-certificates.crt');
    $opensslVersion = openssl_version_number();
    return response()->json([
        'cert_exists' => $certExists,
        'openssl_version' => $opensslVersion,
        'php_version' => PHP_VERSION,
    ]);
});

