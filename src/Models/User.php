<?php

namespace Upsoftware\Svarium\Models;

use App\Models\User as UserBase;
use Upsoftware\Svarium\Traits\HasSetting;
use Upsoftware\Svarium\Traits\UseDevices;
use Upsoftware\Svarium\Traits\UsesConnection;

class User extends UserBase {
    use HasSetting, UsesConnection, UseDevices;

    public function routeNotificationForSms()
    {
        return $this->phone_number;
    }
}
