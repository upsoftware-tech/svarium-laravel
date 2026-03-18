<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Upsoftware\Svarium\Http\Controllers\ApiAuthLoginController;

if (! (bool) config('upsoftware.api.enabled', true)) {
    return;
}

$apiPrefix = trim((string) config('upsoftware.api.prefix', 'api/v1'), '/');
$loginPath = trim(implode('/', array_filter([$apiPrefix, 'auth/login'])), '/');

if ($loginPath === '') {
    $loginPath = 'auth/login';
}

Route::post($loginPath, ApiAuthLoginController::class)
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('svarium.api.auth.login');

