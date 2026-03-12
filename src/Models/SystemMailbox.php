<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Traits\UsesConnection;

class SystemMailbox extends Model
{
    use UsesConnection;

    protected $table = 'system_mailboxes';

    protected $guarded = [];

    protected $casts = [
        'status' => 'boolean',
        'is_default' => 'boolean',
        'port' => 'integer',
        'scope_id' => 'integer',
        'config' => 'array',
        'password' => 'encrypted',
    ];
}
