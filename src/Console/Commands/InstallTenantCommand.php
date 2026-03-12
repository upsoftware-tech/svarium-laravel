<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;
use Upsoftware\Svarium\Console\Commands\Concerns\InteractsWithTenantTenancy;
use Winter\LaravelConfigWriter\ArrayFile;
use function Laravel\Prompts\select;

class InstallTenantCommand extends CoreCommand
{
    use InteractsWithTenantTenancy;

    protected $signature = 'svarium:tenant.install
        {--central=central : Nazwa połączenia centralnego}
        {--tenant=tenant : Nazwa połączenia tenant}
        {--template= : Istniejące połączenie bazowe (np. mysql)}
        {--mode= : Tryb tenancy (database|column)}
        {--enable-tenancy= : Wymuś włączenie/wyłączenie tenancy (true/false)}
        {--enable-domains= : Wymuś włączenie/wyłączenie domen tenancy (true/false)}
        {--owner-enabled= : Wymuś włączenie/wyłączenie powiązania owner tenantu}
        {--owner-map= : Mapowanie ownerów np. customer=App\\Models\\Customer,company=App\\Models\\UserCompany}
        {--profile-enabled= : Wymuś włączenie/wyłączenie profilu tenantu}
        {--profile-table= : Nazwa tabeli profilu tenantu}
        {--profile-foreign-key= : Nazwa FK do tenants.id w tabeli profilu}
        {--profile-model= : Model profilu tenantu}
        {--migrate-tenancy : Uruchom migracje tabel tenancy}
        {--migrate-domains : Uruchom migracje tabel domen tenancy}
        {--force : Force execution in production}';

    protected $description = 'Configure central/tenant connections in config/database.php';
    protected $descriptionKey = 'tenant.install.default';

    public function handle(): int
    {
        $databasePath = config_path('database.php');

        if (! is_file($databasePath)) {
            $this->error('Brak pliku config/database.php');
            return self::FAILURE;
        }

        $availableConnections = array_keys((array) config('database.connections', []));
        if ($availableConnections === []) {
            $this->error('Brak zdefiniowanych połączeń w config/database.php');
            return self::FAILURE;
        }

        $centralConnection = trim((string) $this->option('central'));
        $tenantConnection = trim((string) $this->option('tenant'));

        if ($centralConnection === '' || $tenantConnection === '') {
            $this->error('Opcje --central i --tenant nie mogą być puste.');
            return self::FAILURE;
        }

        $templateConnection = trim((string) $this->option('template'));
        if ($templateConnection === '') {
            $defaultConnection = (string) config('database.default', 'mysql');
            $templateConnection = in_array($defaultConnection, $availableConnections, true)
                ? $defaultConnection
                : $availableConnections[0];

            if ($this->input->isInteractive()) {
                $templateConnection = select(
                    label: 'Wybierz bazowe połączenie do skopiowania',
                    options: array_combine($availableConnections, $availableConnections),
                    default: $templateConnection
                );
            }
        }

        if (! in_array($templateConnection, $availableConnections, true)) {
            $this->error("Połączenie bazowe [{$templateConnection}] nie istnieje.");
            return self::FAILURE;
        }

        $templateConfig = config("database.connections.{$templateConnection}");
        if (! is_array($templateConfig) || $templateConfig === []) {
            $this->error("Konfiguracja połączenia [{$templateConnection}] jest pusta.");
            return self::FAILURE;
        }

        $addedCentralEnvKeys = $this->ensureConnectionEnvKeys(
            envPrefix: 'SVARIUM_CENTRAL',
            template: $templateConfig
        );
        $addedTenantEnvKeys = $this->ensureConnectionEnvKeys(
            envPrefix: 'SVARIUM_TENANT',
            template: $templateConfig
        );

        try {
            $config = ArrayFile::open($databasePath);

            $config->set("connections.{$centralConnection}", $this->makeConnectionConfig(
                $config,
                $templateConfig,
                'SVARIUM_CENTRAL'
            ));

            $config->set("connections.{$tenantConnection}", $this->makeConnectionConfig(
                $config,
                $templateConfig,
                'SVARIUM_TENANT'
            ));

            $config->write();
        } catch (RuntimeException $exception) {
            $this->error('Nie udało się zapisać config/database.php: '.$exception->getMessage());
            return self::FAILURE;
        }

        $this->addConfigKey('upsoftware.php', 'tenancy.database.central_connection', $centralConnection, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.database.tenant_connection', $tenantConnection, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.database.template_connection', $templateConnection, true);

        $tenancyMode = $this->resolveTenancyMode();
        $enableTenancy = $this->resolveEnableTenancy();
        $enableDomains = $this->resolveEnableDomains();
        $ownerEnabled = $this->resolveOwnerEnabled($enableTenancy);
        $ownerMap = $this->resolveOwnerMap($ownerEnabled);
        $profileEnabled = $this->resolveProfileEnabled($enableTenancy);
        $profileTable = $this->resolveProfileTable($profileEnabled);
        $profileForeignKey = $this->resolveProfileForeignKey($profileEnabled);
        $profileModel = $this->resolveProfileModel($profileEnabled);
        $migrateTenancy = false;
        $migrateDomains = false;

        if ($enableTenancy) {
            $migrateTenancy = $this->resolveMigrateTenancy();
        }

        if ($enableDomains) {
            $migrateDomains = $this->resolveMigrateDomains();
        }

        $this->persistTenancyToggle(
            tenancyMode: $tenancyMode,
            enableTenancy: $enableTenancy,
            enableDomains: $enableDomains,
            ownerEnabled: $ownerEnabled,
            ownerMap: $ownerMap,
            profileEnabled: $profileEnabled,
            profileTable: $profileTable,
            profileForeignKey: $profileForeignKey,
            profileModel: $profileModel
        );
        $this->synchronizeDomainsTableName($enableTenancy);

        if ($enableTenancy && $migrateDomains && ! $migrateTenancy && ! Schema::hasTable('tenants')) {
            $migrateTenancy = true;
            $this->warn('Włączono migracje domen, więc uruchamiam też migracje bazowe tenancy (brak tabeli tenants).');
        }

        if ($migrateTenancy || $migrateDomains) {
            $migrationExitCode = $this->runTenancyMigrations(
                migrateTenancy: $migrateTenancy,
                migrateDomains: $migrateDomains,
                enableTenancy: $enableTenancy,
                ownerEnabled: $ownerEnabled,
                profileEnabled: $profileEnabled,
                force: (bool) $this->option('force')
            );
            if ($migrationExitCode !== self::SUCCESS) {
                return $migrationExitCode;
            }
        }

        $this->synchronizeModelHasRolesTenancySchema($enableTenancy);

        $this->info('Dodano/odświeżono konfigurację tenancy w config/database.php');
        $this->line("Central connection: {$centralConnection}");
        $this->line("Tenant connection: {$tenantConnection}");
        $this->line("Template connection: {$templateConnection}");
        $this->line("Tenancy mode: {$tenancyMode}");
        $this->line('Tenancy enabled: '.($enableTenancy ? 'true' : 'false'));
        $this->line('Domains enabled: '.($enableDomains ? 'true' : 'false'));
        if ($addedCentralEnvKeys !== []) {
            $this->line('Dodano brakujące klucze ENV (CENTRAL): '.implode(', ', $addedCentralEnvKeys));
        }
        if ($addedTenantEnvKeys !== []) {
            $this->line('Dodano brakujące klucze ENV (TENANT): '.implode(', ', $addedTenantEnvKeys));
        }
        $this->line('Tenant owner binding enabled: '.($ownerEnabled ? 'true' : 'false'));
        $this->line('Tenant profile enabled: '.($profileEnabled ? 'true' : 'false'));
        if ($profileEnabled) {
            $this->line("Tenant profile table: {$profileTable}");
            $this->line("Tenant profile FK: {$profileForeignKey}");
        }

        $this->newLine();
        $this->warn('Po zmianie uruchom: php artisan optimize:clear');

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $template
     * @return array<string, mixed>
     */
    protected function makeConnectionConfig(ArrayFile $config, array $template, string $envPrefix): array
    {
        $connection = $template;
        $safeFallbacks = $this->safeConnectionFallbacks($template, $envPrefix);

        $map = [
            'driver' => "{$envPrefix}_DB_DRIVER",
            'url' => "{$envPrefix}_DB_URL",
            'host' => "{$envPrefix}_DB_HOST",
            'port' => "{$envPrefix}_DB_PORT",
            'database' => "{$envPrefix}_DB_DATABASE",
            'username' => "{$envPrefix}_DB_USERNAME",
            'password' => "{$envPrefix}_DB_PASSWORD",
            'unix_socket' => "{$envPrefix}_DB_SOCKET",
            'charset' => "{$envPrefix}_DB_CHARSET",
            'collation' => "{$envPrefix}_DB_COLLATION",
        ];

        foreach ($map as $key => $envKey) {
            if (! array_key_exists($key, $connection)) {
                continue;
            }

            $fallback = $safeFallbacks[$key] ?? $connection[$key];
            $connection[$key] = $config->constant($this->envExpression($envKey, $fallback));
        }

        return $connection;
    }

    /**
     * @param array<string, mixed> $template
     * @return array<string, mixed>
     */
    protected function safeConnectionFallbacks(array $template, string $envPrefix): array
    {
        $driver = trim((string) ($template['driver'] ?? 'mysql'));
        if ($driver === '') {
            $driver = 'mysql';
        }

        $charset = trim((string) ($template['charset'] ?? 'utf8mb4'));
        if ($charset === '') {
            $charset = 'utf8mb4';
        }

        $collation = trim((string) ($template['collation'] ?? 'utf8mb4_unicode_ci'));
        if ($collation === '') {
            $collation = 'utf8mb4_unicode_ci';
        }

        $defaultDatabase = str_contains($envPrefix, 'TENANT')
            ? 'svarium_tenant'
            : 'svarium_central';

        return [
            'driver' => $driver,
            'url' => $this->normalizeNullableString($template['url'] ?? null),
            'host' => $this->normalizeNonEmptyString($template['host'] ?? null, '127.0.0.1'),
            'port' => $this->normalizeNonEmptyString($template['port'] ?? null, '3306'),
            'database' => $this->normalizeNonEmptyString($template['database'] ?? null, $defaultDatabase),
            'username' => $this->normalizeNonEmptyString($template['username'] ?? null, 'svarium'),
            'password' => (string) ($template['password'] ?? ''),
            'unix_socket' => (string) ($template['unix_socket'] ?? ''),
            'charset' => $charset,
            'collation' => $collation,
        ];
    }

    protected function envExpression(string $envKey, mixed $default): string
    {
        if ($default === null) {
            return "env('{$envKey}')";
        }

        if (is_bool($default)) {
            return "env('{$envKey}', ".($default ? 'true' : 'false').')';
        }

        if (is_int($default) || is_float($default)) {
            return "env('{$envKey}', {$default})";
        }

        $value = str_replace("'", "\\'", (string) $default);

        return "env('{$envKey}', '{$value}')";
    }

    /**
     * @param array<string, mixed> $template
     * @return list<string>
     */
    protected function ensureConnectionEnvKeys(string $envPrefix, array $template): array
    {
        $map = [
            'DB_DRIVER' => $this->normalizeNonEmptyString($template['driver'] ?? null, 'mysql'),
            'DB_URL' => $this->normalizeNullableString($template['url'] ?? null) ?? '',
            'DB_HOST' => $this->normalizeNonEmptyString($template['host'] ?? null, '127.0.0.1'),
            'DB_PORT' => $this->normalizeNonEmptyString($template['port'] ?? null, '3306'),
            'DB_DATABASE' => $this->normalizeNonEmptyString(
                $template['database'] ?? null,
                str_contains($envPrefix, 'TENANT') ? 'svarium_tenant' : 'svarium_central'
            ),
            'DB_USERNAME' => $this->normalizeNonEmptyString($template['username'] ?? null, 'root'),
            'DB_PASSWORD' => (string) ($template['password'] ?? ''),
            'DB_SOCKET' => (string) ($template['unix_socket'] ?? ''),
            'DB_CHARSET' => $this->normalizeNonEmptyString($template['charset'] ?? null, 'utf8mb4'),
            'DB_COLLATION' => $this->normalizeNonEmptyString($template['collation'] ?? null, 'utf8mb4_unicode_ci'),
        ];

        $existing = $this->readExistingEnvKeys();
        $added = [];

        foreach ($map as $suffix => $value) {
            $envKey = "{$envPrefix}_{$suffix}";

            if (isset($existing[$envKey])) {
                continue;
            }

            $this->addEnvKey($envKey, $value);
            $existing[$envKey] = true;
            $added[] = $envKey;
        }

        return $added;
    }

    /**
     * @return array<string, bool>
     */
    protected function readExistingEnvKeys(): array
    {
        $path = base_path('.env');
        if (! is_file($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if (! is_string($content) || $content === '') {
            return [];
        }

        preg_match_all('/^\s*([A-Z0-9_]+)\s*=/m', $content, $matches);

        $keys = [];
        foreach ((array) ($matches[1] ?? []) as $key) {
            $normalized = trim((string) $key);
            if ($normalized !== '') {
                $keys[$normalized] = true;
            }
        }

        return $keys;
    }

    protected function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    protected function normalizeNonEmptyString(mixed $value, string $default): string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : $default;
    }

    protected function resolveEnableTenancy(): bool
    {
        $default = (bool) config('upsoftware.tenancy.enabled', false);
        $value = $this->normalizeBooleanOption($this->option('enable-tenancy'));

        if ($value !== null) {
            return $value;
        }

        if (! $this->input->isInteractive()) {
            return $default;
        }

        return $this->confirm('Czy włączyć tenancy?', $default);
    }

    protected function resolveEnableDomains(): bool
    {
        $default = (bool) config('upsoftware.tenancy.column.model_maps.domains.enabled', true);
        $value = $this->normalizeBooleanOption($this->option('enable-domains'));

        if ($value !== null) {
            return $value;
        }

        if (! $this->input->isInteractive()) {
            return $default;
        }

        return $this->confirm('Czy włączyć domeny tenancy?', $default);
    }

    protected function resolveMigrateTenancy(): bool
    {
        if ((bool) $this->option('migrate-tenancy')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        return $this->confirm('Czy utworzyć tabele tenancy (migracje)?', true);
    }

    protected function resolveMigrateDomains(): bool
    {
        if ((bool) $this->option('migrate-domains')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        return $this->confirm('Czy utworzyć tabele domen tenancy (migracje)?', true);
    }

    protected function resolveTenancyMode(): string
    {
        $default = strtolower(trim((string) config('upsoftware.tenancy.mode', 'column')));
        if (! in_array($default, ['database', 'column'], true)) {
            $default = 'column';
        }

        $option = strtolower(trim((string) $this->option('mode')));
        if (in_array($option, ['database', 'column'], true)) {
            return $option;
        }

        if (! $this->input->isInteractive()) {
            return $default;
        }

        return (string) select(
            label: 'Wybierz tryb tenancy',
            options: [
                'column' => 'column (jedna baza + tenant_id)',
                'database' => 'database (osobna baza danych per tenant)',
            ],
            default: $default
        );
    }

    protected function persistTenancyToggle(
        string $tenancyMode,
        bool $enableTenancy,
        bool $enableDomains,
        bool $ownerEnabled,
        array $ownerMap,
        bool $profileEnabled,
        string $profileTable,
        string $profileForeignKey,
        string $profileModel
    ): void {
        $this->addEnvKey('SVARIUM_TENANCY_ENABLED', $enableTenancy ? 'true' : 'false');
        $this->addEnvKey('SVARIUM_TENANCY_MODE', $tenancyMode);
        $this->addEnvKey('SVARIUM_TENANCY_DOMAINS_ENABLED', $enableDomains ? 'true' : 'false');

        $this->addConfigKey('upsoftware.php', 'tenancy.mode', $tenancyMode, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.enabled', $enableTenancy, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.domains.enabled', $enableDomains, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.column.model_maps.tenants.enabled', $enableTenancy, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.column.model_maps.domains.enabled', $enableDomains, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.owner.enabled', $ownerEnabled, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.owner.map', $ownerMap, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.profile.enabled', $profileEnabled, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.profile.table', $profileTable, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.profile.foreign_key', $profileForeignKey, true);
        $this->addConfigKey('upsoftware.php', 'tenancy.profile.model', $profileModel, true);

        if ($enableDomains) {
            $this->addConfigKey('upsoftware.php', 'tenancy.column.model_maps.domains.table', 'model_has_domains', true);
            $this->addConfigKey('upsoftware.php', 'tenancy.column.model_maps.domains.domain_key', 'domain_id', true);
        }

        config([
            'upsoftware.tenancy.mode' => $tenancyMode,
            'upsoftware.tenancy.enabled' => $enableTenancy,
            'upsoftware.tenancy.domains.enabled' => $enableDomains,
            'upsoftware.tenancy.column.model_maps.tenants.enabled' => $enableTenancy,
            'upsoftware.tenancy.column.model_maps.domains.enabled' => $enableDomains,
            'upsoftware.tenancy.owner.enabled' => $ownerEnabled,
            'upsoftware.tenancy.owner.map' => $ownerMap,
            'upsoftware.tenancy.profile.enabled' => $profileEnabled,
            'upsoftware.tenancy.profile.table' => $profileTable,
            'upsoftware.tenancy.profile.foreign_key' => $profileForeignKey,
            'upsoftware.tenancy.profile.model' => $profileModel,
        ]);
    }

    protected function runTenancyMigrations(
        bool $migrateTenancy,
        bool $migrateDomains,
        bool $enableTenancy,
        bool $ownerEnabled,
        bool $profileEnabled,
        bool $force
    ): int
    {
        $base = $this->packageTenantMigrationsPath();
        if (! is_string($base) || $base === '' || ! is_dir($base)) {
            $this->error('Brak katalogu migracji tenancy w paczce.');
            return self::FAILURE;
        }

        $files = [];

        if ($migrateTenancy) {
            $files[] = $this->packageMigrationFilePath('2030_02_02_000001_create_tenants_table.php');
            $files[] = $this->packageMigrationFilePath('2030_02_02_000003_create_model_has_tenants_table.php');
            if ($ownerEnabled) {
                $files[] = $this->packageMigrationFilePath('2030_02_03_000007_add_tenant_owner_columns.php');
            }
            if ($profileEnabled) {
                $files[] = $this->packageMigrationFilePath('2030_02_03_000008_create_tenant_profiles_table.php');
            }
        }

        if ($migrateDomains && $enableTenancy) {
            $files[] = $this->packageMigrationFilePath('2030_02_02_000002_create_tenant_domains_table.php');
            $files[] = $this->packageMigrationFilePath('2030_02_02_000004_create_model_has_domain_tenants_table.php');
        }

        if ($migrateDomains) {
            $files[] = $this->packageMigrationFilePath('2030_02_03_000005_rename_tenant_domain_tables.php');
            $files[] = $this->packageMigrationFilePath('2030_02_03_000006_add_domain_context_columns.php');
        }

        $files = array_values(array_unique(array_filter($files, static fn ($file) => is_string($file) && is_file($file))));
        if ($files === []) {
            return self::SUCCESS;
        }

        $connection = $this->centralConnectionName();

        foreach ($files as $file) {
            $this->prepareTenantMigrationReplayIfNeeded($connection, $file);
            $this->line('Migracja tenancy: '.$file);

            $exitCode = $this->call('migrate', [
                '--database' => $connection,
                '--path' => $file,
                '--realpath' => true,
                '--force' => $force,
            ]);

            if ($exitCode !== self::SUCCESS) {
                $this->error('Błąd migracji tenancy dla pliku: '.$file);
                return $exitCode;
            }
        }

        return self::SUCCESS;
    }

    protected function prepareTenantMigrationReplayIfNeeded(string $connection, string $file): void
    {
        $migration = pathinfo($file, PATHINFO_FILENAME);
        if ($migration === '') {
            return;
        }

        $expectedTables = $this->expectedTenantTablesForMigration($migration);
        if ($expectedTables === []) {
            return;
        }

        if (! $this->migrationRecordExists($connection, $migration)) {
            return;
        }

        if ($this->anyTableExists($connection, $expectedTables)) {
            return;
        }

        try {
            DB::connection($connection)
                ->table('migrations')
                ->where('migration', $migration)
                ->delete();

            $this->warn("Brak tabel dla wykonanej migracji [{$migration}] - usuwam wpis z tabeli migrations i uruchamiam ponownie.");
        } catch (Throwable $exception) {
            $this->warn("Nie udało się przygotować ponownego uruchomienia migracji [{$migration}]: {$exception->getMessage()}");
        }
    }

    /**
     * @return array<int, string>
     */
    protected function expectedTenantTablesForMigration(string $migration): array
    {
        return match (true) {
            str_contains($migration, 'create_tenants_table') => ['tenants'],
            str_contains($migration, 'create_model_has_tenants_table') => ['model_has_tenants'],
            str_contains($migration, 'create_tenant_domains_table') => ['tenant_domains', 'domains'],
            str_contains($migration, 'create_model_has_domain_tenants_table') => ['model_has_domain_tenants', 'model_has_domains'],
            str_contains($migration, 'create_tenant_profiles_table') => [
                trim((string) config('upsoftware.tenancy.profile.table', 'tenant_profiles')) ?: 'tenant_profiles',
            ],
            default => [],
        };
    }

    protected function migrationRecordExists(string $connection, string $migration): bool
    {
        try {
            if (! Schema::connection($connection)->hasTable('migrations')) {
                return false;
            }

            return DB::connection($connection)
                ->table('migrations')
                ->where('migration', $migration)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<int, string>  $tables
     */
    protected function anyTableExists(string $connection, array $tables): bool
    {
        foreach ($tables as $table) {
            $name = trim((string) $table);
            if ($name === '') {
                continue;
            }

            try {
                if (Schema::connection($connection)->hasTable($name)) {
                    return true;
                }
            } catch (Throwable) {
                // Ignore.
            }
        }

        return false;
    }

    protected function packageMigrationFilePath(string $filename): ?string
    {
        $candidates = [
            __DIR__.'/../../database/migrations/tenants/'.$filename,
            __DIR__.'/../../database/migrations/tenants-owner/'.$filename,
            __DIR__.'/../../database/migrations/tenants-profile/'.$filename,
            __DIR__.'/../../database/migrations/tenancy/'.$filename,
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function resolveOwnerEnabled(bool $tenancyEnabled): bool
    {
        $default = $tenancyEnabled && (bool) config('upsoftware.tenancy.owner.enabled', false);
        $value = $this->normalizeBooleanOption($this->option('owner-enabled'));

        if ($value !== null) {
            return $tenancyEnabled && $value;
        }

        if (! $this->input->isInteractive() || ! $tenancyEnabled) {
            return $default;
        }

        return $this->confirm('Czy powiązać tenant z właścicielem biznesowym (owner_type/owner_id)?', $default);
    }

    protected function resolveOwnerMap(bool $ownerEnabled): array
    {
        $defaultMap = config('upsoftware.tenancy.owner.map', []);
        if (! is_array($defaultMap)) {
            $defaultMap = [];
        }

        if (! $ownerEnabled) {
            return $defaultMap;
        }

        $option = $this->option('owner-map');
        if (is_string($option) && trim($option) !== '') {
            return $this->parseOwnerMap(trim($option));
        }

        if (! $this->input->isInteractive()) {
            return $defaultMap;
        }

        $raw = trim((string) $this->ask(
            'Mapowanie owner (alias=Model, po przecinku)',
            $this->stringifyOwnerMap($defaultMap)
        ));

        if ($raw === '') {
            return $defaultMap;
        }

        return $this->parseOwnerMap($raw);
    }

    protected function resolveProfileEnabled(bool $tenancyEnabled): bool
    {
        $default = $tenancyEnabled && (bool) config('upsoftware.tenancy.profile.enabled', true);
        $value = $this->normalizeBooleanOption($this->option('profile-enabled'));

        if ($value !== null) {
            return $tenancyEnabled && $value;
        }

        if (! $this->input->isInteractive() || ! $tenancyEnabled) {
            return $default;
        }

        return $this->confirm('Czy włączyć tabelę rozszerzającą dane tenantu (profile)?', $default);
    }

    protected function resolveProfileTable(bool $profileEnabled): string
    {
        $default = trim((string) config('upsoftware.tenancy.profile.table', 'tenant_profiles'));
        if ($default === '') {
            $default = 'tenant_profiles';
        }

        $option = $this->option('profile-table');
        if (is_string($option) && trim($option) !== '') {
            return trim($option);
        }

        if (! $profileEnabled || ! $this->input->isInteractive()) {
            return $default;
        }

        $value = trim((string) $this->ask('Nazwa tabeli profilu tenantu', $default));

        return $value !== '' ? $value : $default;
    }

    protected function resolveProfileForeignKey(bool $profileEnabled): string
    {
        $default = trim((string) config('upsoftware.tenancy.profile.foreign_key', 'tenant_id'));
        if ($default === '') {
            $default = 'tenant_id';
        }

        $option = $this->option('profile-foreign-key');
        if (is_string($option) && trim($option) !== '') {
            return trim($option);
        }

        if (! $profileEnabled || ! $this->input->isInteractive()) {
            return $default;
        }

        $value = trim((string) $this->ask('Nazwa FK w tabeli profilu do tenants.id', $default));

        return $value !== '' ? $value : $default;
    }

    protected function resolveProfileModel(bool $profileEnabled): string
    {
        $default = trim((string) config('upsoftware.tenancy.profile.model', '\Upsoftware\Svarium\Models\TenantProfile'));
        if ($default === '') {
            $default = '\Upsoftware\Svarium\Models\TenantProfile';
        }

        $option = $this->option('profile-model');
        if (is_string($option) && trim($option) !== '') {
            return trim($option);
        }

        if (! $profileEnabled || ! $this->input->isInteractive()) {
            return $default;
        }

        $value = trim((string) $this->ask('Model profilu tenantu (FQCN)', $default));

        return $value !== '' ? $value : $default;
    }

    protected function parseOwnerMap(string $input): array
    {
        $map = [];
        $pairs = array_filter(array_map('trim', explode(',', $input)));

        foreach ($pairs as $pair) {
            $parts = array_map('trim', explode('=', $pair, 2));
            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                continue;
            }

            $map[$parts[0]] = $parts[1];
        }

        return $map;
    }

    protected function stringifyOwnerMap(array $map): string
    {
        $items = [];
        foreach ($map as $alias => $class) {
            if (! is_string($alias) || ! is_string($class) || trim($alias) === '' || trim($class) === '') {
                continue;
            }
            $items[] = "{$alias}={$class}";
        }

        return implode(',', $items);
    }

    protected function synchronizeDomainsTableName(bool $tenancyEnabled): void
    {
        $source = $tenancyEnabled ? 'domains' : 'tenant_domains';
        $target = $tenancyEnabled ? 'tenant_domains' : 'domains';

        if (! Schema::hasTable($source)) {
            return;
        }

        if (Schema::hasTable($target)) {
            $this->warn("Obie tabele domen istnieją ({$source} i {$target}) — pomijam automatyczną zmianę nazwy.");
            return;
        }

        Schema::rename($source, $target);
        $this->info("Tabela domen została przemianowana: {$source} -> {$target}");
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
