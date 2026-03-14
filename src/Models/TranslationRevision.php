<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Upsoftware\Svarium\Traits\UsesConnection;

class TranslationRevision extends Model
{
    use UsesConnection;

    protected $table = 'translation_revisions';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'meta' => 'array',
    ];

    public function key(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'translation_key_id');
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(TranslationValue::class, 'translation_value_id');
    }
}

