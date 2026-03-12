<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Upsoftware\Svarium\Traits\UsesConnection;

class TenantSubscription extends Model
{
    use UsesConnection;

    protected $table = 'tenant_subscriptions';

    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'total_price' => 'decimal:2',
        'meta' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(TenantSubscriptionItem::class, 'tenant_subscription_id');
    }
}

