<?php

use Illuminate\Support\Facades\Route;
use Upsoftware\Svarium\Http\Middleware\LocaleMiddleware;
use Upsoftware\Svarium\Http\Middleware\HandleInertiaRequests;
use Upsoftware\Svarium\Http\Middleware\InitializeTenancy;

$middleware = ['web'];
$middleware[] = LocaleMiddleware::class;
$middleware[] = HandleInertiaRequests::class;

if (config('upsoftware.tenancy.enabled', config('tenancy.enabled', false))) {
    $middleware[] = InitializeTenancy::class;
}

Route::prefix(config('upsoftware.panel.prefix'))->as('panel.')->middleware('auth.panel')->group(function() use ($middleware) {
    Route::prefix('auth')->as('auth.')->middleware($middleware)->group(function() {
        Route::prefix('login')->group(function() {
            Route::get('/', 'LoginController@init')->name('login');
            Route::post('/', 'LoginController@login')->name('login');
        });

        Route::prefix('{type}')->group(function() {
            Route::prefix('method')->group(function() {
                Route::prefix('{userAuth}')->group(function() {
                    Route::get('/', 'MethodController@init')->name('method');
                    Route::post('/', 'MethodController@set')->name('method.set');
                });
            });

            Route::prefix('verification')->group(function() {
                Route::prefix('{userAuth}')->group(function() {
                    Route::get('/', 'VerificationController@init')->name('verification');
                    Route::post('/', 'VerificationController@set')->name('verification.set');
                });
            });
        });

        Route::prefix('reset')->group(function() {
            Route::get('/', 'ResetController@init')->name('reset');
            Route::post('/', 'ResetController@reset')->name('reset.set');

            Route::prefix('password/{userAuth}')->group(function() {
                Route::get('/', 'ResetPasswordController@init')->name('reset.password');
                Route::post('/', 'ResetPasswordController@reset')->name('reset.password.set');
            });
        });

        Route::prefix('{provider}')->group(function() {
            Route::get('/redirect', 'SocialiteController@redirect')->name('redirect');
            Route::post('/callback', 'SocialiteController@callback')->name('callback');
        })->where(['social' => ['google|facebook|apple|microsoft|facebook|linkedin|zoom']]);

        Route::prefix('register')->group(function() {
            Route::get('/', 'RegisterController@init')->name('register');
            Route::post('/', 'RegisterController@register')->name('register.set');
        });

        Route::get('logout', LogoutController::class)->middleware('auth')->name('logout');
    });
});

Route::get('locale/{locale}', LocaleController::class)->name('locale');

Route::get('/login', function() {
    return redirect()->route('panel.auth.login');
})->name('login');
