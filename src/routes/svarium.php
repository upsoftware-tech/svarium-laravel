<?php

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Upsoftware\Svarium\Http\Middleware\LocaleMiddleware;
use Upsoftware\Svarium\Http\Middleware\HandleInertiaRequests;
use Upsoftware\Svarium\Routing\SvariumHttpKernel;

$middleware = ['web'];
$middleware[] = LocaleMiddleware::class;
$middleware[] = HandleInertiaRequests::class;

if (config('tenancy.enabled', false)) {
    $middleware[] = InitializeTenancyByDomain::class;
    $middleware[] = PreventAccessFromCentralDomains::class;
}

$resourceDir = svarium_resources();
if (File::exists($resourceDir)) {
    $directories = File::directories($resourceDir);
    foreach ($directories as $path) {
        $resourceName = basename($path);

        $className = "App\\Svarium\\Resources\\{$resourceName}\\{$resourceName}Resource";

        if (class_exists($className)) {
            $routeName = $className::getRouteName();
            $pages = $className::getPages();

            foreach ($pages as $key => $page) {
                $pageClass = $page["className"];
                $pageArea = $page["area"];
                $routePath = $page["route"];
                $method = $pageClass::getMethod();
                $routePageName = $pageClass::getRouteName();
                $action = $pageClass::getAction();
                $whereIn = $page["routeWhereIn"] ?? [];

                $route = [config('upsoftware.panel.prefix'), $routeName, $url = ($key === 'index') ? '' : $routePageName];
                $route_path = implode('/', $route);
                if (str_starts_with($route_path, '/')) {
                    $route_path = substr($route_path, 1);
                }
                $route_name = 'svarium.' . strtr($route_path, ['/' => '.']);
                $addMiddleware = [];
                if ($pageArea === "Panel") {
                    $addMiddleware[] = "auth";
                }
                $pageMiddleware = array_merge($middleware, $addMiddleware);
                if ($routePath) {
                    $route_path = $routePath;
                }

                Route::$method($route_path, [$pageClass, $action])
                    ->middleware($pageMiddleware)
                    ->name($route_name)
                    ->when(!empty($whereIn), function ($route) use ($whereIn) {
                        if (!Arr::isAssoc($whereIn) && is_array(Arr::first($whereIn))) {
                            $whereIn = Arr::first($whereIn);
                        }

                        foreach ($whereIn as $parameter => $values) {
                            if (is_string($parameter)) {
                                $route->whereIn($parameter, (array) $values);
                            }
                        }

                        return $route;
                    });
            }
        }
    }
}


