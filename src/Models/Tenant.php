<?php

namespace Upsoftware\Svarium\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted()
    {
        static::creating(function ($model) {
            if (! $model->id) {
                $model->id = 'tenant_'.time();
            }
        });
    }

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'tenancy_db_host',
            'tenancy_db_username',
            'tenancy_db_name',
            'tenancy_db_password',
        ];
    }

    protected $casts = [
        'tenancy_db_name' => 'encrypted',
        'tenancy_db_username' => 'encrypted',
        'tenancy_db_password' => 'encrypted',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'tenant_users', 'tenant_id', 'user_id', 'id', 'id')
            ->withPivot('role_id')
            ->withTimestamps();
    }
}
