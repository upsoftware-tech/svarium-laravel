<?php

namespace Upsoftware\Svarium\Tenancy\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('svarium_tenant', function (Builder $builder): void {
            if (! svarium_tenancy_enabled() || ! svarium_tenancy_column_mode()) {
                return;
            }

            $tenant = tenant();
            $column = $builder->qualifyColumn((new static)->getTenantColumnName());

            if ($tenant && $tenant->getKey() !== null) {
                $builder->where($column, $tenant->getKey());
                return;
            }

            if ((bool) config('upsoftware.tenancy.column.strict', false)) {
                $builder->whereRaw('1 = 0');
            }
        });

        static::creating(function ($model): void {
            if (! svarium_tenancy_enabled() || ! svarium_tenancy_column_mode()) {
                return;
            }

            $tenant = tenant();
            $column = $model->getTenantColumnName();

            if (! $tenant || $tenant->getKey() === null) {
                return;
            }

            if (! isset($model->{$column}) || $model->{$column} === null || $model->{$column} === '') {
                $model->{$column} = $tenant->getKey();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(get_model('tenant'), $this->getTenantColumnName(), 'id');
    }

    public function getTenantColumnName(): string
    {
        $column = $this->tenantColumn ?? null;

        if (is_string($column) && trim($column) !== '') {
            return trim($column);
        }

        return (string) config('upsoftware.tenancy.column.column', 'tenant_id');
    }
}
