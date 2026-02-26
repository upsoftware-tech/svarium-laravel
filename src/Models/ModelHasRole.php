<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Traits\UsesConnection;

class ModelHasRole extends Model
{
    use UsesConnection;

    public function role()
    {
        return $this->hasOne(Role::class, 'id', 'role_id');
    }

    public function tenant()
    {
        return $this->hasOne(Tenant::class, 'id', 'tenant_id');
    }
}
