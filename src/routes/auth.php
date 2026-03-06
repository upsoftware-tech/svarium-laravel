<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Upsoftware\Svarium\Http\Middleware\LocaleMiddleware;
use Upsoftware\Svarium\Http\Middleware\HandleInertiaRequests;
use Upsoftware\Svarium\Http\Middleware\InitializeTenancy;
use Upsoftware\Svarium\Http\Middleware\ResolveDomainContext;
use Upsoftware\Svarium\Routing\SvariumHttpKernel;

$middleware = ['web'];
$middleware[] = InitializeTenancy::class;
$middleware[] = LocaleMiddleware::class;
$middleware[] = ResolveDomainContext::class;
$middleware[] = HandleInertiaRequests::class;

Route::prefix(config('upsoftware.panel.prefix'))->as('panel.')->middleware('auth.panel')->group(function() use ($middleware) {
    Route::prefix('auth')->as('auth.')->middleware($middleware)->group(function() {
        Route::match(['get', 'post'], 'login', function (Request $request) {
            return app(SvariumHttpKernel::class)($request);
        })->name('login');


        Route::prefix('{type}')->group(function() {
            Route::match(['get', 'post'], 'method/{userAuth}', function (Request $request) {
                return app(SvariumHttpKernel::class)($request);
            })->name('method');

            Route::prefix('verification')->group(function() {
                Route::prefix('{userAuth}')->group(function() {
                    Route::get('/', function (Request $request) {
                        return app(SvariumHttpKernel::class)($request);
                    })->name('verification');

                    Route::post('/', function (Request $request) {
                        return app(SvariumHttpKernel::class)($request);
                    })->name('verification.set');

                    Route::get('/resend', function (Request $request) {
                        return app(SvariumHttpKernel::class)($request);
                    })->name('verification.resend');
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
