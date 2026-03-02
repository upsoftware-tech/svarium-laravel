<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
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
        'tenancy_db_host',
        'tenancy_db_port',
        'tenancy_db_username',
        'tenancy_db_name',
        'tenancy_db_password',
    ];

    protected $casts = [
        'status' => 'boolean',
        'tenancy_db_name' => 'encrypted',
        'tenancy_db_username' => 'encrypted',
        'tenancy_db_password' => 'encrypted',
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
        return $this->hasMany(TenantDomain::class, 'tenant_id', 'id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users', 'tenant_id', 'user_id', 'id', 'id')
            ->withPivot('role_id')
            ->withTimestamps();
    }
}
