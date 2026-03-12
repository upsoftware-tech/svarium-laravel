<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Upsoftware\Svarium\Http\Middleware\LocaleMiddleware;
use Upsoftware\Svarium\Http\Middleware\HandleInertiaRequests;
use Upsoftware\Svarium\Http\Middleware\InitializeTenancy;
use Upsoftware\Svarium\Http\Middleware\ResolveDomainContext;
use Upsoftware\Svarium\Panel\Panel;
use Upsoftware\Svarium\Panel\PanelRegistry;
use Upsoftware\Svarium\Routing\SvariumHttpKernel;

$middleware = ['web'];
$middleware[] = InitializeTenancy::class;
$middleware[] = LocaleMiddleware::class;
$middleware[] = ResolveDomainContext::class;
$middleware[] = HandleInertiaRequests::class;

$registerAuthRoutes = static function (?string $panelPrefix, string $authRoutePrefix) use ($middleware): void {
    $normalizedPanelPrefix = trim((string) $panelPrefix, '/');
    $normalizedAuthPrefix = trim($authRoutePrefix, '.');

    if ($normalizedAuthPrefix === '') {
        return;
    }

    Route::prefix($normalizedPanelPrefix)
        ->middleware('auth.panel')
        ->group(function () use ($middleware, $normalizedAuthPrefix): void {
            Route::prefix('auth')->as($normalizedAuthPrefix.'.')->middleware($middleware)->group(function (): void {
                Route::match(['get', 'post'], 'login', function (Request $request) {
                    return app(SvariumHttpKernel::class)($request);
                })->name('login');

                Route::prefix('{type}')->group(function (): void {
                    Route::match(['get', 'post'], 'method/{userAuth}', function (Request $request) {
                        return app(SvariumHttpKernel::class)($request);
                    })->name('method');

                    Route::prefix('verification')->group(function (): void {
                        Route::prefix('{userAuth}')->group(function (): void {
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

                Route::prefix('reset')->group(function (): void {
                    Route::get('/', 'ResetController@init')->name('reset');
                    Route::post('/', 'ResetController@reset')->name('reset.set');

                    Route::prefix('password/{userAuth}')->group(function (): void {
                        Route::get('/', 'ResetPasswordController@init')->name('reset.password');
                        Route::post('/', 'ResetPasswordController@reset')->name('reset.password.set');
                    });
                });

                Route::prefix('{provider}')->group(function (): void {
                    Route::get('/redirect', 'SocialiteController@redirect')->name('redirect');
                    Route::post('/callback', 'SocialiteController@callback')->name('callback');
                })->where(['social' => ['google|facebook|apple|microsoft|facebook|linkedin|zoom']]);

                Route::prefix('register')->group(function (): void {
                    Route::get('/', 'RegisterController@init')->name('register');
                    Route::post('/', 'RegisterController@register')->name('register.set');
                });

                Route::get('logout', LogoutController::class)->middleware('auth')->name('logout');
            });
        });
};

$panels = array_values(array_filter(
    app(PanelRegistry::class)->all(),
    fn ($candidate) => $candidate instanceof Panel
));

if ((bool) config('upsoftware.panel.auth.per_panel', true) && $panels !== []) {
    $defaultPanelName = svarium_default_panel_name();

    foreach ($panels as $panel) {
        $routePrefixes = ['panel.'.$panel->name.'.auth'];

        if ((string) $panel->name === (string) $defaultPanelName) {
            foreach (svarium_auth_compat_route_prefixes() as $prefix) {
                $routePrefixes[] = $prefix;
            }
        }

        foreach (array_values(array_unique($routePrefixes)) as $routePrefix) {
            $registerAuthRoutes($panel->prefix, $routePrefix);
        }
    }
} else {
    $legacyPrefix = trim((string) config('upsoftware.panel.route_prefix', 'panel.auth'), '.');
    if ($legacyPrefix === '') {
        $legacyPrefix = 'panel.auth';
    }

    $panelPrefix = trim((string) config('upsoftware.panel.prefix', ''), '/');

    $routePrefixes = [$legacyPrefix, ...svarium_auth_compat_route_prefixes()];

    foreach (array_values(array_unique($routePrefixes)) as $routePrefix) {
        $registerAuthRoutes($panelPrefix, $routePrefix);
    }
}

Route::get('locale/{locale}', LocaleController::class)->name('locale');
