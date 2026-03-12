<?php

namespace Upsoftware\Svarium\Services\Notifications;

class NotificationTemplateRenderer
{
    public function __construct(
        protected NotificationTemplateStoreService $store
    ) {
    }

    /**
     * @param  array<string, scalar|null>  $variables
     * @return array{subject:string,body:string}
     */
    public function render(
        string $notificationClass,
        string $defaultSubject,
        string $defaultBody,
        array $variables = [],
        ?string $locale = null
    ): array {
        $resolvedLocale = $this->resolveLocale($locale);
        $template = $this->store->localeTemplate($notificationClass, $resolvedLocale);

        $subject = trim((string) ($template['subject'] ?? ''));
        $body = trim((string) ($template['body'] ?? ''));

        $subject = $subject !== '' ? $this->replaceVariables($subject, $variables) : $defaultSubject;
        $body = $body !== '' ? $this->replaceVariables($body, $variables) : $defaultBody;

        return [
            'subject' => (string) $subject,
            'body' => (string) $body,
        ];
    }

    /**
     * @param  array<string, scalar|null>  $variables
     */
    protected function replaceVariables(string $template, array $variables): string
    {
        if ($variables === []) {
            return $template;
        }

        $replace = [];

        foreach ($variables as $key => $value) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            $replace['{'.$normalizedKey.'}'] = (string) ($value ?? '');
            $replace['{{'.$normalizedKey.'}}'] = (string) ($value ?? '');
        }

        if ($replace === []) {
            return $template;
        }

        return strtr($template, $replace);
    }

    protected function resolveLocale(?string $locale): string
    {
        $resolved = trim((string) ($locale ?? app()->getLocale()));

        return $resolved !== '' ? strtolower($resolved) : strtolower((string) config('app.locale', 'en'));
    }
}

