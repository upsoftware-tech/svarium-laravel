<?php

namespace Upsoftware\Svarium\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Support\HtmlString;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginFromNewDeviceNotify extends Notification
{
    use Queueable;

    public $device;

    /**
     * Create a new notification instance.
     */
    public function __construct($device)
    {
        $this->device = $device;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->greeting(__('Hello!'))
            ->subject(__('svarium::email.We have detected a new login to your account'))
            ->line(new HtmlString('
            <strong>IP:</strong> '.$this->device["ip"].' <br />
            <strong>'.__('svarium::email.Device').':</strong> '.$this->device["deviceType"].' <br />
            <strong>'.__('svarium::email.Operating system').':</strong> '.$this->device["platform"].' '.strtr($this->device["platformVer"], ['_' => '.']).'<br />
            <strong>'.__('svarium::email.Browser').':</strong> '.$this->device["browser"].' '.$this->device["browserVer"].'
            '))
            ->line(__('svarium::email.If this was your login, you do not need to do anything.'))
            ->line(__('svarium::email.If you do not recognise this login, change your password immediately and contact us.'))
            ->salutation(__('svarium::email.Team :system', ['system' => config('app.name')]));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
