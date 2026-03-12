<?php

namespace Upsoftware\Svarium\Roles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;
use Upsoftware\Svarium\Panel\OperationRegistry;
use Upsoftware\Svarium\Panel\Resource;
use Upsoftware\Svarium\Panel\ResourceRegistry;

class RolePermissionCatalog
{
    protected const RESOURCE_ACTIONS = [
        'list' => 'Access list',
        'create' => 'Create',
        'edit' => 'Edit',
        'preview' => 'Preview',
        'duplicate' => 'Duplicate',
        'delete' => 'Delete',
        'import' => 'Import',
    ];

    public function ensurePermissionsForGuard(string $guardName): void
    {
        $permissionModel = $this->resolvePermissionModelClass();

        if ($permissionModel === null) {
            return;
        }

        foreach ($this->definitions() as $definition) {
            $permissionModel::query()->firstOrCreate([
                'name' => $definition['name'],
                'guard_name' => $guardName,
            ]);
        }
    }

    public function groupedOptions(string $guardName = 'web'): array
    {
        $this->ensurePermissionsForGuard($guardName);

        $groups = [];

        foreach ($this->groupDefinitions() as $group) {
            $options = [];

            foreach ($group['items'] as $item) {
                $options[] = [
                    'value' => $item['name'],
                    'label' => $item['label'],
                    'description' => $item['description'] ?? null,
                ];
            }

            if ($options === []) {
                continue;
            }

            $groups[] = [
                'label' => $group['label'],
                'options' => $options,
            ];
        }

        return $groups;
    }

    public function definitions(): array
    {
        $definitions = [];

        foreach ($this->resourceDefinitions() as $definition) {
            $definitions[$definition['name']] = $definition;
        }

        foreach ($this->operationDefinitions() as $definition) {
            $definitions[$definition['name']] = $definition;
        }

        ksort($definitions);

        return array_values($definitions);
    }

    public function resourcePermissionName(string $resourceClass, string $action): string
    {
        return 'resource.'.$this->resourceKey($resourceClass).'.'.$action;
    }

    public function operationPermissionName(string $operationClass, ?string $uri = null): ?string
    {
        if (! $this->shouldRegisterOperationPermission($operationClass)) {
            return null;
        }

        $resolvedUri = $uri;
        if ($resolvedUri === null && method_exists($operationClass, 'uri')) {
            $resolvedUri = (string) $operationClass::uri();
        }

        return 'operation.'.$this->operationKey($operationClass, (string) $resolvedUri);
    }

    protected function groupDefinitions(): array
    {
        $groups = [];

        foreach ($this->resourceDefinitions() as $definition) {
            $groupKey = 'resource:'.$definition['group_key'];

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'label' => $definition['group_label'],
                    'items' => [],
                ];
            }

            $groups[$groupKey]['items'][] = $definition;
        }

        $operationItems = $this->operationDefinitions();
        if ($operationItems !== []) {
            $groups['operations'] = [
                'label' => __('Operations'),
                'items' => $operationItems,
            ];
        }

        return array_values($groups);
    }

    protected function resourceDefinitions(): array
    {
        $definitions = [];

        foreach (app(ResourceRegistry::class)->all() as $resourceClass) {
            if (! is_string($resourceClass) || ! class_exists($resourceClass) || ! is_subclass_of($resourceClass, Resource::class)) {
                continue;
            }

            $resource = app($resourceClass);
            $groupLabel = $this->resourceLabel($resourceClass, $resource);
            $groupKey = $this->resourceKey($resourceClass);

            foreach (self::RESOURCE_ACTIONS as $action => $label) {
                $definitions[] = [
                    'name' => $this->resourcePermissionName($resourceClass, $action),
                    'label' => __($label),
                    'description' => $groupLabel,
                    'group_key' => $groupKey,
                    'group_label' => $groupLabel,
                ];
            }
        }

        return $definitions;
    }

    protected function operationDefinitions(): array
    {
        $definitions = [];

        foreach (app(OperationRegistry::class)->definitions() as $definition) {
            $operationClass = $definition['operation'] ?? null;

            if (! is_string($operationClass) || $operationClass === '') {
                continue;
            }

            if (! $this->shouldRegisterOperationPermission($operationClass)) {
                continue;
            }

            $uri = $this->extractOperationUri($definition['pattern'] ?? '');
            $name = $this->operationPermissionName($operationClass, $uri);
            if ($name === null) {
                continue;
            }

            $definitions[$name] = [
                'name' => $name,
                'label' => $this->operationLabel($operationClass, $uri),
                'description' => $uri,
                'group_key' => 'operations',
                'group_label' => __('Operations'),
            ];
        }

        ksort($definitions);

        return array_values($definitions);
    }

    protected function shouldRegisterOperationPermission(string $operationClass): bool
    {
        if (str_contains($operationClass, '\\Panel\\Resource\\Operations\\')) {
            return false;
        }

        if (str_ends_with($operationClass, '\\DashboardOperation')) {
            return false;
        }

        if (str_contains($operationClass, '\\Panel\\Operations\\Auth\\')) {
            return false;
        }

        if (str_ends_with($operationClass, '\\SvariumInstallOperation')) {
            return false;
        }

        if (str_ends_with($operationClass, '\\SvariumConfigurationOperation')) {
            return false;
        }

        return true;
    }

    protected function resourceKey(string $resourceClass): string
    {
        return (string) Str::of(class_basename($resourceClass))
            ->replace('Resource', '')
            ->snake()
            ->toString();
    }

    protected function resourceLabel(string $resourceClass, object $resource): string
    {
        if ($resource instanceof Resource) {
            $modelClass = $resourceClass::model();
            $base = class_exists($modelClass)
                ? class_basename($modelClass)
                : class_basename($resourceClass);

            return (string) Str::of($base)->headline();
        }

        return (string) Str::of(class_basename($resourceClass))
            ->replace('Resource', '')
            ->headline();
    }

    protected function operationKey(string $operationClass, string $uri): string
    {
        $uriKey = (string) Str::of($uri)
            ->replaceMatches('/\{[^}]+\}/', '')
            ->replace('/', '_')
            ->replace('-', '_')
            ->snake()
            ->trim('_')
            ->toString();

        if ($uriKey !== '') {
            return $uriKey;
        }

        return (string) Str::of(class_basename($operationClass))
            ->replace('Operation', '')
            ->snake()
            ->toString();
    }

    protected function operationLabel(string $operationClass, string $uri): string
    {
        $base = (string) Str::of(class_basename($operationClass))
            ->replace('Operation', '')
            ->headline()
            ->toString();

        if ($base !== '') {
            return $base;
        }

        return (string) Str::of($uri)
            ->replace('/', ' / ')
            ->headline();
    }

    protected function extractOperationUri(string $pattern): string
    {
        $trimmed = trim($pattern, '#^$');

        return preg_replace('/\(\[\^\/\]\+\)/', '{param}', $trimmed) ?? '';
    }

    protected function resolvePermissionModelClass(): ?string
    {
        $class = (string) config('permission.models.permission', config('upsoftware.models.permission'));

        if ($class === '' || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        try {
            new $class();
        } catch (Throwable) {
            return null;
        }

        return $class;
    }
}
