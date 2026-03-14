<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Upsoftware\Svarium\Traits\UsesConnection;

class TranslationOrder extends Model
{
    use UsesConnection;

    protected $table = 'translation_orders';

    protected $guarded = [];

    protected $casts = [
        'target_locales' => 'array',
        'due_at' => 'datetime',
        'meta' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(TranslationOrderItem::class, 'translation_order_id');
    }
}

