<?php

namespace Upsoftware\Svarium\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserChangePasswordNotify extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
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
            ->subject(__('svarium::email.Confirmation of password change'))
            ->line(__('svarium::email.Your password for accessing the :system panel has been changed.', ['system' => config('app.name')]))
            ->line(__('svarium::email.Please remember this the next time you log in.'))
            ->line(__('svarium::email.If you have not changed your password or believe this message to be incorrect, please contact us as soon as possible.'))
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
