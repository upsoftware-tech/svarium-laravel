<?php

namespace Upsoftware\Svarium\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Upsoftware\Svarium\Notifications\Concerns\InteractsWithNotificationTemplates;

class UserChangePasswordNotify extends Notification
{
    use Queueable;
    use InteractsWithNotificationTemplates;

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
        $subject = __('svarium::email.Confirmation of password change');
        $defaultBody = [
            __('svarium::email.Your password for accessing the :system panel has been changed.', ['system' => config('app.name')]),
            __('svarium::email.Please remember this the next time you log in.'),
            __('svarium::email.If you have not changed your password or believe this message to be incorrect, please contact us as soon as possible.'),
        ];

        $rendered = $this->renderTemplateContent($subject, $defaultBody, [
            'system' => (string) config('app.name'),
        ]);

        $message = (new MailMessage)
            ->greeting(__('svarium::email.Hello!'))
            ->subject((string) ($rendered['subject'] ?? $subject))
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
