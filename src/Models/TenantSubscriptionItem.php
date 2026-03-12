<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Upsoftware\Svarium\Traits\UsesConnection;

class TenantSubscriptionItem extends Model
{
    use UsesConnection;

    protected $table = 'tenant_subscription_items';

    protected $guarded = [];

    protected $casts = [
        'status' => 'boolean',
        'module_limit' => 'integer',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'sort' => 'integer',
        'meta' => 'array',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class, 'tenant_subscription_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(SubscriptionModule::class, 'subscription_module_id');
    }

    public function limitTier(): BelongsTo
    {
        return $this->belongsTo(SubscriptionLimitTier::class, 'subscription_limit_tier_id');
    }
}

