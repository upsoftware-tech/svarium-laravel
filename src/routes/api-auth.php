<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Upsoftware\Svarium\Http\Controllers\ApiAuthLoginController;
use Upsoftware\Svarium\Http\Controllers\ApiAuthOtpSendController;
use Upsoftware\Svarium\Http\Controllers\ApiAuthOtpVerifyController;

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
