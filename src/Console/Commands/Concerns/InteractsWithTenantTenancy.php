<?php

namespace Upsoftware\Svarium\Console\Commands\Concerns;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

trait InteractsWithTenantTenancy
{
    protected function tenantMode(): string
    {
        $mode = strtolower(trim((string) config('upsoftware.tenancy.mode', 'column')));

        return in_array($mode, ['column', 'database'], true)
            ? $mode
            : 'column';
    }

    protected function tenantMigrationsPaths(array $override = []): array
    {
        $paths = $override !== []
            ? $override
            : (array) config('upsoftware.tenancy.paths.migrations', app_path('Svarium/Tenancy/Migrations'));

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

    protected function tenantSeedersPath(?string $override = null): string
    {
        $path = $override !== null && trim($override) !== ''
            ? $override
            : (string) config('upsoftware.tenancy.paths.seeders', app_path('Svarium/Tenancy/Seeders'));

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
