<?php

namespace Upsoftware\Svarium\Panel;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Upsoftware\Svarium\Http\Middleware\HandleInertiaRequests;
use Upsoftware\Svarium\Http\Middleware\InitializeTenancy;
use Upsoftware\Svarium\Http\Middleware\LocaleMiddleware;
use Upsoftware\Svarium\Http\Middleware\ResolveDomainContext;
use Upsoftware\Svarium\Menu\MenuRegistry;
use Upsoftware\Svarium\Modules\ActivationRegistry;
use Upsoftware\Svarium\Modules\ModuleRegistry;
use Upsoftware\Svarium\Routing\SvariumHttpKernel;
use Upsoftware\Svarium\Widgets\WidgetRegistry;

class OperationRegistry
{
    protected const API_PANEL = '__api';

    protected array $routes = [];

    public function register(string $panel, array $methods, string $uri, string $operation, array $meta = []): void
    {

        [$pattern, $names] = $this->compile($uri);

        foreach ($methods as $method) {

            $this->routes[$panel][strtoupper($method)][] = [
                'operation' => $operation,
                'pattern' => $pattern,
                'names' => $names,
                'meta' => $meta,
            ];
        }
    }

    public function resolve(string $panel, string $method, string $uri): ?array
    {
        foreach ($this->routes[$panel][$method] ?? [] as $route) {

            if (preg_match($route['pattern'], $uri, $matches)) {

                array_shift($matches);

                $params = array_combine($route['names'], $matches);

                return [
                    'operation' => $route['operation'],
                    'params' => $params,
                    'meta' => $route['meta'] ?? [],
                ];
            }
        }

        return null;
    }

    public function definitions(): array
    {
        $definitions = [];

        foreach ($this->routes as $panel => $methods) {
            foreach ($methods as $method => $routes) {
                foreach ($routes as $route) {
                    $key = sha1(json_encode([
                        'panel' => $panel,
                        'operation' => $route['operation'] ?? null,
                        'pattern' => $route['pattern'] ?? null,
                        'meta' => $route['meta'] ?? [],
                    ]));

                    if (! isset($definitions[$key])) {
                        $definitions[$key] = [
                            'panel' => $panel,
                            'methods' => [],
                            ...$route,
                        ];
                    }

                    $definitions[$key]['methods'][$method] = $method;
                }
            }
        }

        return array_map(static function (array $definition): array {
            $definition['methods'] = array_values($definition['methods']);

            return $definition;
        }, array_values($definitions));
    }

    protected function compile(string $uri): array
    {
        preg_match_all('/\{([^}]+)\}/', $uri, $paramNames);

        $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $uri);
        $pattern = '#^'.$pattern.'$#';

        return [$pattern, $paramNames[1]];
    }

    public function bootFromModules(ModuleRegistry $modules): void
    {
        $activation = app(ActivationRegistry::class);

        foreach ($modules->all() as $module) {

            $moduleClass = get_class($module);
            if (! $activation->isEnabled($moduleClass)) {
                continue;
            }

            $this->registerOperationsFromPath($module->path('Panel'));
        }

        // Preferred path for non-module operations.
        $this->registerOperationsFromPath(svarium_path('Operations'));
        // Backward compatible path for existing projects.
        $this->registerOperationsFromPath(svarium_path('Panel/Operations'));
        // Built-in operations provided by the package.
        $this->registerOperationsFromPath(__DIR__.'/Operations');
    }

    public function bootFromModule(\Upsoftware\Svarium\Modules\Module $module): void
    {
        $activation = app(ActivationRegistry::class);
        $moduleClass = get_class($module);

        if (! $activation->isEnabled($moduleClass)) {
            return;
        }

        $this->registerOperationsFromPath($module->path('Panel'));
    }

    protected function registerOperationsFromPath(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (File::allFiles($path) as $file) {
            $class = $this->classFromFile($file->getPathname());

            if (! class_exists($class) || ! is_subclass_of($class, Operation::class)) {
                continue;
            }

            if (! method_exists($class, 'uri')) {
                continue;
            }

            $uri = (string) $class::uri();

            $methods = method_exists($class, 'methods')
                ? (array) $class::methods()
                : ['GET'];

            $panels = $this->resolvePanelsForOperation($class);

            foreach ($panels as $panel) {
                $this->register(
                    $panel,
                    $methods,
                    $uri,
                    $class
                );

                $this->registerOperationRouteAliases($class, $panel, $uri, $methods);
            }

            $this->registerApiOperation($class, $uri, $methods);

            $menu = method_exists($class, 'menu')
                ? (array) $class::menu()
                : [];

            if ($menu !== []) {
                app(MenuRegistry::class)->register($menu, [
                    'source' => $class,
                ]);
            }

            $widgets = method_exists($class, 'widgets')
                ? (array) $class::widgets()
                : [];

            if ($widgets !== []) {
                app(WidgetRegistry::class)->register($widgets, [
                    'source' => $class,
                    'contexts' => $this->widgetContextsFromUri($uri),
                ]);
            }
        }
    }

    /**
     * Register Laravel named route aliases for module operations.
     *
     * Examples:
     * - module:ksef.documents
     * - module:ksef.documents.get
     * - module:ksef.documents.post
     */
    protected function registerOperationRouteAliases(
        string $operationClass,
        string $panel,
        string $uri,
        array $methods
    ): void {
        $module = $this->resolveModuleKeyFromOperationClass($operationClass);
        if ($module === null) {
            return;
        }

        $suffix = $this->resolveOperationRouteSuffix($operationClass, $uri, $module);
        if ($suffix === null) {
            return;
        }

        $baseName = "module:{$module}";
        if ($suffix !== 'index') {
            $baseName .= ".{$suffix}";
        }

        $panelBaseName = "module:{$panel}.{$module}";
        if ($suffix !== 'index') {
            $panelBaseName .= ".{$suffix}";
        }

        $normalizedMethods = $this->normalizeHttpMethods($methods);
        $routeUri = $this->panelPrefixedUri($panel, $uri);
        $middleware = $this->defaultAliasRouteMiddleware();

        $routeNames = [$baseName, $panelBaseName];

        foreach ($normalizedMethods as $method) {
            $routeNames[] = $baseName.'.'.strtolower($method);
            $routeNames[] = $panelBaseName.'.'.strtolower($method);

            $actionAlias = $this->httpMethodActionAlias($method);
            if ($actionAlias !== null) {
                $routeNames[] = $baseName.'.'.$actionAlias;
                $routeNames[] = $panelBaseName.'.'.$actionAlias;
            }
        }

        foreach (array_values(array_unique($routeNames)) as $routeName) {
            if (Route::has($routeName)) {
                continue;
            }

            Route::middleware($middleware)
                ->match($normalizedMethods, $routeUri, SvariumHttpKernel::class)
                ->name($routeName);
        }
    }

    protected function resolveModuleKeyFromOperationClass(string $operationClass): ?string
    {
        if (preg_match('/\\\\Modules\\\\Builtin\\\\([^\\\\]+)\\\\/i', $operationClass, $matches) === 1) {
            return (string) Str::of((string) ($matches[1] ?? ''))
                ->snake()
                ->toString();
        }

        if (preg_match('/\\\\Modules\\\\([^\\\\]+)\\\\/i', $operationClass, $matches) === 1) {
            return (string) Str::of((string) ($matches[1] ?? ''))
                ->snake()
                ->toString();
        }

        return null;
    }

    protected function resolveOperationRouteSuffix(string $operationClass, string $uri, string $moduleKey): ?string
    {
        $custom = method_exists($operationClass, 'routeName')
            ? trim((string) $operationClass::routeName())
            : '';

        if ($custom !== '') {
            return $this->normalizeRouteSuffix($custom);
        }

        $normalizedUri = trim($uri, '/');
        if ($normalizedUri === '') {
            return 'index';
        }

        $segments = array_values(array_filter(
            explode('/', $normalizedUri),
            static fn (string $segment): bool => trim($segment) !== ''
        ));

        $moduleSlug = (string) Str::of($moduleKey)->replace('_', '-')->toString();
        $moduleCompact = (string) Str::of($moduleKey)->replace('_', '')->toString();

        if (($segments[0] ?? null) !== null) {
            $first = Str::lower((string) ($segments[0] ?? ''));
            $candidates = array_unique(array_filter([
                Str::lower($moduleKey),
                Str::lower($moduleSlug),
                Str::lower($moduleCompact),
                Str::lower((string) Str::plural($moduleKey)),
                Str::lower((string) Str::plural($moduleSlug)),
                Str::lower((string) Str::plural($moduleCompact)),
            ]));

            if (in_array($first, $candidates, true)) {
                array_shift($segments);
            }
        }

        if ($segments === []) {
            return 'index';
        }

        $normalizedSegments = [];
        foreach ($segments as $segment) {
            if (preg_match('/^\{.+\}$/', $segment) === 1) {
                continue;
            }

            $normalizedSegments[] = (string) Str::of($segment)
                ->replace('-', '_')
                ->snake()
                ->toString();
        }

        if ($normalizedSegments === []) {
            return 'index';
        }

        return $this->normalizeRouteSuffix(implode('.', $normalizedSegments));
    }

    protected function normalizeRouteSuffix(string $suffix): ?string
    {
        $parts = array_values(array_filter(array_map(
            static fn (string $part): string => trim((string) Str::of($part)->replace('-', '_')->snake()->toString()),
            explode('.', trim($suffix, '.'))
        ), static fn (string $part): bool => $part !== ''));

        if ($parts === []) {
            return null;
        }

        return implode('.', $parts);
    }

    /**
     * @param array<int, mixed> $methods
     * @return array<int, string>
     */
    protected function normalizeHttpMethods(array $methods): array
    {
        $resolved = [];

        foreach ($methods as $method) {
            if (! is_string($method)) {
                continue;
            }

            $normalized = strtoupper(trim($method));
            if ($normalized === '') {
                continue;
            }

            $resolved[] = $normalized;
        }

        if ($resolved === []) {
            $resolved[] = 'GET';
        }

        if (in_array('GET', $resolved, true) && ! in_array('HEAD', $resolved, true)) {
            $resolved[] = 'HEAD';
        }

        return array_values(array_unique($resolved));
    }

    protected function httpMethodActionAlias(string $method): ?string
    {
        return match (strtoupper(trim($method))) {
            'GET' => 'index',
            'POST' => 'store',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => null,
        };
    }

    protected function panelPrefixedUri(string $panelName, string $uri): string
    {
        if ($panelName === self::API_PANEL) {
            $trimmedUri = trim($uri, '/');

            return $trimmedUri === '' ? '/' : $trimmedUri;
        }

        $panel = app(PanelRegistry::class)->get($panelName);
        $prefix = trim((string) ($panel?->prefix ?? ''), '/');
        $trimmedUri = trim($uri, '/');

        $combined = trim(implode('/', array_filter([$prefix, $trimmedUri])), '/');

        return $combined === '' ? '/' : $combined;
    }

    /**
     * @return array<int, string>
     */
    protected function defaultAliasRouteMiddleware(): array
    {
        return [
            'web',
            InitializeTenancy::class,
            LocaleMiddleware::class,
            ResolveDomainContext::class,
            HandleInertiaRequests::class,
        ];
    }

    protected function registerApiOperation(string $operationClass, string $uri, array $methods): void
    {
        if (! (bool) config('upsoftware.api.enabled', true)) {
            return;
        }

        $api = method_exists($operationClass, 'api')
            ? $operationClass::api()
            : false;

        if ($api === false || $api === null) {
            return;
        }

        $apiConfig = is_array($api) ? $api : [];
        if (array_key_exists('enabled', $apiConfig) && ! (bool) $apiConfig['enabled']) {
            return;
        }

        $apiPrefix = trim((string) config('upsoftware.api.prefix', 'api/v1'), '/');
        $uriSource = trim((string) ($apiConfig['uri'] ?? $uri), '/');
        $prependPrefix = array_key_exists('prefix', $apiConfig) ? (bool) $apiConfig['prefix'] : true;

        if ($prependPrefix && $apiPrefix !== '') {
            if ($uriSource === '') {
                $apiUri = $apiPrefix;
            } elseif ($uriSource === $apiPrefix || str_starts_with($uriSource, $apiPrefix.'/')) {
                $apiUri = $uriSource;
            } else {
                $apiUri = trim($apiPrefix.'/'.$uriSource, '/');
            }
        } else {
            $apiUri = $uriSource;
        }

        $apiMethods = array_key_exists('methods', $apiConfig) && is_array($apiConfig['methods'])
            ? $apiConfig['methods']
            : $methods;
        $apiMethods = $this->normalizeHttpMethods($apiMethods);

        $apiMiddleware = array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            (array) ($apiConfig['middleware'] ?? config('upsoftware.api.auth.middleware', []))
        ), static fn (string $value): bool => $value !== ''));

        $this->register(
            self::API_PANEL,
            $apiMethods,
            $apiUri,
            $operationClass,
            [
                'api' => true,
                'uri' => $apiUri,
                'middleware' => $apiMiddleware,
            ]
        );

        $this->registerApiRouteAliases($operationClass, $apiUri, $apiMethods, $apiMiddleware);
    }

    /**
     * @param array<int, string> $methods
     * @param array<int, string> $middleware
     */
    protected function registerApiRouteAliases(
        string $operationClass,
        string $uri,
        array $methods,
        array $middleware
    ): void {
        $module = $this->resolveModuleKeyFromOperationClass($operationClass);
        if ($module === null) {
            return;
        }

        $suffix = $this->resolveOperationRouteSuffix($operationClass, $uri, $module);
        if ($suffix === null) {
            return;
        }

        $baseName = "module:api.{$module}";
        if ($suffix !== 'index') {
            $baseName .= ".{$suffix}";
        }

        $routeNames = [$baseName];
        foreach ($methods as $method) {
            $routeNames[] = $baseName.'.'.strtolower($method);

            $actionAlias = $this->httpMethodActionAlias($method);
            if ($actionAlias !== null) {
                $routeNames[] = $baseName.'.'.$actionAlias;
            }
        }

        $routeUri = $this->panelPrefixedUri(self::API_PANEL, $uri);
        $routeMiddleware = $middleware !== []
            ? $middleware
            : (array) config('upsoftware.middleware.api', []);

        foreach (array_values(array_unique($routeNames)) as $routeName) {
            if (Route::has($routeName)) {
                continue;
            }

            Route::middleware($routeMiddleware)
                ->match($methods, $routeUri, SvariumHttpKernel::class)
                ->name($routeName);
        }
    }

    protected function resolvePanelsForOperation(string $class): array
    {
        $availablePanels = array_keys(app(PanelRegistry::class)->all());

        $legacyPanel = $this->readStaticProperty($class, 'panel');

        if (is_string($legacyPanel) && trim($legacyPanel) !== '') {
            $legacy = trim($legacyPanel);
            if ($legacy === '*') {
                return $availablePanels;
            }

            if (in_array($legacy, $availablePanels, true)) {
                return [$legacy];
            }
        }

        if (is_array($legacyPanel)) {
            $normalizedLegacyPanels = $this->normalizePanels($legacyPanel, $availablePanels);
            if ($normalizedLegacyPanels !== []) {
                return $normalizedLegacyPanels;
            }
        }

        $panels = $this->readStaticProperty($class, 'panels');
        if (
            ! $this->isStaticPropertyDeclaredOnClass($class, 'panels')
            && is_string($panels)
            && trim($panels) === 'admin'
        ) {
            $panels = null;
        }

        $normalizedPanels = $this->normalizePanels($panels, $availablePanels);

        if (in_array('*', $normalizedPanels, true)) {
            $panelNames = $availablePanels;

            if ($panelNames !== []) {
                return $panelNames;
            }
        }

        if ($normalizedPanels !== []) {
            return $normalizedPanels;
        }

        return [$this->resolveDefaultPanelName()];
    }

    protected function resolveDefaultPanelName(): string
    {
        $panels = app(PanelRegistry::class)->all();
        $configured = trim((string) config('upsoftware.panel.name', env('SVARIUM_PANEL_NAME', '')));

        if ($panels !== []) {
            if ($configured !== '' && array_key_exists($configured, $panels)) {
                return $configured;
            }

            $noPrefixPanels = array_filter(
                $panels,
                static fn ($panel): bool => $panel instanceof Panel && $panel->prefix === null
            );

            if (count($noPrefixPanels) === 1) {
                return (string) array_key_first($noPrefixPanels);
            }

            return (string) array_key_first($panels);
        }

        if ($configured !== '') {
            return $configured;
        }

        return 'admin';
    }

    protected function normalizePanels(mixed $panels, ?array $availablePanels = null): array
    {
        if (is_string($panels)) {
            $panels = [$panels];
        }

        if (! is_array($panels)) {
            return [];
        }

        $normalized = [];

        $knownPanels = is_array($availablePanels)
            ? array_values(array_unique(array_map(static fn ($panel): string => (string) $panel, $availablePanels)))
            : [];

        foreach ($panels as $panel) {
            if (! is_string($panel)) {
                continue;
            }

            $panel = trim($panel);

            if ($panel === '') {
                continue;
            }

            if ($panel === '*') {
                $normalized[] = $panel;
                continue;
            }

            if ($knownPanels !== [] && ! in_array($panel, $knownPanels, true)) {
                continue;
            }

            $normalized[] = $panel;
        }

        return array_values(array_unique($normalized));
    }

    protected function isStaticPropertyDeclaredOnClass(string $class, string $property): bool
    {
        try {
            $reflection = new \ReflectionClass($class);

            if (! $reflection->hasProperty($property)) {
                return false;
            }

            $declared = $reflection->getProperty($property)->getDeclaringClass()->getName();

            return ltrim($declared, '\\') === ltrim($class, '\\');
        } catch (\ReflectionException) {
            return false;
        }
    }

    /**
     * @return array<int, string>
     */
    protected function widgetContextsFromUri(string $uri): array
    {
        $normalized = trim($uri, '/');

        if ($normalized === '') {
            return ['dashboard'];
        }

        $segments = array_values(array_filter(
            explode('/', $normalized),
            static function (string $segment): bool {
                $segment = trim($segment);

                if ($segment === '') {
                    return false;
                }

                if (str_starts_with($segment, '{') && str_ends_with($segment, '}')) {
                    return false;
                }

                return true;
            }
        ));

        if ($segments === []) {
            return ['dashboard'];
        }

        $dot = implode('.', $segments);
        $contexts = [$dot];

        if (count($segments) === 1) {
            $contexts[] = $segments[0].'.index';
            $contexts[] = $segments[0];
        }

        return array_values(array_unique(array_filter($contexts)));
    }

    protected function readStaticProperty(string $class, string $property): mixed
    {
        try {
            $reflection = new \ReflectionClass($class);

            if (! $reflection->hasProperty($property)) {
                return null;
            }

            $reflectionProperty = $reflection->getProperty($property);
            $reflectionProperty->setAccessible(true);

            return $reflectionProperty->getValue();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function classFromFile(string $path): string
    {
        $contents = (string) File::get($path);

        $namespace = null;
        if (preg_match('/^\s*namespace\s+([^;]+);/m', $contents, $namespaceMatch)) {
            $namespace = trim((string) ($namespaceMatch[1] ?? ''));
        }

        $class = null;
        if (preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/m', $contents, $classMatch)) {
            $class = trim((string) ($classMatch[1] ?? ''));
        }

        if ($namespace !== null && $namespace !== '' && $class !== null && $class !== '') {
            return $namespace.'\\'.$class;
        }

        $relative = str_replace(app_path().DIRECTORY_SEPARATOR, '', $path);
        $relative = str_replace(['/', '.php'], ['\\', ''], $relative);

        return 'App\\'.$relative;
    }
}
