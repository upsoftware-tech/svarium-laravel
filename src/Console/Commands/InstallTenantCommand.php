<?php

namespace Upsoftware\Svarium\Console\Commands;

use RuntimeException;
use Winter\LaravelConfigWriter\ArrayFile;
use function Laravel\Prompts\select;

class InstallTenantCommand extends CoreCommand
{
    protected $signature = 'svarium:install:tenant
        {--central=central : Nazwa połączenia centralnego}
        {--tenant=tenant : Nazwa połączenia tenant}
        {--template= : Istniejące połączenie bazowe (np. mysql)}';

    protected $description = 'Konfiguruje połączenia central/tenant w config/database.php';

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

        $this->info('Dodano/odświeżono konfigurację tenancy w config/database.php');
        $this->line("Central connection: {$centralConnection}");
        $this->line("Tenant connection: {$tenantConnection}");
        $this->line("Template connection: {$templateConnection}");

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

            $connection[$key] = $config->constant($this->envExpression($envKey, $connection[$key]));
        }

        return $connection;
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
}
