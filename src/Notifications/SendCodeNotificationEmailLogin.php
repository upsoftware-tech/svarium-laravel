<?php

namespace Upsoftware\Svarium\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Upsoftware\Svarium\Notifications\Concerns\InteractsWithNotificationTemplates;

class SendCodeNotificationEmailLogin extends Notification
{
    use Queueable;
    use InteractsWithNotificationTemplates;

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
        $subject = __('svarium::email.Your one-time login code :code', ['code' => $this->code]);
        $defaultBody = [
            __('svarium::email.We received a request to log in to your account in the').' '.config('app.name'),
            __('svarium::email.To confirm the login, enter the code below:'),
            (string) $this->code,
            __('svarium::email.The code and the link will expire in 30 minutes (:expires).', ['expires' => $this->expired_at]),
            __('svarium::email.If you did not request a verification code, you can safely ignore this message. If the message keeps repeating, please contact us.'),
        ];

        $rendered = $this->renderTemplateContent($subject, $defaultBody, [
            'code' => (string) $this->code,
            'expires' => (string) $this->expired_at,
            'system' => (string) config('app.name'),
        ]);

        $message = (new MailMessage)
            ->subject((string) ($rendered['subject'] ?? $subject))
            ->greeting(__('svarium::email.Hello!'))
            ->salutation(__('svarium::email.Team :system', ['system' => config('app.name')]));

        $this->appendTemplateParagraphLines($message, (string) ($rendered['body'] ?? ''));

        return $message;
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
