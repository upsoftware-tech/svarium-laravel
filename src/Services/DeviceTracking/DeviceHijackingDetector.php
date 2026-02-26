<?php

namespace Upsoftware\Svarium\Services\DeviceTracking;

use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Models\Device;

interface DeviceHijackingDetector
{
    public function detect(Device $device, ?Model $user);
}
