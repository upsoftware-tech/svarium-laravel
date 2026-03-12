<?php

namespace Upsoftware\Svarium\Tenancy\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Schema;
use Throwable;

trait BelongsToTenant
{
    protected static array $tenantColumnExistsCache = [];
    protected static array $tableExistsCache = [];
    protected static array $mapColumnExistsCache = [];

    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('svarium_tenant', function (Builder $builder): void {
            if (! svarium_tenancy_enabled() || ! svarium_tenancy_column_mode()) {
                return;
            }

            $model = new static;
            $tenantKey = tenant('id');
            $domainId = tenant_domain('id');
            $hasTenantColumn = $model->modelHasTenantColumn();
            $tenantMapEnabled = $model->tenantModelMapEnabled();
            $domainMapEnabled = $model->domainTenantMapEnabled() && $domainId !== null;

            if ($tenantKey === null) {
                if ((bool) config('upsoftware.tenancy.column.strict', false)) {
                    $builder->whereRaw('1 = 0');
                }

                return;
            }

            $hasTenantRestriction = $hasTenantColumn || $tenantMapEnabled || $domainMapEnabled;

            if (! $hasTenantRestriction) {
                return;
            }

            $builder->where(function (Builder $query) use (
                $model,
                $tenantKey,
                $domainId,
                $hasTenantColumn,
                $tenantMapEnabled,
                $domainMapEnabled
            ): void {
                $added = false;

                if ($hasTenantColumn) {
                    $query->where(
                        $query->qualifyColumn($model->getTenantColumnName()),
                        $tenantKey
                    );
                    $added = true;
                }

                if ($tenantMapEnabled) {
                    $method = $added ? 'orWhereExists' : 'whereExists';
                    $query->{$method}(function ($subQuery) use ($model, $tenantKey): void {
                        $subQuery
                            ->selectRaw('1')
                            ->from($model->getTenantModelMapTableName())
                            ->where('tenant_id', $tenantKey)
                            ->where('model_type', svarium_model_type($model))
                            ->whereColumn('model_id', $model->qualifyColumn($model->getKeyName()));
                    });

                    $added = true;
                }

                if ($domainMapEnabled && $domainId !== null) {
                    $method = $added ? 'orWhereExists' : 'whereExists';
                    $domainColumn = $model->getDomainTenantMapColumnName();

                    $query->{$method}(function ($subQuery) use ($model, $domainId, $domainColumn): void {
                        $subQuery
                            ->selectRaw('1')
                            ->from($model->getDomainTenantMapTableName())
                            ->where($domainColumn, $domainId)
                            ->where('model_type', svarium_model_type($model))
                            ->whereColumn('model_id', $model->qualifyColumn($model->getKeyName()));
                    });
                }
            });

            if ($domainMapEnabled && $domainId !== null) {
                // If record has explicit domain mapping, it must contain current domain.
                // If mapping does not exist, record is treated as tenant-global.
                $domainColumn = $model->getDomainTenantMapColumnName();

                $builder->where(function (Builder $query) use ($model, $domainId, $domainColumn): void {
                    $query->whereNotExists(function ($subQuery) use ($model): void {
                        $subQuery
                            ->selectRaw('1')
                            ->from($model->getDomainTenantMapTableName())
                            ->where('model_type', svarium_model_type($model))
                            ->whereColumn('model_id', $model->qualifyColumn($model->getKeyName()));
                    })->orWhereExists(function ($subQuery) use ($model, $domainId, $domainColumn): void {
                        $subQuery
                            ->selectRaw('1')
                            ->from($model->getDomainTenantMapTableName())
                            ->where($domainColumn, $domainId)
                            ->where('model_type', svarium_model_type($model))
                            ->whereColumn('model_id', $model->qualifyColumn($model->getKeyName()));
                    });
                });
            }
        });

        static::creating(function ($model): void {
            if (! svarium_tenancy_enabled() || ! svarium_tenancy_column_mode()) {
                return;
            }

            if (! method_exists($model, 'modelHasTenantColumn') || ! $model->modelHasTenantColumn()) {
                return;
            }

            $tenantId = tenant('id');
            $column = $model->getTenantColumnName();

            if ($tenantId === null) {
                return;
            }

            if (! isset($model->{$column}) || $model->{$column} === null || $model->{$column} === '') {
                $model->{$column} = $tenantId;
            }
        });

        static::created(function (Model $model): void {
            if (! svarium_tenancy_enabled() || ! svarium_tenancy_column_mode()) {
                return;
            }

            if (! method_exists($model, 'tenantModelMapEnabled') || ! $model->tenantModelMapEnabled()) {
                return;
            }

            if (method_exists($model, 'modelHasTenantColumn') && $model->modelHasTenantColumn()) {
                return;
            }

            $tenantId = tenant('id');

            if ($tenantId === null || ! method_exists($model, 'attachTenant')) {
                return;
            }

            $model->attachTenant((string) $tenantId);
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(get_model('tenant'), $this->getTenantColumnName(), 'id');
    }

    public function tenants(): MorphToMany
    {
        return $this->morphToMany(
            get_model('tenant'),
            'model',
            $this->getTenantModelMapTableName(),
            'model_id',
            'tenant_id',
            $this->getKeyName(),
            'id'
        )->withTimestamps();
    }

    public function attachTenant(string $tenantId): static
    {
        if ($tenantId === '' || $this->getKey() === null) {
            return $this;
        }

        $this->tenants()->syncWithoutDetaching([$tenantId]);

        return $this;
    }

    public function syncTenants(array $tenantIds): static
    {
        $ids = array_values(array_filter(array_map(
            static fn ($id) => trim((string) $id),
            $tenantIds
        )));

        if ($this->getKey() === null) {
            return $this;
        }

        $this->tenants()->sync($ids);

        return $this;
    }

    public function attachTenantDomain(string|int $tenantDomainId, ?string $tenantId = null): static
    {
        if ($this->getKey() === null) {
            return $this;
        }

        $table = $this->getDomainTenantMapTableName();
        $domainColumn = $this->getDomainTenantMapColumnName();
        $resolvedTenantId = trim((string) ($tenantId ?? tenant('id')));

        $keys = [
            $domainColumn => (int) $tenantDomainId,
            'model_type' => svarium_model_type($this),
            'model_id' => (string) $this->getKey(),
        ];

        if ($this->domainTenantMapHasTenantColumn()) {
            if ($resolvedTenantId === '') {
                return $this;
            }

            $keys['tenant_id'] = $resolvedTenantId;
        }

        $this->newQuery()->getConnection()->table($table)->updateOrInsert(
            $keys,
            [
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return $this;
    }

    public function syncTenantDomains(array $tenantDomainIds, ?string $tenantId = null): static
    {
        if ($this->getKey() === null) {
            return $this;
        }

        $tableName = $this->getDomainTenantMapTableName();
        $domainColumn = $this->getDomainTenantMapColumnName();
        $hasTenantColumn = $this->domainTenantMapHasTenantColumn();
        $resolvedTenantId = trim((string) ($tenantId ?? tenant('id')));
        if ($hasTenantColumn && $resolvedTenantId === '') {
            return $this;
        }

        $ids = array_values(array_filter(array_map(
            static fn ($id) => is_numeric($id) ? (int) $id : null,
            $tenantDomainIds
        )));

        $table = $this->newQuery()->getConnection()->table($tableName);

        $table->where('model_type', svarium_model_type($this))
            ->where('model_id', (string) $this->getKey());

        if ($hasTenantColumn) {
            $table->where('tenant_id', $resolvedTenantId);
        }

        $table->delete();

        if ($ids === []) {
            return $this;
        }

        $now = now();
        $rows = array_map(function (int $id) use ($resolvedTenantId, $now, $domainColumn, $hasTenantColumn): array {
            $row = [
                $domainColumn => $id,
                'model_type' => svarium_model_type($this),
                'model_id' => (string) $this->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($hasTenantColumn) {
                $row['tenant_id'] = $resolvedTenantId;
            }

            return $row;
        }, $ids);

        $table->insert($rows);

        return $this;
    }

    public function getTenantColumnName(): string
    {
        $column = $this->tenantColumn ?? null;

        if (is_string($column) && trim($column) !== '') {
            return trim($column);
        }

        return (string) config('upsoftware.tenancy.column.column', 'tenant_id');
    }

    protected function getTenantModelMapTableName(): string
    {
        $table = trim((string) config('upsoftware.tenancy.column.model_maps.tenants.table', ''));
        if ($table === '') {
            $table = trim((string) config('upsoftware.tenancy.column.model_map.table', ''));
        }
        if ($table === '') {
            $table = 'model_has_tenants';
        }

        return $table;
    }

    protected function getDomainTenantMapTableName(): string
    {
        $table = trim((string) config('upsoftware.tenancy.column.model_maps.domains.table', ''));
        if ($table === '') {
            $table = 'model_has_domains';
        }

        if (! $this->tableExists($table) && $table !== 'model_has_domain_tenants' && $this->tableExists('model_has_domain_tenants')) {
            return 'model_has_domain_tenants';
        }

        return $table;
    }

    protected function getDomainTenantMapColumnName(): string
    {
        $table = $this->getDomainTenantMapTableName();
        $column = trim((string) config('upsoftware.tenancy.column.model_maps.domains.domain_key', 'domain_id'));

        if ($column !== '' && $this->columnExists($table, $column)) {
            return $column;
        }

        if ($this->columnExists($table, 'tenant_domain_id')) {
            return 'tenant_domain_id';
        }

        return 'domain_id';
    }

    protected function tenantModelMapEnabled(): bool
    {
        $enabled = config('upsoftware.tenancy.column.model_maps.tenants.enabled');
        if ($enabled === null) {
            $enabled = config('upsoftware.tenancy.column.model_map.enabled', true);
        }

        if (! (bool) $enabled) {
            return false;
        }

        return $this->tableExists($this->getTenantModelMapTableName());
    }

    protected function domainTenantMapEnabled(): bool
    {
        if (! (bool) config('upsoftware.tenancy.column.model_maps.domains.enabled', true)) {
            return false;
        }

        return $this->tableExists($this->getDomainTenantMapTableName());
    }

    protected function domainTenantMapHasTenantColumn(): bool
    {
        return $this->columnExists($this->getDomainTenantMapTableName(), 'tenant_id');
    }

    protected function modelHasTenantColumn(): bool
    {
        $column = $this->getTenantColumnName();
        if ($column === '') {
            return false;
        }

        $connectionName = (string) ($this->getConnectionName() ?? config('database.default', 'mysql'));
        $cacheKey = "{$connectionName}:{$this->getTable()}:{$column}";

        if (array_key_exists($cacheKey, static::$tenantColumnExistsCache)) {
            return static::$tenantColumnExistsCache[$cacheKey];
        }

        try {
            return static::$tenantColumnExistsCache[$cacheKey] = Schema::connection($connectionName)
                ->hasColumn($this->getTable(), $column);
        } catch (Throwable) {
            return static::$tenantColumnExistsCache[$cacheKey] = false;
        }
    }

    protected function tableExists(string $table): bool
    {
        if ($table === '') {
            return false;
        }

        $connectionName = (string) ($this->getConnectionName() ?? config('database.default', 'mysql'));
        $cacheKey = "{$connectionName}:{$table}";

        if (array_key_exists($cacheKey, static::$tableExistsCache)) {
            return static::$tableExistsCache[$cacheKey];
        }

        try {
            return static::$tableExistsCache[$cacheKey] = Schema::connection($connectionName)->hasTable($table);
        } catch (Throwable) {
            return static::$tableExistsCache[$cacheKey] = false;
        }
    }

    protected function columnExists(string $table, string $column): bool
    {
        if ($table === '' || $column === '') {
            return false;
        }

        $connectionName = (string) ($this->getConnectionName() ?? config('database.default', 'mysql'));
        $cacheKey = "{$connectionName}:{$table}:{$column}";

        if (array_key_exists($cacheKey, static::$mapColumnExistsCache)) {
            return static::$mapColumnExistsCache[$cacheKey];
        }

        try {
            return static::$mapColumnExistsCache[$cacheKey] = Schema::connection($connectionName)
                ->hasColumn($table, $column);
        } catch (Throwable) {
            return static::$mapColumnExistsCache[$cacheKey] = false;
        }
    }
}
