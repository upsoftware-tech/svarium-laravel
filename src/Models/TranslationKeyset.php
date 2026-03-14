<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Upsoftware\Svarium\Traits\UsesConnection;

class TranslationKeyset extends Model
{
    use UsesConnection;

    protected $table = 'translation_keysets';

    protected $guarded = [];

    protected $casts = [
        'status' => 'boolean',
        'meta' => 'array',
    ];

    public function keys(): HasMany
    {
        return $this->hasMany(TranslationKey::class, 'translation_keyset_id');
    }
}

