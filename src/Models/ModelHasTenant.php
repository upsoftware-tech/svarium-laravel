<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Traits\UsesConnection;

class ModelHasTenant extends Model
{
    use UsesConnection;

    protected $table = 'model_has_tenants';

    protected $fillable = [
        'tenant_id',
        'model_type',
        'model_id',
    ];
}
