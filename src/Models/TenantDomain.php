<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Upsoftware\Svarium\Traits\UsesConnection;

class TenantDomain extends Model
{
    use UsesConnection;

    protected $table = 'tenant_domains';

    protected $fillable = [
        'tenant_id',
        'domain',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }
}
