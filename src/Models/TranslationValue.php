<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Upsoftware\Svarium\Traits\UsesConnection;

class TranslationValue extends Model
{
    use UsesConnection;

    protected $table = 'translation_values';

    protected $guarded = [];

    protected $casts = [
        'is_machine' => 'boolean',
        'version' => 'integer',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'meta' => 'array',
    ];

    public function key(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'translation_key_id');
    }
}

