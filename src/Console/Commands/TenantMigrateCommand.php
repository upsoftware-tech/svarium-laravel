<?php

namespace Upsoftware\Svarium\Console\Commands;

use RuntimeException;
use Upsoftware\Svarium\Console\Commands\Concerns\InteractsWithTenantTenancy;

class TenantMigrateCommand extends CoreCommand
{
    use InteractsWithTenantTenancy;

    protected $signature = 'svarium:tenant.migrate
        {--tenant=* : Tenant IDs (database mode)}
        {--fresh : Run migrate:fresh instead of migrate}
        {--seed : Run tenant seeders after migrations}
        {--seeder=* : Seeder class(es) used with --seed}
        {--path=* : Override migration path(s)}
        {--force : Force execution in production}';

    protected $description = 'Run tenant migrations using built-in Svarium tenancy';

    public function handle(): int
    {
        $paths = $this->tenantMigrationsPaths((array) $this->option('path'));

        foreach ($paths as $path) {
            $this->ensureDirectory($path);
        }

        $mode = $this->tenantMode();
        $fresh = (bool) $this->option('fresh');
        $seed = (bool) $this->option('seed');
        $seederInput = array_values(array_filter((array) $this->option('seeder')));
        $force = (bool) $this->option('force');

        if ($mode === 'database') {
            return $this->migrateDatabaseTenants($paths, $fresh, $seed, $seederInput, $force);
        }

        return $this->migrateColumnMode($paths, $fresh, $seed, $seederInput, $force);
    }

    protected function migrateDatabaseTenants(
        array $paths,
        bool $fresh,
        bool $seed,
        array $seederInput,
        bool $force
    ): int {
        $tenantIds = array_values(array_filter((array) $this->option('tenant')));

        try {
            $tenants = $this->resolveTenants($tenantIds);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found for migration.');
            return self::SUCCESS;
        }

        $seeders = $seed
            ? $this->resolveSeederClasses($seederInput)
            : [];

        if ($seed && $seeders === []) {
            $this->warn('No tenant seeders found. Migration will run without seeding.');
        }

        $command = $fresh ? 'migrate:fresh' : 'migrate';

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

            $exitCode = $this->call($command, $migrateParams);

            if ($exitCode !== self::SUCCESS) {
                $this->error("Migration failed for tenant [{$tenant->getKey()}].");
                return $exitCode;
            }

            if ($seed && $seeders !== []) {
                if ($this->seedConnection($connection, $seeders, $force) !== self::SUCCESS) {
                    $this->error("Seeding failed for tenant [{$tenant->getKey()}].");
                    return self::FAILURE;
                }
            }
        }

        $this->info('Tenant migration completed (database mode).');

        return self::SUCCESS;
    }

    protected function migrateColumnMode(
        array $paths,
        bool $fresh,
        bool $seed,
        array $seederInput,
        bool $force
    ): int {
        $connection = $this->centralConnectionName();
        $command = $fresh ? 'migrate:fresh' : 'migrate';

        $this->line("Running {$command} in column mode on connection [{$connection}]...");

        $migrateParams = [
            '--database' => $connection,
            '--path' => $paths,
            '--realpath' => true,
            '--force' => $force,
        ];

        $exitCode = $this->call($command, $migrateParams);

        if ($exitCode !== self::SUCCESS) {
            return $exitCode;
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
}
