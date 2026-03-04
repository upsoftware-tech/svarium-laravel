<?php

namespace Upsoftware\Svarium\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;
use Upsoftware\Svarium\Models\Domain;
use Upsoftware\Svarium\Models\Tenant;

class TenancyManager
{
    protected ?Model $tenant = null;
    protected ?Model $domain = null;

    protected ?string $originalConnection = null;

    public function enabled(): bool
    {
        return (bool) config('upsoftware.tenancy.enabled', config('tenancy.enabled', false));
    }

    public function mode(): string
    {
        $mode = strtolower(trim((string) config('upsoftware.tenancy.mode', 'column')));

        return in_array($mode, ['database', 'column'], true)
            ? $mode
            : 'column';
    }

    public function isDatabaseMode(): bool
    {
        return $this->mode() === 'database';
    }

    public function isColumnMode(): bool
    {
        return $this->mode() === 'column';
    }

    public function domainsEnabled(): bool
    {
        return (bool) config('upsoftware.tenancy.domains.enabled', true);
    }

    public function tenant(): ?Model
    {
        return $this->tenant;
    }

    public function domain(): ?Model
    {
        return $this->domain;
    }

    public function initialize(Request $request): void
    {
        $this->terminate();

        if (! $this->enabled()) {
            return;
        }

        $host = $this->normalizeHost((string) $request->getHost());

        if ($host === '') {
            return;
        }

        if (! $this->domainsEnabled()) {
            return;
        }

        if ($this->isCentralDomain($host)) {
            return;
        }

        $tenant = $this->resolveTenantByHost($host);

        if (! $tenant instanceof Model) {
            return;
        }

        $this->tenant = $tenant;

        if ($this->isDatabaseMode()) {
            $this->bootstrapTenantDatabaseConnection($tenant);
        }
    }

    public function terminate(): void
    {
        $this->tenant = null;
        $this->domain = null;

        if ($this->originalConnection !== null) {
            DB::setDefaultConnection($this->originalConnection);
            $this->originalConnection = null;
        }
    }

    protected function resolveTenantByHost(string $host): ?Model
    {
        if (! $this->domainsEnabled()) {
            return null;
        }

        $this->domain = null;
        $domainModelClass = $this->resolveDomainModelClass();

        if ($domainModelClass !== null) {
            try {
                /** @var Model|null $domain */
                $domain = $domainModelClass::query()
                    ->where('domain', $host)
                    ->orWhere('domain', 'www.'.$host)
                    ->first();

                if ($domain instanceof Model) {
                    if ($domain->getAttribute('status') !== null && ! (bool) $domain->getAttribute('status')) {
                        return null;
                    }

                    $this->domain = $domain;
                    $tenant = $domain->getAttribute('tenant');

                    if ($tenant instanceof Model) {
                        return $tenant;
                    }

                    if (method_exists($domain, 'tenant')) {
                        $resolved = $domain->tenant()->first();

                        if ($resolved instanceof Model) {
                            return $resolved;
                        }
                    }
                }
            } catch (Throwable) {
                // Table/model may be missing during bootstrap; ignore gracefully.
            }
        }

        $tenantModelClass = $this->resolveTenantModelClass();

        if ($tenantModelClass === null) {
            return null;
        }

        try {
            /** @var Model|null $tenant */
            $tenant = $tenantModelClass::query()
                ->where('domain', $host)
                ->orWhere('domain', 'www.'.$host)
                ->first();

            return $tenant;
        } catch (Throwable) {
            return null;
        }
    }

    protected function bootstrapTenantDatabaseConnection(Model $tenant): void
    {
        $database = (string) ($tenant->getAttribute('tenancy_db_name') ?? $tenant->getAttribute('db_database') ?? '');
        $username = (string) ($tenant->getAttribute('tenancy_db_username') ?? $tenant->getAttribute('db_username') ?? '');
        $password = (string) ($tenant->getAttribute('tenancy_db_password') ?? $tenant->getAttribute('db_password') ?? '');
        $host = (string) ($tenant->getAttribute('tenancy_db_host') ?? $tenant->getAttribute('db_host') ?? '');
        $port = $tenant->getAttribute('tenancy_db_port') ?? $tenant->getAttribute('db_port') ?? null;

        if ($database === '' || $username === '' || $host === '') {
            return;
        }

        $tenantConnection = (string) config('upsoftware.tenancy.database.tenant_connection', 'tenant');
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

        config(["database.connections.{$tenantConnection}" => $tenantConfig]);

        if ($this->originalConnection === null) {
            $this->originalConnection = (string) config('database.default', 'mysql');
        }

        DB::purge($tenantConnection);
        DB::setDefaultConnection($tenantConnection);
    }

    protected function resolveTenantModelClass(): ?string
    {
        $tenantModel = config('upsoftware.models.tenant', Tenant::class);

        return is_string($tenantModel) && class_exists($tenantModel)
            ? $tenantModel
            : null;
    }

    protected function resolveDomainModelClass(): ?string
    {
        $domainModel = config('upsoftware.models.domain');

        if (! is_string($domainModel) || ! class_exists($domainModel)) {
            $domainModel = config('upsoftware.models.tenant_domain');
        }

        if (! is_string($domainModel) || ! class_exists($domainModel)) {
            $domainModel = Domain::class;
        }

        return is_string($domainModel) && class_exists($domainModel)
            ? $domainModel
            : null;
    }

    protected function normalizeHost(string $host): string
    {
        return strtolower(trim($host));
    }

    protected function isCentralDomain(string $host): bool
    {
        $domains = config('upsoftware.tenancy.domains.central_domains', []);

        if (is_string($domains)) {
            $domains = array_filter(array_map('trim', explode(',', $domains)));
        }

        if (! is_array($domains)) {
            return false;
        }

        $normalized = array_values(array_filter(array_map(
            static fn ($item) => strtolower(trim((string) $item)),
            $domains
        )));

        return in_array($host, $normalized, true);
    }
}
