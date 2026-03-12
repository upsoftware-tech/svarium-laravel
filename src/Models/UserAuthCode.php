<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Traits\UsesConnection;

class UserAuthCode extends Model
{
    use UsesConnection;

    public $guarded = [];

    protected $casts = [
        'expired_at' => 'datetime',
        'used_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    public function userAuth(): BelongsTo
    {
        return $this->belongsTo(get_model('user_auth'));
    }
}
