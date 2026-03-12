<?php

namespace Upsoftware\Svarium\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Support\HtmlString;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Upsoftware\Svarium\Notifications\Concerns\InteractsWithNotificationTemplates;
use Upsoftware\Svarium\Services\Notifications\NotificationTemplateStoreService;

class LoginFromNewDeviceNotify extends Notification
{
    use Queueable;
    use InteractsWithNotificationTemplates;

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
        $subject = __('svarium::email.We have detected a new login to your account');
        $variables = [
            'ip' => (string) ($this->device['ip'] ?? ''),
            'deviceType' => (string) ($this->device['deviceType'] ?? ''),
            'platform' => (string) ($this->device['platform'] ?? ''),
            'platformVer' => strtr((string) ($this->device['platformVer'] ?? ''), ['_' => '.']),
            'browser' => (string) ($this->device['browser'] ?? ''),
            'browserVer' => (string) ($this->device['browserVer'] ?? ''),
            'system' => (string) config('app.name'),
        ];

        $localeTemplate = app(NotificationTemplateStoreService::class)
            ->localeTemplate(static::class, (string) app()->getLocale());
        $hasOverride = trim((string) ($localeTemplate['subject'] ?? '')) !== ''
            || trim((string) ($localeTemplate['body'] ?? '')) !== '';

        if (! $hasOverride) {
            return (new MailMessage)
                ->greeting(__('Hello!'))
                ->subject($subject)
                ->line(new HtmlString('
                <strong>IP:</strong> '.$variables['ip'].' <br />
                <strong>'.__('svarium::email.Device').':</strong> '.$variables['deviceType'].' <br />
                <strong>'.__('svarium::email.Operating system').':</strong> '.$variables['platform'].' '.$variables['platformVer'].'<br />
                <strong>'.__('svarium::email.Browser').':</strong> '.$variables['browser'].' '.$variables['browserVer'].'
                '))
                ->line(__('svarium::email.If this was your login, you do not need to do anything.'))
                ->line(__('svarium::email.If you do not recognise this login, change your password immediately and contact us.'))
                ->salutation(__('svarium::email.Team :system', ['system' => config('app.name')]));
        }

        $defaultBody = [
            __('IP').': '.$variables['ip'],
            __('svarium::email.Device').': '.$variables['deviceType'],
            __('svarium::email.Operating system').': '.$variables['platform'].' '.$variables['platformVer'],
            __('svarium::email.Browser').': '.$variables['browser'].' '.$variables['browserVer'],
            __('svarium::email.If this was your login, you do not need to do anything.'),
            __('svarium::email.If you do not recognise this login, change your password immediately and contact us.'),
        ];

        $rendered = $this->renderTemplateContent($subject, $defaultBody, $variables);

        $message = (new MailMessage)
            ->greeting(__('Hello!'))
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
