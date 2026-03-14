<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Upsoftware\Svarium\Traits\UsesConnection;

class TranslationOrderItem extends Model
{
    use UsesConnection;

    protected $table = 'translation_order_items';

    protected $guarded = [];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(TranslationOrder::class, 'translation_order_id');
    }

    public function key(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'translation_key_id');
    }
}

