<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Support\Facades\Schema;
use Upsoftware\Svarium\Console\Commands\Concerns\InteractsWithTenantTenancy;

class InstallTenantProfileCommand extends CoreCommand
{
    use InteractsWithTenantTenancy;

    protected $signature = 'svarium:tenant.install.profile
        {--enable= : Wymuś włączenie/wyłączenie profilu tenantu (true/false)}
        {--migrate= : Wymuś uruchomienie migracji create_tenant_profiles_table.php (true/false)}
        {--force : Force execution in production}';

    protected $description = 'Enable/disable tenant profile and optionally run create_tenant_profiles_table.php migration';
    protected $descriptionKey = 'tenant.install.profile';

    public function handle(): int
    {
        $enabled = $this->resolveEnabled();

        $this->addConfigKey('upsoftware.php', 'tenancy.profile.enabled', $enabled, true);
        config(['upsoftware.tenancy.profile.enabled' => $enabled]);

        if (! $enabled) {
            $this->info('Tenant profile: disabled');
            $this->warn('Po zmianie uruchom: php artisan optimize:clear');
            return self::SUCCESS;
        }

        $migrate = $this->resolveMigrate();

        if ($migrate) {
            $migrationPath = $this->profileMigrationPath();
            if ($migrationPath === null) {
                $this->error('Nie znaleziono migracji create_tenant_profiles_table.php w paczce.');
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
                $this->error('Migracja create_tenant_profiles_table.php zakończyła się błędem.');
                return $exitCode;
            }
        }

        $this->info('Tenant profile: enabled');
        if ($migrate) {
            $this->line('Migracja create_tenant_profiles_table.php wykonana.');
        }
        $this->warn('Po zmianie uruchom: php artisan optimize:clear');

        return self::SUCCESS;
    }

    protected function resolveEnabled(): bool
    {
        $default = (bool) config('upsoftware.tenancy.profile.enabled', true);
        $option = $this->normalizeBooleanOption($this->option('enable'));

        if ($option !== null) {
            return $option;
        }

        if (! $this->input->isInteractive()) {
            return $default;
        }

        return $this->confirm('Czy włączyć profil tenantu?', $default);
    }

    protected function resolveMigrate(): bool
    {
        $default = ! Schema::hasTable($this->profileTableName());
        $option = $this->normalizeBooleanOption($this->option('migrate'));

        if ($option !== null) {
            return $option;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        return $this->confirm(
            'Czy uruchomić migrację create_tenant_profiles_table.php?',
            $default
        );
    }

    protected function profileTableName(): string
    {
        $table = trim((string) config('upsoftware.tenancy.profile.table', 'tenant_profiles'));
        return $table !== '' ? $table : 'tenant_profiles';
    }

    protected function profileMigrationPath(): ?string
    {
        $candidates = [
            __DIR__.'/../../database/migrations/tenants-profile/2030_02_03_000008_create_tenant_profiles_table.php',
            __DIR__.'/../../database/migrations/tenants/2030_02_03_000008_create_tenant_profiles_table.php',
            __DIR__.'/../../database/migrations/tenancy/2030_02_03_000008_create_tenant_profiles_table.php',
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
