<?php

namespace Upsoftware\Svarium\Console\Commands;

use Upsoftware\Svarium\Console\Commands\Concerns\InteractsWithTenantTenancy;

class InstallTenantOwnerCommand extends CoreCommand
{
    use InteractsWithTenantTenancy;

    protected $signature = 'svarium:tenant.install.owner
        {--enable= : Wymuś włączenie/wyłączenie owner tenantu (true/false)}
        {--migrate= : Wymuś uruchomienie migracji add_tenant_owner_columns.php (true/false)}
        {--force : Force execution in production}';

    protected $description = 'Enable/disable tenant owner binding and optionally run add_tenant_owner_columns.php migration';
    protected $descriptionKey = 'tenant.install.owner';

    public function handle(): int
    {
        $enabled = $this->resolveEnabled();

        $this->addConfigKey('upsoftware.php', 'tenancy.owner.enabled', $enabled, true);
        config(['upsoftware.tenancy.owner.enabled' => $enabled]);

        if (! $enabled) {
            $this->info('Tenant owner: disabled');
            $this->warn('Po zmianie uruchom: php artisan optimize:clear');
            return self::SUCCESS;
        }

        $migrate = $this->resolveMigrate();

        if ($migrate) {
            $migrationPath = $this->ownerMigrationPath();
            if ($migrationPath === null) {
                $this->error('Nie znaleziono migracji add_tenant_owner_columns.php w paczce.');
                return self::FAILURE;
            }

            $connection = $this->centralConnectionName();
            $exitCode = $this->call('migrate', [
                '--database' => $connection,
                '--path' => $migrationPath,
                '--realpath' => true,
                '--force' => (bool) $this->option('force'),
            ]);

            if ($exitCode !== self::SUCCESS) {
                $this->error('Migracja add_tenant_owner_columns.php zakończyła się błędem.');
                return $exitCode;
            }
        }

        $this->info('Tenant owner: enabled');
        if ($migrate) {
            $this->line('Migracja add_tenant_owner_columns.php wykonana.');
        }
        $this->warn('Po zmianie uruchom: php artisan optimize:clear');

        return self::SUCCESS;
    }

    protected function resolveEnabled(): bool
    {
        $default = (bool) config('upsoftware.tenancy.owner.enabled', false);
        $option = $this->normalizeBooleanOption($this->option('enable'));

        if ($option !== null) {
            return $option;
        }

        if (! $this->input->isInteractive()) {
            return $default;
        }

        return $this->confirm('Czy włączyć owner tenantu?', $default);
    }

    protected function resolveMigrate(): bool
    {
        $option = $this->normalizeBooleanOption($this->option('migrate'));

        if ($option !== null) {
            return $option;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        return $this->confirm(
            'Czy uruchomić migrację add_tenant_owner_columns.php?',
            true
        );
    }

    protected function ownerMigrationPath(): ?string
    {
        $candidates = [
            __DIR__.'/../../database/migrations/tenants-owner/2030_02_03_000007_add_tenant_owner_columns.php',
            __DIR__.'/../../database/migrations/tenants/2030_02_03_000007_add_tenant_owner_columns.php',
            __DIR__.'/../../database/migrations/tenancy/2030_02_03_000007_add_tenant_owner_columns.php',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function normalizeBooleanOption(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'n', 'off'], true)) {
            return false;
        }

        return null;
    }
}
