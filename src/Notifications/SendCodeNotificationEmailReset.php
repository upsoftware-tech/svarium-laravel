<?php

namespace Upsoftware\Svarium\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendCodeNotificationEmailReset extends Notification
{
    use Queueable;

    public $code;
    public $expired_at;

    /**
     * Create a new notification instance.
     */
    public function __construct($code, $expired_at)
    {
        $this->code = $code;
        $this->expired_at = $expired_at;
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
            ->subject(__('svarium::email.Your one-time code :code for password reset', ['code' => $this->code]))
            ->greeting(__('svarium::email.Hello!'))
            ->line(__('svarium::email.We received a request to reset the password for your account in the :system system.', ['system' => config('app.name')]))
            ->line(__('svarium::email.To confirm the request to set a new password, enter the code below:'))
            ->line($this->code)
            ->line(__('svarium::email.The code and the link will expire in 30 minutes (:expires).', ['expires' => $this->expired_at]))
            ->line(__('svarium::email.If you did not request a verification code, you can safely ignore this message. If the message keeps repeating, please contact us.'))
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
