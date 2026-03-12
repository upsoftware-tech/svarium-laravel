<?php

namespace Upsoftware\Svarium\Services\Notifications;

use Carbon\CarbonImmutable;

class NotificationTemplateStoreService
{
    protected string $settingKey = 'notification_templates';

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $settingModel = $this->settingModelClass();
        $stored = $settingModel::getSettingGlobal($this->settingKey, []);

        return is_array($stored) ? $stored : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function byClass(string $notificationClass): array
    {
        $all = $this->all();
        $templates = (array) ($all['templates'] ?? []);
        $template = $templates[$notificationClass] ?? [];

        return is_array($template) ? $template : [];
    }

    /**
     * @return array{subject:string,body:string}
     */
    public function localeTemplate(string $notificationClass, string $locale): array
    {
        $template = $this->byClass($notificationClass);
        $locale = $this->normalizeLocale($locale);

        $locales = (array) ($template['locales'] ?? []);
        $payload = $locales[$locale] ?? [];

        return [
            'subject' => (string) ($payload['subject'] ?? ''),
            'body' => (string) ($payload['body'] ?? ''),
        ];
    }

    public function saveLocaleTemplate(
        string $notificationClass,
        string $templateKey,
        string $locale,
        string $subject,
        string $body
    ): void {
        $locale = $this->normalizeLocale($locale);
        $subject = trim($subject);
        $body = trim($body);

        $all = $this->all();
        $templates = (array) ($all['templates'] ?? []);
        $template = $templates[$notificationClass] ?? [];
        $template = is_array($template) ? $template : [];

        $locales = (array) ($template['locales'] ?? []);

        if ($subject === '' && $body === '') {
            unset($locales[$locale]);
        } else {
            $locales[$locale] = [
                'subject' => $subject,
                'body' => $body,
            ];
        }

        if ($locales === []) {
            unset($templates[$notificationClass]);
        } else {
            $template['key'] = $templateKey;
            $template['class'] = $notificationClass;
            $template['locales'] = $locales;
            $template['updated_at'] = CarbonImmutable::now()->toIso8601String();

            $templates[$notificationClass] = $template;
        }

        $payload = $all;
        $payload['templates'] = $templates;

        $settingModel = $this->settingModelClass();
        $settingModel::setSettingGlobal($this->settingKey, $payload, true);
    }

    protected function normalizeLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));

        return $locale !== '' ? $locale : strtolower((string) config('app.locale', 'en'));
    }

    protected function settingModelClass(): string
    {
        return (string) config('upsoftware.models.setting', \Upsoftware\Svarium\Models\Setting::class);
    }
}

