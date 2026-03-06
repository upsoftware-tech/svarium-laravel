<?php

namespace Upsoftware\Svarium\Console\Commands\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

trait InteractsWithTenantTenancy
{
    protected function synchronizeModelHasRolesTenancySchema(bool $enableTenancy): void
    {
        $connection = $this->resolveConnectionForTenancySchemaSync();
        if ($connection === null) {
            return;
        }

        $table = trim((string) config('permission.table_names.model_has_roles', 'model_has_roles'));
        if ($table === '') {
            $table = 'model_has_roles';
        }

        if (! Schema::connection($connection)->hasTable($table)) {
            return;
        }

        if ($enableTenancy) {
            $this->enableModelHasRolesTenancySchema($connection, $table);
            return;
        }

        $this->disableModelHasRolesTenancySchema($connection, $table);
    }

    protected function enableModelHasRolesTenancySchema(string $connection, string $table): void
    {
        $useNumericTenantId = $this->tenantIdUsesNumericTypeForConnection($connection);

        if (! Schema::connection($connection)->hasColumn($table, 'tenant_id')) {
            try {
                Schema::connection($connection)->table($table, function (Blueprint $blueprint) use ($useNumericTenantId): void {
                    if ($useNumericTenantId) {
                        $blueprint->unsignedBigInteger('tenant_id')->nullable();
                        return;
                    }

                    $blueprint->string('tenant_id')->nullable();
                });
            } catch (Throwable $exception) {
                $this->warn("Nie udało się dodać kolumny tenant_id do {$table}: {$exception->getMessage()}");
                return;
            }
        } else {
            $this->alignModelHasRolesTenantIdType($connection, $table);
        }

        if (! $this->indexExists($connection, $table, 'model_has_roles_tenant_lookup_index')) {
            try {
                Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
                    $blueprint->index(['tenant_id', 'model_type', 'model_id'], 'model_has_roles_tenant_lookup_index');
                });
            } catch (Throwable $exception) {
                $this->warn("Nie udało się dodać indeksu model_has_roles_tenant_lookup_index: {$exception->getMessage()}");
            }
        }

        if (! $this->indexExists($connection, $table, 'model_has_roles_role_model_tenant_unique')) {
            try {
                Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
                    $blueprint->unique(
                        ['role_id', 'model_id', 'model_type', 'tenant_id'],
                        'model_has_roles_role_model_tenant_unique'
                    );
                });
            } catch (Throwable $exception) {
                $this->warn("Nie udało się dodać unikalnego indeksu model_has_roles_role_model_tenant_unique: {$exception->getMessage()}");
            }
        }

        if ($this->indexExists($connection, $table, 'PRIMARY')) {
            try {
                Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropPrimary();
                });
            } catch (Throwable $exception) {
                $this->warn("Nie udało się usunąć PRIMARY KEY z {$table}: {$exception->getMessage()}");
            }
        }

        $this->ensureModelHasRolesTenantForeignKey($connection, $table);
    }

    protected function disableModelHasRolesTenancySchema(string $connection, string $table): void
    {
        foreach ([
            'model_has_roles_tenant_foreign',
            'model_has_roles_tenant_id_foreign',
        ] as $foreignKey) {
            $this->dropForeignIfExists($connection, $table, $foreignKey);
        }

        $this->dropIndexIfExists($connection, $table, 'model_has_roles_role_model_tenant_unique', 'unique');
        $this->dropIndexIfExists($connection, $table, 'model_has_roles_tenant_lookup_index', 'index');

        if (! $this->indexExists($connection, $table, 'PRIMARY')) {
            try {
                Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
                    $blueprint->primary(['role_id', 'model_id', 'model_type']);
                });
            } catch (Throwable $exception) {
                $this->warn("Nie udało się przywrócić PRIMARY KEY w {$table}: {$exception->getMessage()}");
            }
        }

        if (Schema::connection($connection)->hasColumn($table, 'tenant_id')) {
            try {
                Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('tenant_id');
                });
            } catch (Throwable $exception) {
                $this->warn("Nie udało się usunąć kolumny tenant_id z {$table}: {$exception->getMessage()}");
            }
        }
    }

    protected function ensureModelHasRolesTenantForeignKey(string $connection, string $table): void
    {
        if (! Schema::connection($connection)->hasTable('tenants')) {
            return;
        }

        if (! Schema::connection($connection)->hasColumn($table, 'tenant_id')) {
            return;
        }

        if (! $this->tenantColumnsAreCompatibleForConnection($connection, $table, 'tenant_id')) {
            return;
        }

        if ($this->foreignKeyExists($connection, $table, 'model_has_roles_tenant_foreign')) {
            return;
        }

        try {
            Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
                $blueprint->foreign('tenant_id', 'model_has_roles_tenant_foreign')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();
            });
        } catch (Throwable $exception) {
            $this->warn("Nie udało się dodać FK model_has_roles_tenant_foreign: {$exception->getMessage()}");
        }
    }

    protected function alignModelHasRolesTenantIdType(string $connection, string $table): void
    {
        if (! $this->tenantColumnsAreCompatibleForConnection($connection, $table, 'tenant_id')) {
            $useNumericTenantId = $this->tenantIdUsesNumericTypeForConnection($connection);

            try {
                Schema::connection($connection)->table($table, function (Blueprint $blueprint) use ($useNumericTenantId): void {
                    if ($useNumericTenantId) {
                        $blueprint->unsignedBigInteger('tenant_id')->nullable()->change();
                        return;
                    }

                    $blueprint->string('tenant_id')->nullable()->change();
                });
            } catch (Throwable) {
                // Ignore conversion errors; FK creation will be skipped.
            }
        }
    }

    protected function resolveConnectionForTenancySchemaSync(): ?string
    {
        $preferred = trim((string) $this->centralConnectionName());
        if ($this->databaseConnectionConfigured($preferred)) {
            return $preferred;
        }

        $fallback = trim((string) config('database.default', ''));
        if ($this->databaseConnectionConfigured($fallback)) {
            return $fallback;
        }

        return null;
    }

    protected function databaseConnectionConfigured(string $connection): bool
    {
        if ($connection === '') {
            return false;
        }

        $config = config("database.connections.{$connection}");

        return is_array($config) && $config !== [];
    }

    protected function tenantIdUsesNumericTypeForConnection(string $connection): bool
    {
        if (! Schema::connection($connection)->hasTable('tenants')
            || ! Schema::connection($connection)->hasColumn('tenants', 'id')) {
            return true;
        }

        try {
            $type = strtolower((string) Schema::connection($connection)->getColumnType('tenants', 'id'));
        } catch (Throwable) {
            return true;
        }

        return $this->isNumericType($type);
    }

    protected function tenantColumnsAreCompatibleForConnection(string $connection, string $table, string $column): bool
    {
        if (! Schema::connection($connection)->hasTable('tenants')
            || ! Schema::connection($connection)->hasTable($table)) {
            return false;
        }

        if (! Schema::connection($connection)->hasColumn('tenants', 'id')
            || ! Schema::connection($connection)->hasColumn($table, $column)) {
            return false;
        }

        try {
            $tenantType = strtolower((string) Schema::connection($connection)->getColumnType('tenants', 'id'));
            $pivotType = strtolower((string) Schema::connection($connection)->getColumnType($table, $column));
        } catch (Throwable) {
            return false;
        }

        return $this->isNumericType($tenantType) === $this->isNumericType($pivotType);
    }

    protected function isNumericType(string $type): bool
    {
        return in_array($type, [
            'bigint',
            'biginteger',
            'unsignedbigint',
            'int',
            'integer',
            'mediumint',
            'smallint',
            'tinyint',
            'unsignedinteger',
        ], true);
    }

    protected function indexExists(string $connection, string $table, string $indexName): bool
    {
        try {
            $databaseName = (string) DB::connection($connection)->getDatabaseName();
            if ($databaseName === '') {
                return false;
            }

            return DB::connection($connection)
                ->table('information_schema.statistics')
                ->where('table_schema', $databaseName)
                ->where('table_name', $table)
                ->where('index_name', $indexName)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    protected function foreignKeyExists(string $connection, string $table, string $constraintName): bool
    {
        try {
            $databaseName = (string) DB::connection($connection)->getDatabaseName();
            if ($databaseName === '') {
                return false;
            }

            return DB::connection($connection)
                ->table('information_schema.table_constraints')
                ->where('table_schema', $databaseName)
                ->where('table_name', $table)
                ->where('constraint_name', $constraintName)
                ->where('constraint_type', 'FOREIGN KEY')
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    protected function dropForeignIfExists(string $connection, string $table, string $constraintName): void
    {
        if (! $this->foreignKeyExists($connection, $table, $constraintName)) {
            return;
        }

        try {
            DB::connection($connection)->statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraintName}`");
        } catch (Throwable) {
            // Ignore.
        }
    }

    protected function dropIndexIfExists(string $connection, string $table, string $indexName, string $type = 'index'): void
    {
        if (! $this->indexExists($connection, $table, $indexName)) {
            return;
        }

        try {
            Schema::connection($connection)->table($table, function (Blueprint $blueprint) use ($indexName, $type): void {
                if ($type === 'unique') {
                    $blueprint->dropUnique($indexName);
                    return;
                }

                $blueprint->dropIndex($indexName);
            });
        } catch (Throwable) {
            // Ignore.
        }
    }

    protected function tenantMode(): string
    {
        $mode = strtolower(trim((string) config('upsoftware.tenancy.mode', 'column')));

        return in_array($mode, ['column', 'database'], true)
            ? $mode
            : 'column';
    }

    protected function tenantMigrationsPaths(
        array $override = [],
        bool $includeSystem = true,
        bool $includeUser = true
    ): array
    {
        $paths = [];

        if ($includeSystem) {
            $systemPath = $this->packageTenantMigrationsPath();
            if ($systemPath !== null) {
                $paths[] = $systemPath;
            }

            foreach ($this->packageOptionalTenantMigrationsPaths() as $optionalPath) {
                if (! in_array($optionalPath, $paths, true)) {
                    $paths[] = $optionalPath;
                }
            }
        }

        if ($includeUser) {
            foreach ($this->userTenantMigrationsPaths($override) as $path) {
                if (! in_array($path, $paths, true)) {
                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }

    protected function userTenantMigrationsPaths(array $override = []): array
    {
        $paths = $override !== []
            ? $override
            : (array) (
                config('upsoftware.tenancy.paths.tenant_migrations')
                ?? config('upsoftware.tenancy.paths.migrations', app_path('Svarium/Tenancy/Migrations'))
            );

        $resolved = [];

        foreach ($paths as $path) {
            if (! is_string($path) || trim($path) === '') {
                continue;
            }

            $normalized = $this->normalizeAbsolutePath($path);

            if (! in_array($normalized, $resolved, true)) {
                $resolved[] = $normalized;
            }
        }

        if ($resolved === []) {
            $resolved[] = app_path('Svarium/Tenancy/Migrations');
        }

        return $resolved;
    }

    protected function packageTenantMigrationsPath(): ?string
    {
        $tenantsPath = realpath(__DIR__.'/../../../database/migrations/tenants');
        if (is_string($tenantsPath) && is_dir($tenantsPath)) {
            return $tenantsPath;
        }

        $tenancyPath = realpath(__DIR__.'/../../../database/migrations/tenancy');
        if (is_string($tenancyPath) && is_dir($tenancyPath)) {
            return $tenancyPath;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function packageOptionalTenantMigrationsPaths(): array
    {
        $paths = [];

        if ((bool) config('upsoftware.tenancy.owner.enabled', false)) {
            $ownerPath = realpath(__DIR__.'/../../../database/migrations/tenants-owner');
            if (is_string($ownerPath) && is_dir($ownerPath)) {
                $paths[] = $ownerPath;
            }
        }

        if ((bool) config('upsoftware.tenancy.profile.enabled', true)) {
            $profilePath = realpath(__DIR__.'/../../../database/migrations/tenants-profile');
            if (is_string($profilePath) && is_dir($profilePath)) {
                $paths[] = $profilePath;
            }
        }

        return $paths;
    }

    protected function tenantSeedersPath(?string $override = null): string
    {
        $path = $override !== null && trim($override) !== ''
            ? $override
            : (string) (
                config('upsoftware.tenancy.paths.tenant_seeders')
                ?? config('upsoftware.tenancy.paths.seeders', app_path('Svarium/Tenancy/Seeders'))
            );

        return $this->normalizeAbsolutePath($path);
    }

    protected function tenantSeederNamespace(?string $override = null): string
    {
        $namespace = $override !== null && trim($override) !== ''
            ? $override
            : (string) config('upsoftware.tenancy.seeders.namespace', 'App\\Svarium\\Tenancy\\Seeders');

        return trim($namespace, '\\');
    }

    protected function normalizeAbsolutePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return base_path();
        }

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    protected function ensureDirectory(string $path): void
    {
        if (! File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true, true);
        }
    }

    /**
     * @return EloquentCollection<int, Model>
     */
    protected function resolveTenants(array $tenantIds = []): EloquentCollection
    {
        $tenantModel = $this->resolveTenantModelClass();

        $query = $tenantModel::query();

        if ($tenantIds !== []) {
            $query->whereIn('id', $tenantIds);
        }

        return $query->get();
    }

    protected function resolveTenantModelClass(): string
    {
        $tenantModel = config('upsoftware.models.tenant');

        if (! is_string($tenantModel) || ! class_exists($tenantModel)) {
            throw new RuntimeException('Tenant model is not configured correctly in upsoftware.models.tenant');
        }

        return $tenantModel;
    }

    protected function configureRuntimeTenantConnection(Model $tenant, ?string $connectionName = null): ?string
    {
        $database = (string) ($tenant->getAttribute('tenancy_db_name') ?? $tenant->getAttribute('db_database') ?? '');
        $username = (string) ($tenant->getAttribute('tenancy_db_username') ?? $tenant->getAttribute('db_username') ?? '');
        $password = (string) ($tenant->getAttribute('tenancy_db_password') ?? $tenant->getAttribute('db_password') ?? '');
        $host = (string) ($tenant->getAttribute('tenancy_db_host') ?? $tenant->getAttribute('db_host') ?? '');
        $port = $tenant->getAttribute('tenancy_db_port') ?? $tenant->getAttribute('db_port') ?? null;

        if ($database === '' || $username === '' || $host === '') {
            return null;
        }

        $runtimeConnection = $connectionName
            ? trim($connectionName)
            : (string) config('upsoftware.tenancy.database.tenant_connection', 'tenant');

        if ($runtimeConnection === '') {
            $runtimeConnection = 'tenant';
        }

        $templateConnection = (string) config('upsoftware.tenancy.database.template_connection', config('database.default', 'mysql'));
        $templateConfig = config("database.connections.{$templateConnection}", []);

        if (! is_array($templateConfig) || $templateConfig === []) {
            $templateConfig = config('database.connections.mysql', []);
        }

        if (! is_array($templateConfig)) {
            $templateConfig = [];
        }

        $tenantConfig = [
            ...$templateConfig,
            'host' => $host,
            'database' => $database,
            'username' => $username,
            'password' => $password,
        ];

        if ($port !== null && $port !== '') {
            $tenantConfig['port'] = $port;
        }

        config(["database.connections.{$runtimeConnection}" => $tenantConfig]);
        app('db')->purge($runtimeConnection);

        return $runtimeConnection;
    }

    protected function centralConnectionName(): string
    {
        return (string) central_connection();
    }

    /**
     * @return array<int, string>
     */
    protected function resolveSeederClasses(array $inputSeeders = [], ?string $seedersPath = null, ?string $seedersNamespace = null): array
    {
        $namespace = $this->tenantSeederNamespace($seedersNamespace);
        $path = $this->tenantSeedersPath($seedersPath);

        if ($inputSeeders !== []) {
            $classes = [];

            foreach ($inputSeeders as $seeder) {
                if (! is_string($seeder) || trim($seeder) === '') {
                    continue;
                }

                $class = trim($seeder);

                if (! str_contains($class, '\\')) {
                    $class = $namespace.'\\'.Str::studly($class);
                }

                if (! in_array($class, $classes, true)) {
                    $classes[] = $class;
                }
            }

            return $classes;
        }

        return $this->discoverSeederClasses($path, $namespace);
    }

    /**
     * @return array<int, string>
     */
    protected function discoverSeederClasses(string $path, string $namespace): array
    {
        if (! File::isDirectory($path)) {
            return [];
        }

        $files = File::allFiles($path);
        $classes = [];

        foreach ($files as $file) {
            $filename = $file->getFilename();

            if (! str_ends_with($filename, 'Seeder.php')) {
                continue;
            }

            $relative = str_replace($path.DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
            $relativeClass = str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relative);
            $class = trim($namespace.'\\'.$relativeClass, '\\');

            if (! in_array($class, $classes, true)) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }
}
