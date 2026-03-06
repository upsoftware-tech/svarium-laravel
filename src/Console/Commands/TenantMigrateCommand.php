<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Upsoftware\Svarium\Console\Commands\Concerns\InteractsWithTenantTenancy;

class TenantMigrateCommand extends CoreCommand
{
    use InteractsWithTenantTenancy;

    protected $signature = 'svarium:tenant.migrate
        {--tenant=* : Tenant IDs (database mode)}
        {--fresh : Run migrate:fresh instead of migrate}
        {--rollback : Run migrate:rollback instead of migrate}
        {--step=1 : Number of steps for rollback mode}
        {--all : Run for all tenants, ignore env filter}
        {--seed : Run tenant seeders after migrations}
        {--seeder=* : Seeder class(es) used with --seed}
        {--path=* : Override migration path(s)}
        {--force : Force execution in production}';

    protected $description = 'Run tenant migrations using built-in Svarium tenancy';
    protected $descriptionKey = 'tenant.migrate';

    public function handle(): int
    {
        $overridePaths = (array) $this->option('path');
        $userPaths = $this->userTenantMigrationsPaths($overridePaths);

        foreach ($userPaths as $path) {
            $this->ensureDirectory($path);
        }

        $mode = $this->tenantMode();
        $paths = $this->tenantMigrationsPaths(
            $overridePaths,
            // In database mode central tenancy tables (tenants/domains/model maps)
            // must stay on central DB and should not be migrated on tenant DBs.
            includeSystem: $mode !== 'database',
            includeUser: $mode === 'database'
        );

        if ($mode !== 'database' && $userPaths !== []) {
            $this->warn('Pominięto migracje użytkownika tenant (paths.tenant_migrations), bo tryb tenancy nie jest database.');
        }

        $fresh = (bool) $this->option('fresh');
        $rollback = (bool) $this->option('rollback');
        $step = max(1, (int) $this->option('step'));
        $seed = (bool) $this->option('seed');
        $seederInput = array_values(array_filter((array) $this->option('seeder')));
        $force = (bool) $this->option('force');

        if ($fresh && $rollback) {
            $this->error('Opcje --fresh i --rollback nie mogą być użyte jednocześnie.');
            return self::FAILURE;
        }

        if ($rollback && $seed) {
            $this->warn('Opcja --seed jest ignorowana w trybie --rollback.');
            $seed = false;
        }

        if ($mode === 'database') {
            return $this->migrateDatabaseTenants($paths, $fresh, $rollback, $step, $seed, $seederInput, $force);
        }

        return $this->migrateColumnMode($paths, $fresh, $rollback, $step, $seed, $seederInput, $force);
    }

    protected function migrateDatabaseTenants(
        array $paths,
        bool $fresh,
        bool $rollback,
        int $step,
        bool $seed,
        array $seederInput,
        bool $force
    ): int {
        $tenantIds = array_values(array_filter((array) $this->option('tenant')));
        $all = (bool) $this->option('all');

        try {
            $tenants = $this->resolveTenants($tenantIds);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $tenants = $this->filterTenantsByRuntimeEnvironment($tenants, $all);

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found for migration.');
            return self::SUCCESS;
        }

        $seeders = $seed && ! $rollback
            ? $this->resolveSeederClasses($seederInput)
            : [];

        if ($seed && ! $rollback && $seeders === []) {
            $this->warn('No tenant seeders found. Migration will run without seeding.');
        }

        $command = $rollback
            ? 'migrate:rollback'
            : ($fresh ? 'migrate:fresh' : 'migrate');

        foreach ($tenants as $tenant) {
            $connection = $this->configureRuntimeTenantConnection($tenant);

            if ($connection === null) {
                $this->warn("Skipping tenant [{$tenant->getKey()}]: missing database credentials.");
                continue;
            }

            $this->line("Running {$command} for tenant [{$tenant->getKey()}] on connection [{$connection}]...");

            $migrateParams = [
                '--database' => $connection,
                '--path' => $paths,
                '--realpath' => true,
                '--force' => $force,
            ];

            if ($rollback) {
                $migrateParams['--step'] = $step;
            }

            $exitCode = $this->call($command, $migrateParams);

            if ($exitCode !== self::SUCCESS) {
                $this->error("Migration failed for tenant [{$tenant->getKey()}].");
                return $exitCode;
            }

            if ($seed && ! $rollback && $seeders !== []) {
                if ($this->seedConnection($connection, $seeders, $force) !== self::SUCCESS) {
                    $this->error("Seeding failed for tenant [{$tenant->getKey()}].");
                    return self::FAILURE;
                }
            }
        }

        if ($rollback) {
            $this->info('Tenant rollback completed (database mode).');
        } else {
            $this->info('Tenant migration completed (database mode).');
        }

        return self::SUCCESS;
    }

    protected function migrateColumnMode(
        array $paths,
        bool $fresh,
        bool $rollback,
        int $step,
        bool $seed,
        array $seederInput,
        bool $force
    ): int {
        $connection = $this->centralConnectionName();
        $command = $rollback
            ? 'migrate:rollback'
            : ($fresh ? 'migrate:fresh' : 'migrate');

        $this->line("Running {$command} in column mode on connection [{$connection}]...");

        $migrateParams = [
            '--database' => $connection,
            '--path' => $paths,
            '--realpath' => true,
            '--force' => $force,
        ];

        if ($rollback) {
            $migrateParams['--step'] = $step;
        }

        $exitCode = $this->call($command, $migrateParams);

        if ($exitCode !== self::SUCCESS) {
            return $exitCode;
        }

        if ($rollback) {
            $this->info('Tenant rollback completed (column mode).');
            return self::SUCCESS;
        }

        if (! $seed) {
            $this->info('Tenant migration completed (column mode).');
            return self::SUCCESS;
        }

        $seeders = $this->resolveSeederClasses($seederInput);

        if ($seeders === []) {
            $this->warn('No tenant seeders found.');
            return self::SUCCESS;
        }

        return $this->seedConnection($connection, $seeders, $force);
    }

    /**
     * @param array<int, string> $seeders
     */
    protected function seedConnection(string $connection, array $seeders, bool $force): int
    {
        foreach ($seeders as $seederClass) {
            $this->line("Seeding [{$seederClass}] on connection [{$connection}]...");

            $exitCode = $this->call('db:seed', [
                '--database' => $connection,
                '--class' => $seederClass,
                '--force' => $force,
            ]);

            if ($exitCode !== self::SUCCESS) {
                return $exitCode;
            }
        }

        $this->info('Tenant seeding completed.');

        return self::SUCCESS;
    }

    /**
     * @param  EloquentCollection<int, Model>  $tenants
     * @return EloquentCollection<int, Model>
     */
    protected function filterTenantsByRuntimeEnvironment(EloquentCollection $tenants, bool $all): EloquentCollection
    {
        if ($all) {
            return $tenants;
        }

        try {
            $tenantModel = $this->resolveTenantModelClass();
            /** @var Model $tenantPrototype */
            $tenantPrototype = new $tenantModel();
            $table = $tenantPrototype->getTable();

            if (! Schema::hasColumn($table, 'env')) {
                $this->warn("Tabela [{$table}] nie zawiera kolumny env. Pomijam filtr APP_ENV.");
                return $tenants;
            }
        } catch (\Throwable) {
            return $tenants;
        }

        $runtimeEnvironment = $this->normalizeRuntimeEnvironment((string) app()->environment());
        $beforeCount = $tenants->count();

        $filtered = $tenants
            ->filter(function (Model $tenant) use ($runtimeEnvironment): bool {
                $tenantEnvironment = $tenant->getAttribute('env');
                $tenantEnvironment = $this->normalizeRuntimeEnvironment((string) $tenantEnvironment);

                return $tenantEnvironment === $runtimeEnvironment;
            })
            ->values();

        if ($beforeCount !== $filtered->count()) {
            $this->line("APP_ENV filter: {$runtimeEnvironment} (matched: {$filtered->count()}/{$beforeCount})");
        }

        return $filtered;
    }

    protected function normalizeRuntimeEnvironment(?string $value = null): string
    {
        $environment = strtolower(trim((string) ($value ?? app()->environment())));

        if (in_array($environment, ['prod', 'production'], true)) {
            return 'prod';
        }

        if ($environment === 'local') {
            return 'local';
        }

        return 'development';
    }
}
