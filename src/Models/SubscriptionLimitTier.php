<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Upsoftware\Svarium\Traits\UsesConnection;

class SubscriptionLimitTier extends Model
{
    use UsesConnection;

    protected $table = 'subscription_limit_tiers';

    protected $guarded = [];

    protected $casts = [
        'status' => 'boolean',
        'is_unlimited' => 'boolean',
        'min_value' => 'integer',
        'max_value' => 'integer',
        'price_delta' => 'decimal:2',
        'sort' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(TenantSubscriptionItem::class, 'subscription_limit_tier_id');
    }
}

