<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PermissionListCommand extends CoreCommand
{
    protected $signature = 'svarium:permission.list
        {--guard= : Filter by guard (e.g. web, api)}
        {--json : Output as JSON instead of table}';

    protected $description = 'Lists permissions and roles that have access (permissions + role_has_permissions)';

    public function handle(): int
    {
        try {
            $permissionModelClass = $this->resolvePermissionModelClass();
            $roleModelClass = $this->resolveRoleModelClass();
            $guardFilter = strtolower(trim((string) $this->option('guard')));

            $permissions = $this->loadPermissions($permissionModelClass, $guardFilter);

            if ($permissions->isEmpty()) {
                $this->warn('Brak permissionów do wyświetlenia.');

                return self::SUCCESS;
            }

            $permissionRoleMap = $this->loadPermissionRoleMap(
                $permissions,
                $roleModelClass,
                $guardFilter
            );

            $rows = $permissions
                ->map(function (Model $permission) use ($permissionRoleMap): array {
                    $permissionId = $this->normalizeScalar($permission->getKey());
                    $roles = $permissionRoleMap[$permissionId] ?? [];

                    return [
                        'permission' => (string) ($permission->getAttribute('name') ?? ''),
                        'guard' => (string) ($permission->getAttribute('guard_name') ?? ''),
                        'roles' => $roles,
                        'roles_count' => count($roles),
                    ];
                })
                ->all();

            if ((bool) $this->option('json')) {
                $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                return self::SUCCESS;
            }

            $this->table(
                ['permission', 'guard', 'roles_count', 'roles'],
                array_map(static function (array $row): array {
                    return [
                        $row['permission'],
                        $row['guard'] !== '' ? $row['guard'] : '-',
                        (string) $row['roles_count'],
                        $row['roles'] !== [] ? implode(', ', $row['roles']) : '-',
                    ];
                }, $rows)
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return class-string<Model>
     */
    protected function resolvePermissionModelClass(): string
    {
        $configured = trim((string) config('upsoftware.models.permission', ''));
        if ($configured !== '' && class_exists($configured) && is_subclass_of($configured, Model::class)) {
            return $configured;
        }

        $configured = trim((string) config('permission.models.permission', ''));
        if ($configured !== '' && class_exists($configured) && is_subclass_of($configured, Model::class)) {
            return $configured;
        }

        throw new \RuntimeException('Nie znaleziono poprawnego modelu Permission w konfiguracji.');
    }

    /**
     * @return class-string<Model>
     */
    protected function resolveRoleModelClass(): string
    {
        $configured = trim((string) config('upsoftware.models.role', ''));
        if ($configured !== '' && class_exists($configured) && is_subclass_of($configured, Model::class)) {
            return $configured;
        }

        $configured = trim((string) config('permission.models.role', ''));
        if ($configured !== '' && class_exists($configured) && is_subclass_of($configured, Model::class)) {
            return $configured;
        }

        throw new \RuntimeException('Nie znaleziono poprawnego modelu Role w konfiguracji.');
    }

    /**
     * @param class-string<Model> $permissionModelClass
     * @return Collection<int, Model>
     */
    protected function loadPermissions(string $permissionModelClass, string $guardFilter): Collection
    {
        /** @var Model $prototype */
        $prototype = new $permissionModelClass();
        $table = $prototype->getTable();
        $connection = $this->modelConnectionName($prototype);

        $query = $permissionModelClass::query()
            ->orderBy('guard_name')
            ->orderBy('name');

        if ($guardFilter !== '' && Schema::connection($connection)->hasColumn($table, 'guard_name')) {
            $query->where('guard_name', $guardFilter);
        }

        return $query->get();
    }

    /**
     * @param Collection<int, Model> $permissions
     * @param class-string<Model> $roleModelClass
     * @return array<string, array<int, string>>
     */
    protected function loadPermissionRoleMap(Collection $permissions, string $roleModelClass, string $guardFilter): array
    {
        if ($permissions->isEmpty()) {
            return [];
        }

        $roleHasPermissionsTable = trim((string) config('permission.table_names.role_has_permissions', 'role_has_permissions'));
        if ($roleHasPermissionsTable === '') {
            $roleHasPermissionsTable = 'role_has_permissions';
        }

        $permissionPrototype = $permissions->first();
        if (! $permissionPrototype instanceof Model) {
            return [];
        }

        $permissionConnection = $this->modelConnectionName($permissionPrototype);

        /** @var Model $rolePrototype */
        $rolePrototype = new $roleModelClass();
        $roleTable = $rolePrototype->getTable();
        $roleKeyName = $rolePrototype->getKeyName();
        $roleConnection = $this->modelConnectionName($rolePrototype);

        $permissionPivotKey = trim((string) config('permission.column_names.permission_pivot_key', 'permission_id'));
        if ($permissionPivotKey === '') {
            $permissionPivotKey = 'permission_id';
        }

        $rolePivotKey = trim((string) config('permission.column_names.role_pivot_key', 'role_id'));
        if ($rolePivotKey === '') {
            $rolePivotKey = 'role_id';
        }

        $permissionIds = $permissions
            ->map(static fn (Model $permission): string => (string) $permission->getKey())
            ->filter(static fn (string $id): bool => $id !== '')
            ->values()
            ->all();

        if ($permissionIds === []) {
            return [];
        }

        $pivotConnection = $this->resolvePivotConnection(
            $roleHasPermissionsTable,
            [$permissionConnection, $roleConnection, (string) config('database.default', 'mysql')]
        );
        if ($pivotConnection === null) {
            return [];
        }

        $pivotQuery = DB::connection($pivotConnection)->table($roleHasPermissionsTable)
            ->whereIn($permissionPivotKey, $permissionIds)
            ->whereNotNull($rolePivotKey);

        if (Schema::connection($pivotConnection)->hasColumn($roleHasPermissionsTable, 'status')) {
            $pivotQuery->where('status', 1);
        }

        $pivotRows = $pivotQuery->get([$permissionPivotKey, $rolePivotKey]);

        if ($pivotRows->isEmpty()) {
            return [];
        }

        $roleIds = $pivotRows
            ->map(fn (object $row): string => $this->normalizeScalar($row->{$rolePivotKey} ?? null))
            ->filter(static fn (string $id): bool => $id !== '')
            ->unique()
            ->values()
            ->all();

        if ($roleIds === []) {
            return [];
        }

        $roleQuery = $roleModelClass::query()->whereIn($roleKeyName, $roleIds);
        if ($guardFilter !== '' && Schema::connection($roleConnection)->hasColumn($roleTable, 'guard_name')) {
            $roleQuery->where('guard_name', $guardFilter);
        }

        /** @var Collection<int, Model> $roles */
        $roles = $roleQuery->get();
        $rolesById = [];

        foreach ($roles as $role) {
            $id = $this->normalizeScalar($role->getKey());
            if ($id === '') {
                continue;
            }

            $rolesById[$id] = $this->roleLabel($role);
        }

        $result = [];

        foreach ($pivotRows as $row) {
            $permissionId = $this->normalizeScalar($row->{$permissionPivotKey} ?? null);
            $roleId = $this->normalizeScalar($row->{$rolePivotKey} ?? null);

            if ($permissionId === '' || $roleId === '' || ! isset($rolesById[$roleId])) {
                continue;
            }

            $result[$permissionId] ??= [];
            $result[$permissionId][] = $rolesById[$roleId];
        }

        foreach ($result as $permissionId => $labels) {
            $labels = array_values(array_unique(array_filter(array_map(
                static fn (mixed $label): string => trim((string) $label),
                $labels
            ), static fn (string $label): bool => $label !== '')));

            sort($labels);
            $result[$permissionId] = $labels;
        }

        return $result;
    }

    protected function resolvePivotConnection(string $table, array $candidates): ?string
    {
        $normalizedCandidates = array_values(array_unique(array_filter(array_map(
            static fn (mixed $candidate): string => trim((string) $candidate),
            $candidates
        ), static fn (string $candidate): bool => $candidate !== '')));

        foreach ($normalizedCandidates as $connection) {
            if (Schema::connection($connection)->hasTable($table)) {
                return $connection;
            }
        }

        return null;
    }

    protected function roleLabel(Model $role): string
    {
        $roleKey = trim((string) ($role->getAttribute('role_key') ?? ''));
        if ($roleKey !== '') {
            return $roleKey;
        }

        $nameLocale = trim((string) ($role->getAttribute('name_locale') ?? ''));
        if ($nameLocale !== '') {
            return $nameLocale;
        }

        $name = $role->getAttribute('name');

        if (is_array($name)) {
            $locale = (string) app()->getLocale();
            $fallback = (string) config('app.fallback_locale', 'en');
            $name = $name[$locale] ?? $name[$fallback] ?? reset($name) ?? '';
        }

        $label = trim((string) $name);
        if ($label !== '') {
            return $label;
        }

        return (string) $role->getKey();
    }

    protected function normalizeScalar(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '';
    }

    protected function modelConnectionName(Model $model): string
    {
        $connection = trim((string) $model->getConnectionName());
        if ($connection !== '') {
            return $connection;
        }

        return (string) config('database.default', 'mysql');
    }
}
