<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use Upsoftware\Svarium\Models\Domain;
use Upsoftware\Svarium\Models\Tenant;

class MakeTenantCommand extends CoreCommand
{
    protected $signature = 'svarium:make.tenant
        {name? : Nazwa tenanta}
        {domain? : Główna domena tenanta}
        {--slug= : Własny slug tenanta}
        {--inactive : Utwórz tenant jako nieaktywny}
        {--db-host= : Host bazy tenant (tylko tryb database)}
        {--db-port= : Port bazy tenant (tylko tryb database)}
        {--db-name= : Nazwa bazy tenant (tylko tryb database)}
        {--db-user= : Użytkownik bazy tenant (tylko tryb database)}
        {--db-password= : Hasło bazy tenant (tylko tryb database)}';

    protected $description = 'Create tenant + primary domain (and DB data in database mode)';
    protected $descriptionKey = 'make.tenant.default';

    public function handle(): int
    {
        try {
            $tenantModel = $this->resolveModel('upsoftware.models.tenant', Tenant::class);
            $tenantDomainModel = $this->resolveDomainModel();

            $name = $this->resolveTenantName();
            $domain = $this->resolveTenantDomain();
            $slug = $this->resolveUniqueSlug($tenantModel, $name);

            if ($tenantDomainModel::query()->where('domain', $domain)->exists()) {
                $this->error("Domena [{$domain}] jest już przypisana do innego tenant.");
                return self::FAILURE;
            }

            $attributes = [
                'name' => $name,
                'slug' => $slug,
                'status' => ! (bool) $this->option('inactive'),
            ];

            $tenantRuntimeEnvironment = $this->normalizeRuntimeEnvironment();
            $tenantPrototype = new $tenantModel();
            $tenantTable = $tenantPrototype->getTable();

            if (Schema::hasColumn($tenantTable, 'env')) {
                $attributes['env'] = $tenantRuntimeEnvironment;
            }

            if ($this->tenantMode() === 'database') {
                $attributes = [
                    ...$attributes,
                    ...$this->resolveTenantDatabaseAttributes(),
                ];
            }

            /** @var Model $tenant */
            $tenant = $tenantModel::query()->create($attributes);

            $tenantDomainModel::query()->create([
                'tenant_id' => $tenant->getKey(),
                'domain' => $domain,
                'is_primary' => true,
            ]);

            $this->info('Tenant został utworzony.');
            $this->line('ID: '.(string) $tenant->getKey());
            $this->line('Slug: '.$slug);
            $this->line('Domena: '.$domain);
            $this->line('Tryb tenancy: '.$this->tenantMode());

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }

    protected function tenantMode(): string
    {
        $mode = strtolower(trim((string) config('upsoftware.tenancy.mode', 'column')));

        return in_array($mode, ['column', 'database'], true)
            ? $mode
            : 'column';
    }

    /**
     * @return class-string<Model>
     */
    protected function resolveDomainModel(): string
    {
        $domainModel = config('upsoftware.models.domain');

        if (! is_string($domainModel) || ! class_exists($domainModel)) {
            $domainModel = config('upsoftware.models.tenant_domain', Domain::class);
        }

        if (! is_string($domainModel) || ! class_exists($domainModel)) {
            $domainModel = Domain::class;
        }

        if (! is_subclass_of($domainModel, Model::class)) {
            throw new RuntimeException("Model domeny [{$domainModel}] nie dziedziczy po Eloquent Model.");
        }

        return $domainModel;
    }

    /**
     * @return class-string<Model>
     */
    protected function resolveModel(string $configKey, string $fallback): string
    {
        $model = config($configKey, $fallback);

        if (! is_string($model) || ! class_exists($model)) {
            throw new RuntimeException("Model pod kluczem [{$configKey}] nie istnieje.");
        }

        if (! is_subclass_of($model, Model::class)) {
            throw new RuntimeException("Model [{$model}] nie dziedziczy po Eloquent Model.");
        }

        return $model;
    }

    protected function resolveTenantName(): string
    {
        $name = trim((string) ($this->argument('name') ?? ''));

        if ($name === '') {
            $name = $this->askRequired('Nazwa tenanta');
        }

        return $name;
    }

    protected function resolveTenantDomain(): string
    {
        $domain = trim((string) ($this->argument('domain') ?? ''));

        if ($domain === '') {
            $domain = $this->askRequired('Główna domena tenanta (np. acme.example.com)');
        }

        $domain = $this->normalizeDomain($domain);

        if ($domain === '') {
            throw new RuntimeException('Domena tenanta nie może być pusta.');
        }

        if (preg_match('/\s/', $domain) === 1) {
            throw new RuntimeException('Domena tenanta nie może zawierać spacji.');
        }

        return $domain;
    }

    protected function normalizeDomain(string $domain): string
    {
        $normalized = strtolower(trim($domain));
        $normalized = preg_replace('#^https?://#', '', $normalized) ?? $normalized;
        $normalized = preg_replace('#/.*$#', '', $normalized) ?? $normalized;
        $normalized = preg_replace('#:\d+$#', '', $normalized) ?? $normalized;

        return trim($normalized, '.');
    }

    /**
     * @param class-string<Model> $tenantModel
     */
    protected function resolveUniqueSlug(string $tenantModel, string $name): string
    {
        $slugOption = $this->option('slug');
        $slug = is_string($slugOption) ? trim($slugOption) : '';

        if ($slug === '') {
            $slug = Str::slug($name);
        }

        if ($slug === '') {
            $slug = 'tenant';
        }

        $base = $slug;
        $counter = 2;

        while ($tenantModel::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveTenantDatabaseAttributes(): array
    {
        $templateConnection = (string) config(
            'upsoftware.tenancy.database.template_connection',
            config('database.default', 'mysql')
        );
        $templateConfig = config("database.connections.{$templateConnection}", []);

        if (! is_array($templateConfig)) {
            $templateConfig = [];
        }

        $hostDefault = (string) ($templateConfig['host'] ?? '127.0.0.1');
        $portDefault = (string) ($templateConfig['port'] ?? '3306');
        $databaseDefault = (string) ($templateConfig['database'] ?? '');
        $usernameDefault = (string) ($templateConfig['username'] ?? '');

        $dbHost = $this->optionOrAskRequired('db-host', 'Host bazy tenant', $hostDefault);
        $dbPort = $this->optionOrAsk('db-port', 'Port bazy tenant', $portDefault);
        $dbName = $this->optionOrAskRequired('db-name', 'Nazwa bazy tenant', $databaseDefault);
        $dbUser = $this->optionOrAskRequired('db-user', 'Użytkownik bazy tenant', $usernameDefault);

        $dbPasswordOption = $this->option('db-password');
        $dbPassword = is_string($dbPasswordOption)
            ? $dbPasswordOption
            : (string) $this->secret('Hasło bazy tenant (może być puste)');

        return [
            'tenancy_db_host' => $dbHost,
            'tenancy_db_port' => $dbPort !== '' ? (int) $dbPort : null,
            'tenancy_db_name' => $dbName,
            'tenancy_db_username' => $dbUser,
            'tenancy_db_password' => $dbPassword,
        ];
    }

    protected function optionOrAskRequired(string $option, string $question, string $default = ''): string
    {
        $value = $this->optionOrAsk($option, $question, $default);

        if ($value !== '') {
            return $value;
        }

        return $this->askRequired($question, $default);
    }

    protected function optionOrAsk(string $option, string $question, string $default = ''): string
    {
        $optionValue = $this->option($option);

        if (is_string($optionValue) && trim($optionValue) !== '') {
            return trim($optionValue);
        }

        return trim((string) $this->ask($question, $default));
    }

    protected function askRequired(string $question, string $default = ''): string
    {
        while (true) {
            $value = trim((string) $this->ask($question, $default));

            if ($value !== '') {
                return $value;
            }

            $this->warn('To pole jest wymagane.');
        }
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
