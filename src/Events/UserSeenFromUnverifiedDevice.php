<?php

namespace Upsoftware\Svarium\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserSeenFromUnverifiedDevice
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $device;
    public $user;


    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Model $device, ?Model $user)
    {
        $this->device = $device;
        $this->user = $user;
    }
}
