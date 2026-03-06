<?php

namespace Upsoftware\Svarium\Providers;

use App\Models\Page;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Upsoftware\Svarium\Auth\AuthManager;
use Upsoftware\Svarium\Bundles\Bundle;
use Upsoftware\Svarium\Bundles\BundleRegistry;
use Upsoftware\Svarium\Events\EventBus;
use Upsoftware\Svarium\Http\Middleware\AuthenticateMiddleware;
use Upsoftware\Svarium\Http\Middleware\HandleInertiaRequests;
use Upsoftware\Svarium\Http\Middleware\InitializeTenancy;
use Upsoftware\Svarium\Http\Middleware\LocaleMiddleware;
use Upsoftware\Svarium\Http\Middleware\ResolveDomainContext;
use Upsoftware\Svarium\Menu\MenuRegistry;
use Upsoftware\Svarium\Modules\ActivationRegistry;
use Upsoftware\Svarium\Modules\DependencyResolver;
use Upsoftware\Svarium\Modules\ModuleRegistry;
use Upsoftware\Svarium\Panel\BindingRegistry;
use Upsoftware\Svarium\Panel\FieldAttributesRegistry;
use Upsoftware\Svarium\Panel\OperationRegistry;
use Upsoftware\Svarium\Panel\Panel;
use Upsoftware\Svarium\Panel\PanelRegistry;
use Upsoftware\Svarium\Routing\SvariumHttpKernel;
use Upsoftware\Svarium\Services\DeviceTracking\DeviceTracking;
use Upsoftware\Svarium\Services\LayoutService;
use Upsoftware\Svarium\Tenancy\TenancyManager;
use Upsoftware\Svarium\Widgets\Widget;
use Upsoftware\Svarium\Widgets\WidgetRegistry;

class SvariumServiceProvider extends ServiceProvider
{
    /*
    |--------------------------------------------------------------------------
    | REGISTER — tylko bindy
    |--------------------------------------------------------------------------
    */
    public function register(): void
    {
        $this->app->register(SvariumPluginAggregateServiceProvider::class);

        $this->app->singleton('layout', fn () => new LayoutService);
        $this->app->singleton('device-tracking', fn () => new DeviceTracking);
        $this->app->singleton(TenancyManager::class, fn () => new TenancyManager);

        $this->app->singleton('auth-manager', fn () => (new AuthManager)->resolveHandler()
        );

        /*
        |-----------------------------
        | Module system
        |-----------------------------
        */

        $this->app->singleton(ActivationRegistry::class);
        $this->app->singleton(BundleRegistry::class);

        $this->app->singleton(EventBus::class);
        $this->app->singleton(MenuRegistry::class);
        $this->app->singleton(WidgetRegistry::class);

        $this->app->singleton(ModuleRegistry::class, function () {
            $registry = new ModuleRegistry;
            $registry->loadFromApp();
            $registry->registerPhase(); // tylko register

            return $registry;
        });

        $this->app->singleton(OperationRegistry::class);

        $this->app->singleton(PanelRegistry::class, function () {
            $registry = new PanelRegistry;

            foreach ($this->resolvePanels() as $panel) {
                $registry->register($panel);
            }

            return $registry;
        });

        $this->app->singleton(BindingRegistry::class);
        $this->app->singleton(FieldAttributesRegistry::class);

        $this->registerHelpers();
    }

    /*
    |--------------------------------------------------------------------------
    | BOOT — start systemu
    |--------------------------------------------------------------------------
    */
    public function boot(Router $router): void
    {
        $this->registerMigrateInitGuard();

        $this->registerSchemaMacros();

        /*
        |-----------------------------
        | Middleware
        |-----------------------------
        */
        $router->aliasMiddleware('auth.panel', AuthenticateMiddleware::class);

        /*
        |-----------------------------
        | Translations
        |-----------------------------
        */
        $langPath = __DIR__.'/../lang';
        $this->loadJsonTranslationsFrom($langPath);
        $this->loadTranslationsFrom($langPath, 'svarium');

        /*
        |-----------------------------
        | Publish / migrations
        |-----------------------------
        */
        $this->publishes([
            __DIR__.'/../config/upsoftware.php' => config_path('upsoftware.php'),
        ], 'upsoftware');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (config('upsoftware.tenancy.enabled', config('tenancy.enabled', false))) {
            $tenantsMigrationsPath = __DIR__.'/../database/migrations/tenants';

            if (is_dir($tenantsMigrationsPath)) {
                $this->loadMigrationsFrom($tenantsMigrationsPath);

                if ((bool) config('upsoftware.tenancy.owner.enabled', false)) {
                    $ownerMigrationsPath = __DIR__.'/../database/migrations/tenants-owner';
                    if (is_dir($ownerMigrationsPath)) {
                        $this->loadMigrationsFrom($ownerMigrationsPath);
                    }
                }

                if ((bool) config('upsoftware.tenancy.profile.enabled', true)) {
                    $profileMigrationsPath = __DIR__.'/../database/migrations/tenants-profile';
                    if (is_dir($profileMigrationsPath)) {
                        $this->loadMigrationsFrom($profileMigrationsPath);
                    }
                }
            } else {
                // Backward-compatible fallback for installations without split wrappers.
                $this->loadMigrationsFrom(__DIR__.'/../database/migrations/tenancy');
            }
        }

        /*
        |-----------------------------
        | Start Svarium kernel
        |-----------------------------
        */

        $modules = app(ModuleRegistry::class);
        $activation = app(ActivationRegistry::class);
        $resolver = app(DependencyResolver::class);
        $bundles = app(BundleRegistry::class);

        /*
        |--------------------------------------------------------------------------
        | 1. Rejestrujemy bundle z app/
        |--------------------------------------------------------------------------
        */
        $bundlePath = svarium_path('Bundles');

        if (is_dir($bundlePath)) {

            foreach (File::allFiles($bundlePath) as $file) {

                if (! str_ends_with($file->getFilename(), 'Bundle.php')) {
                    continue;
                }

                $relative = str_replace(
                    svarium_path().DIRECTORY_SEPARATOR,
                    '',
                    $file->getPathname()
                );
                $relative = str_replace(['/', '.php'], ['\\', ''], $relative);

                $class = 'App\\'.$relative;

                if (class_exists($class) && is_subclass_of($class, Bundle::class)) {
                    $bundles->register($class);
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Boot bundle (aktywuje moduły)
        |--------------------------------------------------------------------------
        */
        $bundles->boot();

        /*
        |--------------------------------------------------------------------------
        | 3. Rozwiązujemy zależności bazowe
        |--------------------------------------------------------------------------
        */
        while ($resolver->resolve($modules, $activation)) {
            // dopóki coś się aktywuje
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Budujemy routing operacji
        |--------------------------------------------------------------------------
        */
        app(OperationRegistry::class)->bootFromModules($modules);
        $this->registerWidgetsFromHook();

        /*
        |--------------------------------------------------------------------------
        | 5. Boot modułów
        |--------------------------------------------------------------------------
        */
        $modules->bootPhase();

        /*
        |-----------------------------
        | Model bindings
        |-----------------------------
        */
        app(BindingRegistry::class)->bind(
            'page',
            fn ($value) => Page::findOrFail($value)
        );

        Inertia::share([
            'flash' => fn () => session('flash'),
        ]);

        /*
        |-----------------------------
        | Default routes
        |-----------------------------
        */
        Route::middleware(['web'])
            ->namespace('Upsoftware\Svarium\Http\Controllers')
            ->group(__DIR__.'/../routes/web.php');

        /*
        |-----------------------------
        | Catch-all router Svarium (must be last)
        |-----------------------------
        */
        $this->app->booted(function (): void {
            if (! $this->routePathExists('login', ['GET', 'HEAD'])) {
                Route::get('/login', function () {
                    return redirect()->to(svarium_login_url(false));
                })->name('login');
            }

            $fallbackMiddleware = ['web'];

            $fallbackMiddleware[] = InitializeTenancy::class;
            $fallbackMiddleware[] = LocaleMiddleware::class;
            $fallbackMiddleware[] = ResolveDomainContext::class;
            $fallbackMiddleware[] = HandleInertiaRequests::class;

            Route::middleware($fallbackMiddleware)->group(function (): void {
                Route::any('{path?}', SvariumHttpKernel::class)
                    ->where('path', '.*');
            });
        });

        /*
        |-----------------------------
        | Console
        |-----------------------------
        */
        $this->consoleCommands();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers autoload
    |--------------------------------------------------------------------------
    */
    protected function registerHelpers(): void
    {
        require_once __DIR__.'/../Helpers/index.php';

        if (! File::exists(svarium_resources())) {
            return;
        }

        foreach (File::directories(svarium_resources()) as $dir) {

            $helperDir = $dir.DIRECTORY_SEPARATOR.'Helpers';

            if (! File::isDirectory($helperDir)) {
                continue;
            }

            foreach (File::files($helperDir) as $file) {
                if ($file->getExtension() === 'php') {
                    require_once $file->getRealPath();
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Console commands auto-discovery
    |--------------------------------------------------------------------------
    */
    protected function discoverCommands(string $path, string $namespace): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $classes = [];
        $exclude = ['CoreCommand'];

        foreach (File::allFiles($path) as $file) {
            $className = $file->getFilenameWithoutExtension();

            if (in_array($className, $exclude)) {
                continue;
            }

            $relative = str_replace(
                [DIRECTORY_SEPARATOR, '.php'],
                ['\\', ''],
                $file->getRelativePathname()
            );

            $class = trim($namespace, '\\').'\\'.$relative;

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            if ($reflection->isInstantiable() && $reflection->isSubclassOf(Command::class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    protected function registerMigrateInitGuard(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->app['events']->listen(CommandStarting::class, function (CommandStarting $event): void {
            if (! $this->shouldGuardMigrateCommand($event->command)) {
                return;
            }

            if ($this->isMigrateBypassEnabled() || $this->isSvariumInitialized()) {
                return;
            }

            throw new ConsoleRuntimeException(
                'Svarium nie jest zainicjalizowany. Najpierw uruchom: php artisan svarium:app.init'
            );
        });
    }

    protected function registerWidgetsFromHook(): void
    {
        $widgetsPath = svarium_path('widgets.php');

        if (! is_file($widgetsPath)) {
            return;
        }

        $definitions = require $widgetsPath;

        if ($definitions instanceof \Closure) {
            $definitions = $definitions();
        }

        if ($definitions === null || $definitions === 1) {
            return;
        }

        if ($definitions instanceof Widget) {
            $definitions = [$definitions];
        }

        if (! is_array($definitions)) {
            return;
        }

        app(WidgetRegistry::class)->register($definitions, [
            'source' => $widgetsPath,
        ]);
    }

    protected function shouldGuardMigrateCommand(?string $command): bool
    {
        $name = strtolower(trim((string) $command));

        return in_array($name, ['migrate', 'migrate:fresh', 'migrate:refresh'], true);
    }

    protected function isMigrateBypassEnabled(): bool
    {
        $value = strtolower(trim((string) env('SVARIUM_ALLOW_MIGRATE', '')));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    protected function isSvariumInitialized(): bool
    {
        $marker = strtolower(trim((string) env('SVARIUM', '')));
        if ($marker === 'enabled') {
            return true;
        }

        $legacy = strtolower(trim((string) env('SVARIUM_ENABLED', '')));
        if (in_array($legacy, ['1', 'true', 'yes', 'on', 'enabled'], true)) {
            return true;
        }

        $fileMarker = strtolower(trim((string) $this->readEnvValueFromFile('SVARIUM')));
        if ($fileMarker === 'enabled') {
            return true;
        }

        $fileLegacy = strtolower(trim((string) $this->readEnvValueFromFile('SVARIUM_ENABLED')));

        return in_array($fileLegacy, ['1', 'true', 'yes', 'on', 'enabled'], true);
    }

    protected function readEnvValueFromFile(string $key): ?string
    {
        $envPath = base_path('.env');
        if (! is_file($envPath)) {
            return null;
        }

        $content = file_get_contents($envPath);
        if (! is_string($content) || $content === '') {
            return null;
        }

        $pattern = '/^'.preg_quote($key, '/').'\s*=\s*(.*)$/mi';
        if (preg_match($pattern, $content, $matches) !== 1) {
            return null;
        }

        $value = trim((string) ($matches[1] ?? ''));
        if ($value === '') {
            return '';
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            return trim(substr($value, 1, -1));
        }

        $value = preg_replace('/\s+#.*$/', '', $value);

        return trim((string) $value);
    }

    protected function routePathExists(string $path, array $methods = ['GET']): bool
    {
        $normalizedPath = trim($path, '/');
        $routesByMethod = app('router')->getRoutes()->getRoutesByMethod();

        foreach ($methods as $method) {
            $methodRoutes = $routesByMethod[strtoupper($method)] ?? [];

            foreach ($methodRoutes as $route) {
                if (trim((string) $route->uri(), '/') === $normalizedPath) {
                    return true;
                }
            }
        }

        return false;
    }

    public function consoleCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $commands = $this->discoverCommands(
            __DIR__.'/../Console/Commands',
            'Upsoftware\\Svarium\\Console\\Commands'
        );

        $this->commands($commands);
    }

    /**
     * Resolve panel definitions from app/Svarium/panels.php.
     * Falls back to a safe default panel when the file is missing or invalid.
     *
     * @return list<Panel>
     */
    protected function resolvePanels(): array
    {
        $file = base_path('app/Svarium/panels.php');
        $resolved = [];

        if (is_file($file)) {
            $panels = require $file;

            if (is_array($panels)) {
                foreach ($panels as $panel) {
                    if ($panel instanceof Panel) {
                        $resolved[] = $panel;
                    }
                }
            }
        }

        if ($resolved !== []) {
            return $resolved;
        }

        return [$this->defaultPanel()];
    }

    protected function defaultPanel(): Panel
    {
        $name = trim((string) config('upsoftware.panel.name', 'admin'));
        if ($name === '') {
            $name = 'admin';
        }

        $configuredPrefix = config('upsoftware.panel.prefix');
        $prefix = is_string($configuredPrefix)
            ? trim($configuredPrefix, '/')
            : '';

        if ($prefix === '') {
            return Panel::make($name)->noPrefix();
        }

        return Panel::make($name)->prefix($prefix);
    }

    protected function registerSchemaMacros(): void
    {
        if (! Blueprint::hasMacro('tenant_id')) {
            Blueprint::macro('tenant_id', function (
                string $column = 'tenant_id',
                string $tenantTable = 'tenants',
                string $tenantKey = 'id'
            ) {
                /** @var Blueprint $this */
                $definition = $this->string($column);

                $this->foreign($column)
                    ->references($tenantKey)
                    ->on($tenantTable)
                    ->cascadeOnDelete();

                return $definition;
            });
        }
    }
}
