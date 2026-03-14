<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Upsoftware\Svarium\Traits\UsesConnection;

class TranslationKey extends Model
{
    use UsesConnection;

    protected $table = 'translation_keys';

    protected $guarded = [];

    protected $casts = [
        'placeholders' => 'array',
        'status' => 'boolean',
        'max_length' => 'integer',
        'sort' => 'integer',
        'meta' => 'array',
    ];

    public function keyset(): BelongsTo
    {
        return $this->belongsTo(TranslationKeyset::class, 'translation_keyset_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(TranslationValue::class, 'translation_key_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(TranslationRevision::class, 'translation_key_id');
    }
}

