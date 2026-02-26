<?php

namespace Upsoftware\Svarium\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeviceHijacked
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $device;
    public $user;


    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(string $message, Model $device, ?Model $user)
    {
        $this->message = $message;
        $this->device = $device;
        $this->user = $user;
    }
}
