<?php

namespace Upsoftware\Svarium\Facades;

use Illuminate\Support\Facades\Facade;

/**
 */
class DeviceTracker extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'device-tracking';
    }
}
