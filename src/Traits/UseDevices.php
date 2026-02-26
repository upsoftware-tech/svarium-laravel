<?php

namespace Upsoftware\Svarium\Traits;

use Upsoftware\Svarium\Models\Device;

trait UseDevices
{
    public function device()
    {
        return $this->belongsToMany(Device::class, 'device_user')
            ->withPivot(['verified_at', 'name', 'reported_as_rogue_at', 'note', 'admin_note', 'data'])
            ->withTimestamps();
    }

    /**
     * @return bool
     */
    public function deviceShouldBeDetected()
    {
        return true;
    }
}
