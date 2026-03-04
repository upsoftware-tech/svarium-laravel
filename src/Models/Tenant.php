<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use RuntimeException;
use Upsoftware\Svarium\Casts\EncryptedOrPlainText;
use Upsoftware\Svarium\Traits\UsesConnection;

class Tenant extends Model
{
    use UsesConnection;

    protected $table = 'tenants';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'status',
        'owner_type',
        'owner_id',
        'tenancy_db_host',
        'tenancy_db_port',
        'tenancy_db_username',
        'tenancy_db_name',
        'tenancy_db_password',
    ];

    protected $casts = [
        'status' => 'boolean',
        'tenancy_db_name' => EncryptedOrPlainText::class,
        'tenancy_db_username' => EncryptedOrPlainText::class,
        'tenancy_db_password' => EncryptedOrPlainText::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! $model->id) {
                $model->id = 'tenant_'.Str::lower((string) Str::ulid());
            }
        });
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class, 'tenant_id', 'id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users', 'tenant_id', 'user_id', 'id', 'id')
            ->withPivot('role_id')
            ->withTimestamps();
    }

    public function profile(): HasOne
    {
        return $this->hasOne(
            $this->resolveProfileModelClass(),
            (string) config('upsoftware.tenancy.profile.foreign_key', 'tenant_id'),
            'id'
        );
    }

    public function ownerEntity(): ?Model
    {
        $ownerType = trim((string) ($this->getAttribute('owner_type') ?? ''));
        $ownerId = $this->getAttribute('owner_id');

        if ($ownerType === '' || $ownerId === null || $ownerId === '') {
            return null;
        }

        $ownerClass = $this->resolveOwnerModelClass($ownerType);
        if ($ownerClass === null) {
            return null;
        }

        return $ownerClass::query()->find($ownerId);
    }

    public function owner(): MorphTo
    {
        return $this->morphTo(
            __FUNCTION__,
            (string) config('upsoftware.tenancy.owner.type_column', 'owner_type'),
            (string) config('upsoftware.tenancy.owner.id_column', 'owner_id')
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function createForOwner(Model $owner, array $attributes = []): self
    {
        $typeColumn = (string) config('upsoftware.tenancy.owner.type_column', 'owner_type');
        $idColumn = (string) config('upsoftware.tenancy.owner.id_column', 'owner_id');

        $attributes[$typeColumn] = self::resolveOwnerTypeForModel($owner);
        $attributes[$idColumn] = (string) $owner->getKey();

        return static::query()->create($attributes);
    }

    protected function resolveOwnerModelClass(string $ownerType): ?string
    {
        $map = config('upsoftware.tenancy.owner.map', []);
        if (! is_array($map)) {
            $map = [];
        }

        if (isset($map[$ownerType]) && is_string($map[$ownerType]) && class_exists($map[$ownerType])) {
            return $map[$ownerType];
        }

        if (class_exists($ownerType)) {
            return $ownerType;
        }

        foreach ($map as $class) {
            if (is_string($class) && ltrim($class, '\\') === ltrim($ownerType, '\\') && class_exists($class)) {
                return $class;
            }
        }

        return null;
    }

    protected static function resolveOwnerTypeForModel(Model $owner): string
    {
        $ownerClass = ltrim($owner::class, '\\');
        $map = config('upsoftware.tenancy.owner.map', []);

        if (! is_array($map)) {
            return $ownerClass;
        }

        foreach ($map as $alias => $class) {
            if (! is_string($alias) || ! is_string($class)) {
                continue;
            }

            if (ltrim($class, '\\') === $ownerClass) {
                return $alias;
            }
        }

        return $ownerClass;
    }

    /**
     * @return class-string<Model>
     */
    protected function resolveProfileModelClass(): string
    {
        $model = config('upsoftware.tenancy.profile.model', TenantProfile::class);

        if (! is_string($model) || ! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            throw new RuntimeException('Invalid tenant profile model configured under upsoftware.tenancy.profile.model');
        }

        return $model;
    }
}
