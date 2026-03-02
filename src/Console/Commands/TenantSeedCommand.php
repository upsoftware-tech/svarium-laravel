<?php

namespace Upsoftware\Svarium\Console\Commands;

use RuntimeException;
use Upsoftware\Svarium\Console\Commands\Concerns\InteractsWithTenantTenancy;

class TenantSeedCommand extends CoreCommand
{
    use InteractsWithTenantTenancy;

    protected $signature = 'svarium:tenant.seed
        {--tenant=* : Tenant IDs (database mode)}
        {--seeder=* : Seeder class(es); if empty, all tenant seeders are discovered}
        {--path= : Override tenant seeders path}
        {--namespace= : Override tenant seeders namespace}
        {--force : Force execution in production}';

    protected $description = 'Seed tenant databases using built-in Svarium tenancy';

    public function handle(): int
    {
        $seeders = $this->resolveSeederClasses(
            array_values(array_filter((array) $this->option('seeder'))),
            is_string($this->option('path')) ? $this->option('path') : null,
            is_string($this->option('namespace')) ? $this->option('namespace') : null,
        );

        if ($seeders === []) {
            $this->warn('No tenant seeders found.');
            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');

        if ($this->tenantMode() === 'database') {
            return $this->seedDatabaseTenants($seeders, $force);
        }

        return $this->seedColumnMode($seeders, $force);
    }

    /**
     * @param array<int, string> $seeders
     */
    protected function seedDatabaseTenants(array $seeders, bool $force): int
    {
        $tenantIds = array_values(array_filter((array) $this->option('tenant')));

        try {
            $tenants = $this->resolveTenants($tenantIds);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found for seeding.');
            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $connection = $this->configureRuntimeTenantConnection($tenant);

            if ($connection === null) {
                $this->warn("Skipping tenant [{$tenant->getKey()}]: missing database credentials.");
                continue;
            }

            if ($this->seedConnection($connection, $seeders, $force) !== self::SUCCESS) {
                $this->error("Seeding failed for tenant [{$tenant->getKey()}].");
                return self::FAILURE;
            }
        }

        $this->info('Tenant seeding completed (database mode).');

        return self::SUCCESS;
    }

    /**
     * @param array<int, string> $seeders
     */
    protected function seedColumnMode(array $seeders, bool $force): int
    {
        $connection = $this->centralConnectionName();

        if ($this->seedConnection($connection, $seeders, $force) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->info('Tenant seeding completed (column mode).');

        return self::SUCCESS;
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

        return self::SUCCESS;
    }
}
