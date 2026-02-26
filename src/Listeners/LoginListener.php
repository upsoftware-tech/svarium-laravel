<?php

namespace Upsoftware\Svarium\Listeners;

use Illuminate\Support\Facades\Auth;
use Upsoftware\Svarium\Facades\DeviceTracker;

class LoginListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        if (Auth::guard('web')->check()) {
            $user = $event->user;
            if (!$user->deviceShouldBeDetected()) {
                return;
            }
            DeviceTracker::detectFindAndUpdate();
        }
    }
}
