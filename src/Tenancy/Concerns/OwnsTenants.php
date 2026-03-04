<?php

namespace Upsoftware\Svarium\Tenancy\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Upsoftware\Svarium\Models\Tenant;

trait OwnsTenants
{
    public static function bootOwnsTenants(): void
    {
        static::created(function (Model $owner): void {
            if (! ($owner instanceof self)) {
                return;
            }

            if (! $owner->shouldAutoCreateTenant()) {
                return;
            }

            $owner->ensureTenantExists();
        });
    }

    public function tenants(): HasMany
    {
        $tenantClass = $this->tenantModelClass();
        $typeColumn = $this->tenantOwnerTypeColumn();
        $idColumn = $this->tenantOwnerIdColumn();
        $ownerClass = ltrim(static::class, '\\');
        $ownerAlias = $this->tenantOwnerAlias();

        return $this->hasMany($tenantClass, $idColumn, $this->getKeyName())
            ->where(function ($query) use ($typeColumn, $ownerClass, $ownerAlias): void {
                $query->where($typeColumn, $ownerClass);

                if ($ownerAlias !== null) {
                    $query->orWhere($typeColumn, $ownerAlias);
                }
            });
    }

    public function ensureTenantExists(array $attributes = []): Tenant
    {
        /** @var Tenant|null $existing */
        $existing = $this->tenants()->first();

        if ($existing !== null) {
            return $existing;
        }

        $defaults = $this->defaultTenantAttributes();

        /** @var array<string, mixed> $payload */
        $payload = array_replace($defaults, $attributes);

        return $this->createTenant($payload);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function createTenant(array $attributes = []): Tenant
    {
        $tenantClass = $this->tenantModelClass();
        $typeColumn = $this->tenantOwnerTypeColumn();
        $idColumn = $this->tenantOwnerIdColumn();

        /** @var Tenant $tenant */
        $tenant = new $tenantClass($attributes);
        $tenant->setAttribute($typeColumn, $this->tenantOwnerAlias() ?? ltrim(static::class, '\\'));
        $tenant->setAttribute($idColumn, (string) $this->getKey());
        $tenant->save();

        return $tenant;
    }

    protected function shouldAutoCreateTenant(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultTenantAttributes(): array
    {
        $name = $this->resolveTenantName();

        return [
            'name' => $name,
            'slug' => $this->resolveUniqueTenantSlug($name),
            'status' => true,
        ];
    }

    /**
     * @return class-string<Tenant>
     */
    protected function tenantModelClass(): string
    {
        $model = config('upsoftware.models.tenant', Tenant::class);

        if (! is_string($model) || ! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            return Tenant::class;
        }

        return $model;
    }

    protected function tenantOwnerTypeColumn(): string
    {
        $column = trim((string) config('upsoftware.tenancy.owner.type_column', 'owner_type'));

        return $column !== '' ? $column : 'owner_type';
    }

    protected function tenantOwnerIdColumn(): string
    {
        $column = trim((string) config('upsoftware.tenancy.owner.id_column', 'owner_id'));

        return $column !== '' ? $column : 'owner_id';
    }

    protected function tenantOwnerAlias(): ?string
    {
        $map = config('upsoftware.tenancy.owner.map', []);
        if (! is_array($map)) {
            return null;
        }

        $ownerClass = ltrim(static::class, '\\');

        foreach ($map as $alias => $class) {
            if (! is_string($alias) || ! is_string($class)) {
                continue;
            }

            if (ltrim($class, '\\') === $ownerClass && trim($alias) !== '') {
                return $alias;
            }
        }

        return null;
    }

    protected function resolveTenantName(): string
    {
        $candidate = trim((string) ($this->getAttribute('name') ?? ''));
        if ($candidate !== '') {
            return $candidate;
        }

        $candidate = trim((string) ($this->getAttribute('title') ?? ''));
        if ($candidate !== '') {
            return $candidate;
        }

        return class_basename(static::class).' '.$this->getKey();
    }

    protected function resolveUniqueTenantSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = Str::slug(class_basename(static::class).'-'.$this->getKey());
        }
        if ($base === '') {
            $base = 'tenant-'.(string) $this->getKey();
        }

        $tenantClass = $this->tenantModelClass();
        $slug = $base;
        $suffix = 1;

        while ($tenantClass::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
