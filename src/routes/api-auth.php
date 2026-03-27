<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Upsoftware\Svarium\Http\Controllers\ApiAuthLoginController;
use Upsoftware\Svarium\Http\Controllers\ApiAuthOtpSendController;
use Upsoftware\Svarium\Http\Controllers\ApiAuthOtpVerifyController;
use Upsoftware\Svarium\Http\Controllers\ApiAuthTenantSelectController;
use Upsoftware\Svarium\Http\Controllers\ApiAuthUserController;

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

$otpSendPath = trim(implode('/', array_filter([$apiPrefix, 'auth/otp/{userAuth}/send'])), '/');
if ($otpSendPath === '') {
    $otpSendPath = 'auth/otp/{userAuth}/send';
}

Route::post($otpSendPath, ApiAuthOtpSendController::class)
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('svarium.api.auth.otp.send');

$otpVerifyPath = trim(implode('/', array_filter([$apiPrefix, 'auth/otp/{userAuth}/verify'])), '/');
if ($otpVerifyPath === '') {
    $otpVerifyPath = 'auth/otp/{userAuth}/verify';
}

Route::post($otpVerifyPath, ApiAuthOtpVerifyController::class)
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('svarium.api.auth.otp.verify');

$tenantSelectPath = trim(implode('/', array_filter([$apiPrefix, 'auth/tenant'])), '/');
if ($tenantSelectPath === '') {
    $tenantSelectPath = 'auth/tenant';
}

Route::middleware((array) config('upsoftware.api.auth.middleware', ['auth:sanctum']))
    ->post($tenantSelectPath, ApiAuthTenantSelectController::class)
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('svarium.api.auth.tenant');

$tenantSelectAliasPath = trim(implode('/', array_filter([$apiPrefix, 'auth/tenant/select'])), '/');
if ($tenantSelectAliasPath === '') {
    $tenantSelectAliasPath = 'auth/tenant/select';
}

Route::middleware((array) config('upsoftware.api.auth.middleware', ['auth:sanctum']))
    ->post($tenantSelectAliasPath, ApiAuthTenantSelectController::class)
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('svarium.api.auth.tenant.select');

$userPath = trim(implode('/', array_filter([$apiPrefix, 'auth/user'])), '/');
if ($userPath === '') {
    $userPath = 'auth/user';
}

Route::middleware((array) config('upsoftware.api.auth.middleware', ['auth:sanctum']))
    ->get($userPath, ApiAuthUserController::class)
    ->name('svarium.api.auth.user');
