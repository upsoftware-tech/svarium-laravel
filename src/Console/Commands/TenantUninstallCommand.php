<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Upsoftware\Svarium\Console\Commands\Concerns\InteractsWithTenantTenancy;

class TenantUninstallCommand extends CoreCommand
{
    use InteractsWithTenantTenancy;

    protected $signature = 'svarium:tenant.uninstall
        {--path=* : Dodatkowe ścieżki migracji tenant}
        {--force : Force execution in production}';

    protected $description = 'Wyłącza tenancy i wycofuje migracje tenant';

    public function handle(): int
    {
        $domainsWereEnabled = (bool) config('upsoftware.tenancy.column.model_maps.domains.enabled', true);
        $force = (bool) $this->option('force');
        $overridePaths = array_values(array_filter((array) $this->option('path')));

        if ($overridePaths !== []) {
            $exitCode = $this->call('migrate:reset', [
                '--database' => $this->centralConnectionName(),
                '--path' => $this->tenantMigrationsPaths($overridePaths),
                '--realpath' => true,
                '--force' => $force,
            ]);

            if ($exitCode !== self::SUCCESS) {
                $this->error('Nie udało się wycofać migracji tenant.');
                return $exitCode;
            }
        } else {
            $this->warn('Brak dodatkowych ścieżek migracji tenant do wycofania.');
        }

        $this->dropTenancyTables();
        $this->synchronizeDomainsTableName(false);

        $this->disableTenancyConfiguration($domainsWereEnabled);

        $this->info('Tenancy zostało wyłączone i odinstalowane.');
        $this->warn('Uruchom: php artisan optimize:clear');

        return self::SUCCESS;
    }

    protected function dropTenancyTables(): void
    {
        $profileTable = trim((string) config('upsoftware.tenancy.profile.table', 'tenant_profiles'));
        if ($profileTable === '') {
            $profileTable = 'tenant_profiles';
        }
        $profileForeignKey = trim((string) config('upsoftware.tenancy.profile.foreign_key', 'tenant_id'));
        if ($profileForeignKey === '') {
            $profileForeignKey = 'tenant_id';
        }

        $this->dropDomainsTenantForeignKey('tenant_domains');
        $this->dropDomainsTenantForeignKey('domains');
        $this->dropProfileTenantForeignKey($profileTable, $profileForeignKey);

        Schema::dropIfExists($profileTable);
        Schema::dropIfExists('model_has_domain_tenants');
        Schema::dropIfExists('model_has_domains');
        Schema::dropIfExists('model_has_tenants');
        Schema::dropIfExists('model_has_tenant');
        Schema::dropIfExists('tenants');
    }

    protected function synchronizeDomainsTableName(bool $tenancyEnabled): void
    {
        $source = $tenancyEnabled ? 'domains' : 'tenant_domains';
        $target = $tenancyEnabled ? 'tenant_domains' : 'domains';

        try {
            if (Schema::hasTable($source) && ! Schema::hasTable($target)) {
                Schema::rename($source, $target);
                $this->line("Tabela {$source} została przemianowana na {$target}.");
            }
        } catch (Throwable $exception) {
            $this->warn('Nie udało się znormalizować tabeli domen: '.$exception->getMessage());
        }
    }

    protected function dropDomainsTenantForeignKey(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ([
            "{$table}_tenant_foreign",
            "{$table}_tenant_id_foreign",
            'domains_tenant_foreign',
            'domains_tenant_id_foreign',
            'tenant_domains_tenant_id_foreign',
        ] as $key) {
            try {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$key}`");
            } catch (Throwable) {
                // Ignore missing keys.
            }
        }
    }

    protected function dropProfileTenantForeignKey(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        foreach ([
            "{$table}_{$column}_foreign",
            "{$table}_tenant_foreign",
            "{$table}_tenant_id_foreign",
        ] as $key) {
            try {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$key}`");
            } catch (Throwable) {
                // Ignore missing keys.
            }
        }
    }

    protected function disableTenancyConfiguration(bool $keepDomains): void
    {
        $this->addEnvKey('SVARIUM_TENANCY_ENABLED', 'false');
        $this->addEnvKey('SVARIUM_TENANCY_DOMAINS_ENABLED', $keepDomains ? 'true' : 'false');

        $this->addConfigKey('upsoftware.php', 'tenancy.enabled', false, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.domains.enabled', $keepDomains, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.column.model_maps.tenants.enabled', false, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.column.model_maps.domains.enabled', $keepDomains, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.column.model_maps.domains.table', 'model_has_domains', true);
        $this->addConfigKey('upsoftware.php', 'tenancy.column.model_maps.domains.domain_key', 'domain_id', true);
    }
}
