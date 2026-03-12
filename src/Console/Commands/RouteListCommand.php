<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceCreateOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceDeleteOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceDuplicateOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceEditOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceImportOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceListOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourcePreviewOperation;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\OperationRegistry;
use Upsoftware\Svarium\Panel\Panel;
use Upsoftware\Svarium\Panel\PanelRegistry;
use Upsoftware\Svarium\Roles\RolePermissionCatalog;
use Upsoftware\Svarium\Routing\SvariumHttpKernel;

class RouteListCommand extends CoreCommand
{
    /**
     * @var array<string, array<int, string>>
     */
    protected array $permissionRoleCache = [];

    protected $signature = 'svarium:route:list
        {--panel= : Filter by panel name}
        {--module= : Filter by module key (e.g. ksef)}
        {--name= : Filter named aliases containing this fragment}
        {--json : Output as JSON}';

    protected $description = 'Lists Svarium operation routes and route aliases';

    public function handle(): int
    {
        $panelFilter = strtolower(trim((string) $this->option('panel')));
        $moduleFilter = strtolower(trim((string) $this->option('module')));
        $nameFilter = trim((string) $this->option('name'));

        $operations = $this->collectOperationRoutes($panelFilter, $moduleFilter);
        $aliases = $this->collectNamedAliases($panelFilter, $moduleFilter, $nameFilter);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'operations' => $operations,
                'aliases' => $aliases,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('Operation routes');
        if ($operations === []) {
            $this->line('Brak tras operation dla podanych filtrów.');
        } else {
            $this->table(
                ['Panel', 'Module', 'Methods', 'URI', 'Operation', 'Route name', 'Permission', 'Access levels'],
                array_map(static function (array $row): array {
                    return [
                        $row['panel'],
                        $row['module'] ?? '-',
                        implode(',', $row['methods']),
                        $row['uri'],
                        $row['operation'],
                        $row['route_name'] ?? '-',
                        $row['permission'] ?? '-',
                        $row['access_levels'] !== [] ? implode(', ', $row['access_levels']) : '-',
                    ];
                }, $operations)
            );
        }

        $this->newLine();
        $this->info('Named aliases');
        if ($aliases === []) {
            $this->line('Brak aliasów tras dla podanych filtrów.');
        } else {
            $this->table(
                ['Name', 'Methods', 'URI', 'Permission', 'Access levels', 'Action'],
                array_map(static function (array $row): array {
                    return [
                        $row['name'],
                        implode(',', $row['methods']),
                        $row['uri'],
                        $row['permission'] ?? '-',
                        $row['access_levels'] !== [] ? implode(', ', $row['access_levels']) : '-',
                        $row['action'],
                    ];
                }, $aliases)
            );
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function collectOperationRoutes(string $panelFilter, string $moduleFilter): array
    {
        /** @var OperationRegistry $registry */
        $registry = app(OperationRegistry::class);
        $definitions = $registry->definitions();
        $rows = [];

        foreach ($definitions as $definition) {
            $panel = strtolower(trim((string) ($definition['panel'] ?? '')));
            $operationClass = trim((string) ($definition['operation'] ?? ''));
            $methods = array_values(array_filter(array_map(
                static fn (mixed $method): string => strtoupper(trim((string) $method)),
                (array) ($definition['methods'] ?? [])
            ), static fn (string $method): bool => $method !== ''));

            if ($panelFilter !== '' && $panel !== $panelFilter) {
                continue;
            }

            if ($operationClass === '') {
                continue;
            }

            $module = $this->moduleKeyFromOperationClass($operationClass);
            if ($moduleFilter !== '' && strtolower((string) $module) !== $moduleFilter) {
                continue;
            }

            $routeName = $this->operationRouteBaseName(
                $operationClass,
                $panel,
                $module,
                $this->definitionUri($definition)
            );
            $permission = $this->resolvePermissionForDefinition($definition, $operationClass);
            $accessLevels = $this->resolveAccessLevels($permission);

            $rows[] = [
                'panel' => $panel,
                'module' => $module,
                'methods' => $methods !== [] ? $methods : ['GET'],
                'uri' => $this->definitionUri($definition),
                'operation' => $operationClass,
                'route_name' => $routeName,
                'permission' => $permission,
                'access_levels' => $accessLevels,
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            return strcmp(
                ($left['panel'] ?? '').'|'.($left['uri'] ?? ''),
                ($right['panel'] ?? '').'|'.($right['uri'] ?? '')
            );
        });

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function collectNamedAliases(string $panelFilter, string $moduleFilter, string $nameFilter): array
    {
        $rows = [];

        foreach (Route::getRoutes() as $route) {
            $name = trim((string) $route->getName());
            if ($name === '') {
                continue;
            }

            $action = trim((string) $route->getActionName());
            if (! str_contains($action, SvariumHttpKernel::class)) {
                continue;
            }

            if ($nameFilter !== '' && ! Str::contains($name, $nameFilter, true)) {
                continue;
            }

            [$panel, $module] = $this->panelAndModuleFromNamedRoute($name);

            if ($panelFilter !== '' && strtolower((string) $panel) !== $panelFilter) {
                continue;
            }

            if ($moduleFilter !== '' && strtolower((string) $module) !== $moduleFilter) {
                continue;
            }

            $permission = $this->resolvePermissionForNamedAlias(
                $name,
                array_values(array_filter($route->methods(), static fn (string $method): bool => $method !== 'HEAD')),
                '/'.trim((string) $route->uri(), '/')
            );
            $accessLevels = $this->resolveAccessLevels($permission);

            $rows[] = [
                'name' => $name,
                'methods' => array_values(array_filter($route->methods(), static fn (string $method): bool => $method !== 'HEAD')),
                'uri' => '/'.trim((string) $route->uri(), '/'),
                'permission' => $permission,
                'access_levels' => $accessLevels,
                'action' => $action,
            ];
        }

        usort($rows, static fn (array $left, array $right): int => strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? '')));

        return $rows;
    }

    /**
     * @param array<string, mixed> $definition
     */
    protected function definitionUri(array $definition): string
    {
        $pattern = (string) ($definition['pattern'] ?? '');
        $names = array_values((array) ($definition['names'] ?? []));

        $uri = $pattern;
        if (str_starts_with($uri, '#^') && str_ends_with($uri, '$#')) {
            $uri = substr($uri, 2, -2);
        }

        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $uri = preg_replace('/\(\[\^\/\]\+\)/', '{'.$name.'}', $uri, 1) ?? $uri;
        }

        $uri = str_replace('\\/', '/', $uri);
        $uri = trim($uri, '/');

        return $uri === '' ? '/' : '/'.$uri;
    }

    protected function moduleKeyFromOperationClass(string $operationClass): ?string
    {
        if (preg_match('/\\\\Modules\\\\Builtin\\\\([^\\\\]+)\\\\/i', $operationClass, $matches) === 1) {
            return (string) Str::of((string) ($matches[1] ?? ''))->snake()->toString();
        }

        if (preg_match('/\\\\Modules\\\\([^\\\\]+)\\\\/i', $operationClass, $matches) === 1) {
            return (string) Str::of((string) ($matches[1] ?? ''))->snake()->toString();
        }

        return null;
    }

    protected function operationRouteBaseName(string $operationClass, string $panel, ?string $module, string $uri): ?string
    {
        if ($module === null || ! class_exists($operationClass) || ! is_subclass_of($operationClass, Operation::class)) {
            return null;
        }

        $custom = method_exists($operationClass, 'routeName')
            ? trim((string) $operationClass::routeName())
            : '';

        $suffix = $custom;
        if ($suffix === '') {
            $suffix = trim($uri, '/');
            $suffix = preg_replace('/^\{.+\}$/', '', $suffix) ?? $suffix;
            $suffix = str_replace('/', '.', $suffix);

            $moduleCandidates = [
                Str::lower($module),
                Str::lower((string) Str::of($module)->replace('_', '-')),
                Str::lower((string) Str::plural($module)),
                Str::lower((string) Str::plural((string) Str::of($module)->replace('_', '-'))),
            ];

            foreach ($moduleCandidates as $candidate) {
                $candidate = trim((string) $candidate, '.');
                if ($candidate !== '' && str_starts_with($suffix, $candidate.'.')) {
                    $suffix = substr($suffix, strlen($candidate) + 1);
                    break;
                }

                if ($suffix === $candidate) {
                    $suffix = 'index';
                    break;
                }
            }
        }

        $suffix = trim((string) $suffix, '.');
        if ($suffix === '') {
            $suffix = 'index';
        }

        $name = "module:{$module}";
        if ($suffix !== 'index') {
            $name .= '.'.$suffix;
        }

        if ($panel !== '') {
            return "{$name} (panel alias: module:{$panel}.{$module}".($suffix !== 'index' ? ".{$suffix}" : '').')';
        }

        return $name;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    protected function panelAndModuleFromNamedRoute(string $name): array
    {
        if (preg_match('/^module:([^.]+)\.([^.]+)(?:\..+)?$/', $name, $matches) === 1) {
            $panelOrModule = trim((string) ($matches[1] ?? ''));
            $module = trim((string) ($matches[2] ?? ''));

            $panelExists = $panelOrModule !== '' && app(\Upsoftware\Svarium\Panel\PanelRegistry::class)->get($panelOrModule) !== null;

            if ($panelExists) {
                return [$panelOrModule, $module !== '' ? $module : null];
            }

            return [null, $panelOrModule !== '' ? $panelOrModule : null];
        }

        if (preg_match('/^module:([^.]+)(?:\..+)?$/', $name, $matches) === 1) {
            $module = trim((string) ($matches[1] ?? ''));

            return [null, $module !== '' ? $module : null];
        }

        return [null, null];
    }

    /**
     * @param array<string, mixed> $definition
     */
    protected function resolvePermissionForDefinition(array $definition, string $operationClass): ?string
    {
        $resourceClass = trim((string) (($definition['meta']['resource'] ?? null) ?: ''));
        $resourceAction = $this->resolveResourceActionFromOperation($operationClass);

        if ($resourceClass !== '' && $resourceAction !== '') {
            return app(RolePermissionCatalog::class)->resourcePermissionName($resourceClass, $resourceAction);
        }

        $uri = $this->definitionUri($definition);

        return app(RolePermissionCatalog::class)->operationPermissionName($operationClass, $uri);
    }

    protected function resolveResourceActionFromOperation(string $operationClass): string
    {
        return match ($operationClass) {
            ResourceListOperation::class => 'list',
            ResourceCreateOperation::class => 'create',
            ResourceEditOperation::class => 'edit',
            ResourcePreviewOperation::class => 'preview',
            ResourceDeleteOperation::class => 'delete',
            ResourceDuplicateOperation::class => 'duplicate',
            ResourceImportOperation::class => 'import',
            default => '',
        };
    }

    /**
     * @return array<int, string>
     */
    protected function resolveAccessLevels(?string $permission): array
    {
        if (! is_string($permission) || trim($permission) === '') {
            return ['superadmin'];
        }

        $permission = trim($permission);
        if (array_key_exists($permission, $this->permissionRoleCache)) {
            return $this->permissionRoleCache[$permission];
        }

        $roles = ['superadmin'];
        $roleClass = (string) config('permission.models.role', \Spatie\Permission\Models\Role::class);

        if ($roleClass !== '' && class_exists($roleClass) && is_subclass_of($roleClass, Model::class)) {
            try {
                /** @var class-string<Model> $roleClass */
                $records = $roleClass::query()
                    ->whereHas('permissions', static function ($query) use ($permission): void {
                        $query->where('name', $permission);
                    })
                    ->get();

                foreach ($records as $role) {
                    $label = $this->roleAccessLabel($role);
                    if ($label === '') {
                        continue;
                    }

                    $roles[] = $label;
                }
            } catch (Throwable) {
                // Ignore DB/schema errors in command listing.
            }
        }

        $roles = array_values(array_unique(array_filter(array_map(
            static fn (string $role): string => trim($role),
            $roles
        ), static fn (string $role): bool => $role !== '')));

        sort($roles);

        return $this->permissionRoleCache[$permission] = $roles;
    }

    protected function roleAccessLabel(object $role): string
    {
        try {
            $roleKey = trim((string) ($role->getAttribute('role_key') ?? ''));
            if ($roleKey !== '') {
                return $roleKey;
            }

            $nameLocale = trim((string) ($role->getAttribute('name_locale') ?? ''));
            if ($nameLocale !== '') {
                return $nameLocale;
            }

            $name = $role->getAttribute('name');
            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }

            if (is_array($name)) {
                foreach (['en', 'pl', 'de'] as $preferredLocale) {
                    if (isset($name[$preferredLocale]) && is_string($name[$preferredLocale]) && trim($name[$preferredLocale]) !== '') {
                        return trim($name[$preferredLocale]);
                    }
                }

                foreach ($name as $value) {
                    if (is_string($value) && trim($value) !== '') {
                        return trim($value);
                    }
                }
            }
        } catch (Throwable) {
            return '';
        }

        return '';
    }

    /**
     * @param array<int, string> $methods
     */
    protected function resolvePermissionForNamedAlias(string $routeName, array $methods, string $uri): ?string
    {
        $path = trim($uri, '/');
        $panelHint = $this->panelAndModuleFromNamedRoute($routeName)[0] ?? null;
        $resolved = $this->resolveOperationForPath($path, $methods, $panelHint);

        if (! is_array($resolved)) {
            return null;
        }

        $operationClass = trim((string) ($resolved['operation'] ?? ''));
        if ($operationClass === '') {
            return null;
        }

        return $this->resolvePermissionForDefinition($resolved, $operationClass);
    }

    /**
     * @param array<int, string> $methods
     * @return array<string, mixed>|null
     */
    protected function resolveOperationForPath(string $path, array $methods, ?string $panelHint = null): ?array
    {
        /** @var OperationRegistry $registry */
        $registry = app(OperationRegistry::class);

        $path = trim($path, '/');
        $panelCandidates = $this->resolvePanelCandidates($path, $panelHint);

        $normalizedMethods = array_values(array_unique(array_filter(array_map(
            static fn (string $method): string => strtoupper(trim((string) $method)),
            $methods
        ), static fn (string $method): bool => $method !== '' && $method !== 'HEAD')));

        if ($normalizedMethods === []) {
            $normalizedMethods = ['GET'];
        }

        foreach ($panelCandidates as $panelName) {
            $operationPath = $this->stripPanelPrefix($path, $panelName);
            if ($operationPath === null) {
                continue;
            }

            foreach ($normalizedMethods as $method) {
                $resolved = $registry->resolve($panelName, $method, $operationPath);
                if (is_array($resolved)) {
                    return [
                        ...$resolved,
                        'panel' => $panelName,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function resolvePanelCandidates(string $path, ?string $panelHint = null): array
    {
        /** @var PanelRegistry $registry */
        $registry = app(PanelRegistry::class);
        $panels = $registry->all();
        $path = trim($path, '/');

        $prefixed = [];
        $noPrefix = [];

        foreach ($panels as $name => $panel) {
            if (! $panel instanceof Panel) {
                continue;
            }

            $prefix = trim((string) ($panel->prefix ?? ''), '/');
            if ($prefix === '') {
                $noPrefix[] = (string) $name;
            } elseif ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                $prefixed[] = (string) $name;
            }
        }

        $candidates = [];

        if (is_string($panelHint) && trim($panelHint) !== '' && isset($panels[trim($panelHint)])) {
            $candidates[] = trim($panelHint);
        }

        $candidates = [...$candidates, ...$prefixed, ...$noPrefix, ...array_map('strval', array_keys($panels))];

        return array_values(array_unique(array_filter(array_map(
            static fn (string $name): string => trim($name),
            $candidates
        ), static fn (string $name): bool => $name !== '')));
    }

    protected function stripPanelPrefix(string $path, string $panelName): ?string
    {
        $path = trim($path, '/');
        $panel = app(PanelRegistry::class)->get($panelName);
        $prefix = trim((string) ($panel?->prefix ?? ''), '/');

        if ($prefix === '') {
            return $path;
        }

        if ($path === $prefix) {
            return '';
        }

        if (! str_starts_with($path, $prefix.'/')) {
            return null;
        }

        return trim(substr($path, strlen($prefix) + 1), '/');
    }
}
