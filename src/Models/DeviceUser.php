<?php

namespace Upsoftware\Svarium\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Upsoftware\Svarium\Traits\UsesConnection;

class DeviceUser extends Pivot
{
    use UsesConnection;

    protected $casts = [
        'verified_at' => 'datetime',
        'reported_as_rogue_at' => 'datetime',
        'data' => 'array',
    ];
    protected $guarded = [];
    protected $hidden = [
        'note','admin_note'
    ];


    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(Device::getUserClass());
    }
}
