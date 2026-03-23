<?php

namespace Upsoftware\Svarium\Panel;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;
use Upsoftware\Svarium\Http\Middleware\HandleInertiaRequests;
use Upsoftware\Svarium\Http\Middleware\InitializeTenancy;
use Upsoftware\Svarium\Http\Middleware\LocaleMiddleware;
use Upsoftware\Svarium\Http\Middleware\ResolveDomainContext;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceCreateOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceDeleteOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceDuplicateOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceEditOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceExportOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceImportOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceListOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourcePreviewOperation;
use Upsoftware\Svarium\Routing\SvariumHttpKernel;

class ResourceRegistry
{
    protected const API_PANEL = '__api';

    protected array $resources = [];

    public function register(string $resourceClass): void
    {
        $this->resources[] = $resourceClass;

        $resource = app($resourceClass);
        $slug = $resource::slug();
        $panel = $this->resolvePanelName();
        $this->registerModuleRouteAliases($resourceClass, $panel, $slug);

        $registry = app(OperationRegistry::class);
        $listOperationClass = $this->resolveResourceOperationClass($resourceClass, 'list', ResourceListOperation::class);
        $createOperationClass = $this->resolveResourceOperationClass($resourceClass, 'create', ResourceCreateOperation::class);
        $editOperationClass = $this->resolveResourceOperationClass($resourceClass, 'edit', ResourceEditOperation::class);
        $previewOperationClass = $this->resolveResourceOperationClass($resourceClass, 'preview', ResourcePreviewOperation::class);
        $deleteOperationClass = $this->resolveResourceOperationClass($resourceClass, 'delete', ResourceDeleteOperation::class);
        $duplicateOperationClass = $this->resolveResourceOperationClass($resourceClass, 'duplicate', ResourceDuplicateOperation::class);

        $registry->register($panel, ['GET', 'POST'], $slug, $listOperationClass, [
            'resource' => $resourceClass,
        ]);

        $registry->register($panel, ['GET', 'POST'], "{$slug}/create", $createOperationClass, [
            'resource' => $resourceClass,
        ]);

        $registry->register($panel, ['GET', 'POST'], "{$slug}/create/{tab}", $createOperationClass, [
            'resource' => $resourceClass,
        ]);

        $registry->register($panel, ['GET', 'POST'], "{$slug}/import", ResourceImportOperation::class, [
            'resource' => $resourceClass,
        ]);

        $registry->register($panel, ['GET', 'POST'], "{$slug}/export", ResourceExportOperation::class, [
            'resource' => $resourceClass,
        ]);

        $registry->register($panel, ['GET', 'POST'], "{$slug}/{id}/edit", $editOperationClass, [
            'resource' => $resourceClass,
        ]);

        $registry->register($panel, ['GET', 'POST'], "{$slug}/{id}/edit/{tab}", $editOperationClass, [
            'resource' => $resourceClass,
        ]);

        $registry->register($panel, ['GET'], "{$slug}/{id}/preview", $previewOperationClass, [
            'resource' => $resourceClass,
        ]);

        $registry->register($panel, ['POST'], "{$slug}/{id}/delete", $deleteOperationClass, [
            'resource' => $resourceClass,
        ]);

        $registry->register($panel, ['GET', 'POST'], "{$slug}/{id}/duplicate", $duplicateOperationClass, [
            'resource' => $resourceClass,
        ]);

        $this->registerApiCrudRoutes($resourceClass, $slug, $registry);
    }

    public function all(): array
    {
        return $this->resources;
    }

    protected function resolvePanelName(): string
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

            return array_key_first($panels);
        }

        if ($configured !== '') {
            return $configured;
        }

        return 'admin';
    }

    protected function registerApiCrudRoutes(string $resourceClass, string $slug, OperationRegistry $registry): void
    {
        if (! (bool) config('upsoftware.api.enabled', true)) {
            return;
        }

        $api = $this->resolveResourceApiDeclaration($resourceClass);

        if ($api === false || $api === null) {
            return;
        }

        $apiConfig = is_array($api) ? $api : [];
        if (array_key_exists('enabled', $apiConfig) && ! (bool) $apiConfig['enabled']) {
            return;
        }

        $apiBaseUri = $this->resolveApiBaseUri($slug, $apiConfig);
        if ($apiBaseUri === null) {
            return;
        }

        $operations = $this->resolveApiCrudOperations($apiConfig);
        $middleware = $this->resolveApiCrudMiddleware($apiConfig);
        $docsMap = $this->resolveApiCrudDocs($apiConfig['docs'] ?? null);

        $meta = [
            'resource' => $resourceClass,
            'api' => true,
            'api_resource' => true,
            'middleware' => $middleware,
            'api_group' => is_string($apiConfig['group'] ?? null) ? trim((string) $apiConfig['group']) : null,
        ];
        $listOperationClass = $this->resolveResourceOperationClass($resourceClass, 'list', ResourceListOperation::class);
        $createOperationClass = $this->resolveResourceOperationClass($resourceClass, 'create', ResourceCreateOperation::class);
        $editOperationClass = $this->resolveResourceOperationClass($resourceClass, 'edit', ResourceEditOperation::class);
        $deleteOperationClass = $this->resolveResourceOperationClass($resourceClass, 'delete', ResourceDeleteOperation::class);

        if ($operations['index']) {
            $registry->register(self::API_PANEL, ['GET'], $apiBaseUri, $listOperationClass, [
                ...$meta,
                'api_resource_operation' => 'index',
                'api_docs' => $docsMap['index'],
            ]);
            $this->registerApiRoute($apiBaseUri, ['GET'], $middleware, $this->resourceApiRouteNames($resourceClass, 'index'));
        }

        if ($operations['store']) {
            $registry->register(self::API_PANEL, ['POST'], $apiBaseUri, $createOperationClass, [
                ...$meta,
                'api_resource_operation' => 'store',
                'api_docs' => $docsMap['store'],
            ]);
            $this->registerApiRoute($apiBaseUri, ['POST'], $middleware, $this->resourceApiRouteNames($resourceClass, 'store'));
        }

        $itemUri = trim($apiBaseUri.'/{id}', '/');

        if ($operations['show']) {
            $registry->register(self::API_PANEL, ['GET'], $itemUri, $editOperationClass, [
                ...$meta,
                'api_resource_operation' => 'show',
                'api_docs' => $docsMap['show'],
            ]);
            $this->registerApiRoute($itemUri, ['GET'], $middleware, $this->resourceApiRouteNames($resourceClass, 'show'));
        }

        if ($operations['update']) {
            $registry->register(self::API_PANEL, ['PUT', 'PATCH'], $itemUri, $editOperationClass, [
                ...$meta,
                'api_resource_operation' => 'update',
                'api_docs' => $docsMap['update'],
            ]);
            $this->registerApiRoute($itemUri, ['PUT', 'PATCH'], $middleware, $this->resourceApiRouteNames($resourceClass, 'update'));
        }

        if ($operations['delete']) {
            $registry->register(self::API_PANEL, ['DELETE'], $itemUri, $deleteOperationClass, [
                ...$meta,
                'api_resource_operation' => 'delete',
                'api_docs' => $docsMap['delete'],
            ]);
            $this->registerApiRoute($itemUri, ['DELETE'], $middleware, $this->resourceApiRouteNames($resourceClass, 'delete'));
        }
    }

    /**
     * @return array{
     *   index: array<string, mixed>,
     *   store: array<string, mixed>,
     *   show: array<string, mixed>,
     *   update: array<string, mixed>,
     *   delete: array<string, mixed>
     * }
     */
    protected function resolveApiCrudDocs(mixed $value): array
    {
        $empty = [
            'index' => [],
            'store' => [],
            'show' => [],
            'update' => [],
            'delete' => [],
        ];

        if (! is_array($value)) {
            return $empty;
        }

        foreach (array_keys($empty) as $operation) {
            $raw = $value[$operation] ?? null;
            $empty[$operation] = is_array($raw) ? $raw : [];
        }

        return $empty;
    }

    protected function resolveResourceApiDeclaration(string $resourceClass): bool|array|null
    {
        if (class_exists($resourceClass)) {
            $reflection = new \ReflectionClass($resourceClass);

            if ($reflection->hasProperty('api')) {
                $defaults = $reflection->getDefaultProperties();
                $value = $defaults['api'] ?? null;

                if (is_bool($value)) {
                    return $value;
                }
            }
        }

        return method_exists($resourceClass, 'api')
            ? $resourceClass::api()
            : false;
    }

    protected function resolveResourceOperationClass(
        string $resourceClass,
        string $operation,
        string $fallbackClass
    ): string {
        $resourceBase = trim((string) Str::of(class_basename($resourceClass))
            ->replace('Resource', '')
            ->toString());

        if ($resourceBase === '') {
            return $fallbackClass;
        }

        $resourceNamespace = trim((string) Str::beforeLast($resourceClass, '\\'));
        if ($resourceNamespace === '') {
            return $fallbackClass;
        }

        $operationSuffix = match (strtolower(trim($operation))) {
            'list' => 'List',
            'create' => 'Create',
            'preview' => 'Preview',
            'edit' => 'Edit',
            'duplicate' => 'Duplicate',
            'delete' => 'Delete',
            default => '',
        };

        if ($operationSuffix === '') {
            return $fallbackClass;
        }

        $operationsNamespace = $resourceNamespace.'\\Operations';
        $candidate = $operationsNamespace.'\\'.$resourceBase.$operationSuffix.'Operation';

        if (! class_exists($candidate)) {
            return $fallbackClass;
        }

        if (! is_subclass_of($candidate, $fallbackClass)) {
            return $fallbackClass;
        }

        return $candidate;
    }

    protected function resolveApiBaseUri(string $slug, array $apiConfig): ?string
    {
        $apiPrefix = trim((string) config('upsoftware.api.prefix', 'api/v1'), '/');
        $uriSource = trim((string) ($apiConfig['uri'] ?? $slug), '/');
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
            $apiUri = $uriSource !== '' ? $uriSource : $apiPrefix;
        }

        $apiUri = trim((string) $apiUri, '/');

        return $apiUri !== '' ? $apiUri : null;
    }

    /**
     * @return array{index: bool, store: bool, show: bool, update: bool, delete: bool}
     */
    protected function resolveApiCrudOperations(array $apiConfig): array
    {
        $all = [
            'index' => true,
            'store' => true,
            'show' => true,
            'update' => true,
            'delete' => true,
        ];

        $operations = $all;

        // New API config style: only / except.
        if (array_key_exists('only', $apiConfig)) {
            $operations = $this->resolveApiCrudOperationSet($apiConfig['only'], false);
        }

        if (array_key_exists('except', $apiConfig)) {
            $excluded = $this->resolveApiCrudOperationSet($apiConfig['except'], false);

            foreach ($excluded as $operation => $enabled) {
                if ($enabled) {
                    $operations[$operation] = false;
                }
            }
        }

        if (array_key_exists('only', $apiConfig) || array_key_exists('except', $apiConfig)) {
            return $operations;
        }

        // Backward compatibility with legacy "operations".
        if (array_key_exists('operations', $apiConfig)) {
            return $this->resolveApiCrudOperationSet($apiConfig['operations'], true);
        }

        return $operations;
    }

    /**
     * @return array{index: bool, store: bool, show: bool, update: bool, delete: bool}
     */
    protected function resolveApiCrudOperationSet(mixed $value, bool $defaultAll): array
    {
        $all = [
            'index' => true,
            'store' => true,
            'show' => true,
            'update' => true,
            'delete' => true,
        ];

        $none = [
            'index' => false,
            'store' => false,
            'show' => false,
            'update' => false,
            'delete' => false,
        ];

        if ($value === null) {
            return $defaultAll ? $all : $none;
        }

        if ($value === true) {
            return $all;
        }

        if ($value === false) {
            return $none;
        }

        $resolved = $defaultAll ? $all : $none;
        $tokens = [];

        if (is_string($value)) {
            $tokens = array_map('trim', explode(',', $value));
        } elseif (is_array($value)) {
            foreach ($value as $key => $entry) {
                if (is_string($key) && is_bool($entry)) {
                    $normalized = $this->normalizeApiCrudOperation((string) $key);
                    if ($normalized !== null) {
                        $resolved[$normalized] = $entry;
                    }
                    continue;
                }

                if (is_string($entry)) {
                    $tokens[] = $entry;
                }
            }
        }

        foreach ($tokens as $token) {
            $normalized = strtolower(trim((string) $token));
            if ($normalized === '') {
                continue;
            }

            if ($normalized === '*') {
                return $all;
            }

            $operation = $this->normalizeApiCrudOperation($normalized);
            if ($operation !== null) {
                $resolved[$operation] = true;
            }
        }

        return $resolved;
    }

    protected function normalizeApiCrudOperation(string $value): ?string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'index', 'list' => 'index',
            'store', 'create' => 'store',
            'show', 'view', 'preview' => 'show',
            'update', 'edit', 'patch', 'put' => 'update',
            'delete', 'destroy', 'remove' => 'delete',
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    protected function resolveApiCrudMiddleware(array $apiConfig): array
    {
        $globalApiMiddleware = (array) config('upsoftware.middleware.api', ['api']);
        $resourceApiMiddleware = array_key_exists('middleware', $apiConfig)
            ? (array) $apiConfig['middleware']
            : (array) config('upsoftware.api.auth.middleware', []);

        $merged = array_merge($globalApiMiddleware, $resourceApiMiddleware);

        $merged[] = InitializeTenancy::class;
        $merged[] = ResolveDomainContext::class;
        $merged[] = LocaleMiddleware::class;

        $normalized = [];
        foreach ($merged as $item) {
            $value = trim((string) $item);
            if ($value === '' || in_array($value, $normalized, true)) {
                continue;
            }

            $normalized[] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<int, string> $methods
     * @param array<int, string> $middleware
     * @param array<int, string> $routeNames
     */
    protected function registerApiRoute(string $uri, array $methods, array $middleware, array $routeNames): void
    {
        $path = trim($uri, '/');

        foreach ($routeNames as $routeName) {
            $name = trim((string) $routeName);
            if ($name === '' || Route::has($name)) {
                continue;
            }

            Route::middleware($middleware)
                ->match($methods, $path, SvariumHttpKernel::class)
                ->withoutMiddleware([ValidateCsrfToken::class])
                ->name($name);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function resourceApiRouteNames(string $resourceClass, string $operation): array
    {
        $module = $this->resolveResourceModuleKey($resourceClass);
        $base = "module:api.{$module}";

        return match ($operation) {
            'index' => [$base, "{$base}.index", "{$base}.get"],
            'store' => ["{$base}.store", "{$base}.post"],
            'show' => ["{$base}.show", "{$base}.show.get"],
            'update' => ["{$base}.update", "{$base}.put", "{$base}.patch"],
            'delete' => ["{$base}.delete", "{$base}.destroy"],
            default => [],
        };
    }

    protected function resolveResourceModuleKey(string $resourceClass): string
    {
        $module = (string) str(class_basename($resourceClass))
            ->replace('Resource', '')
            ->snake();

        if ($module === '') {
            return 'resource';
        }

        return $module;
    }

    protected function registerModuleRouteAliases(string $resourceClass, string $panel, string $slug): void
    {
        $middleware = ['web'];

        $middleware[] = InitializeTenancy::class;
        $middleware[] = LocaleMiddleware::class;
        $middleware[] = ResolveDomainContext::class;
        $middleware[] = HandleInertiaRequests::class;

        $module = (string) str(class_basename($resourceClass))
            ->replace('Resource', '')
            ->snake();

        if ($module === '') {
            $module = (string) str($slug)->singular()->snake();
        }

        $panelInstance = app(PanelRegistry::class)->get($panel);
        $panelPrefix = $panelInstance?->prefix;

        $base = trim(implode('/', array_filter([
            trim((string) $panelPrefix, '/'),
            trim($slug, '/'),
        ])), '/');

        if ($base === '') {
            return;
        }

        $routes = [
            "module:{$module}" => $base,
            "module:{$module}.create" => "{$base}/create",
            "module:{$module}.create.tab" => "{$base}/create/{tab}",
            "module:{$module}.import" => "{$base}/import",
            "module:{$module}.export" => "{$base}/export",
            "module:{$module}.edit" => "{$base}/{id}/edit",
            "module:{$module}.edit.tab" => "{$base}/{id}/edit/{tab}",
            "module:{$module}.preview" => "{$base}/{id}/preview",
            "module:{$module}.delete" => "{$base}/{id}/delete",
            "module:{$module}.duplicate" => "{$base}/{id}/duplicate",
        ];

        foreach ($routes as $name => $uri) {
            if (Route::has($name)) {
                continue;
            }

            Route::middleware($middleware)
                ->any($uri, SvariumHttpKernel::class)
                ->name($name);
        }
    }
}
