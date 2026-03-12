<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Upsoftware\Svarium\Traits\UsesConnection;

class SubscriptionModule extends Model
{
    use UsesConnection;

    protected $table = 'subscription_modules';

    protected $guarded = [];

    protected $casts = [
        'status' => 'boolean',
        'base_price' => 'decimal:2',
        'sort' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(TenantSubscriptionItem::class, 'subscription_module_id');
    }
}

