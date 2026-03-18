<?php

namespace Upsoftware\Svarium\Modules\Builtin\SystemMailTemplate\Panel;

use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Services\Notifications\NotificationCatalogService;
use Upsoftware\Svarium\Services\Notifications\NotificationTemplateStoreService;
use Upsoftware\Svarium\UI\Components\Badge;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\Form\Input;
use Upsoftware\Svarium\UI\Components\Form\Textarea;
use Upsoftware\Svarium\UI\Components\Link;
use Upsoftware\Svarium\UI\Components\Text;

class SystemMailTemplateEditOperation extends Operation
{
    public static string|array $panels = '*';

    public static function uri(): string
    {
        return 'system/mail-templates/{template}/edit';
    }

    public static function methods(): array
    {
        return ['GET', 'POST'];
    }

    public function execution(): ExecutionMode
    {
        return ExecutionMode::FORM;
    }

    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
        ];
    }

    public function title(PanelContext $context, string $template): string
    {
        $notification = $this->resolveNotification($template);

        if (! is_array($notification)) {
            return __('Szablon mailowy');
        }

        return __('Edycja szablonu: :name', [
            'name' => (string) ($notification['label'] ?? __('Szablon mailowy')),
        ]);
    }

    public function schema(PanelContext $context, string $template): array
    {
        $notification = $this->resolveNotification($template);
        if (! is_array($notification)) {
            abort(404);
        }

        $locale = $this->currentLocale();
        $defaults = $this->resolveDefaultTemplate((string) ($notification['class'] ?? ''));
        $stored = app(NotificationTemplateStoreService::class)->localeTemplate(
            (string) ($notification['class'] ?? ''),
            $locale
        );
        $subjectValue = trim((string) ($stored['subject'] ?? '')) !== ''
            ? (string) ($stored['subject'] ?? '')
            : (string) ($defaults['subject'] ?? '');
        $bodyValue = trim((string) ($stored['body'] ?? '')) !== ''
            ? (string) ($stored['body'] ?? '')
            : (string) ($defaults['body'] ?? '');
        $placeholderTokens = $this->normalizePlaceholders((array) ($notification['placeholders'] ?? []));
        $placeholders = $this->formatPlaceholders($placeholderTokens);

        return [
            Block::make()
                ->appearance('space-y-3')
                ->children([
                    Link::make(__('Wróć do listy'))
                        ->panelHref('system/mail-templates')
                        ->appearance('text-sm underline'),
                    Text::make((string) ($notification['label'] ?? __('Szablon mailowy')))
                        ->headline('h2')
                        ->fontWeight('semibold'),
                    Text::make((string) ($notification['class'] ?? ''))
                        ->appearance('text-xs text-slate-500'),
                    Block::make()
                        ->appearance('flex gap-2 items-center flex-wrap')
                        ->children([
                            Badge::make(__('Język: :locale', ['locale' => strtoupper($locale)]))
                                ->variant('secondary'),
                            Badge::make(__('template_key: :key', ['key' => (string) ($notification['key'] ?? '')]))
                                ->variant('secondary'),
                        ]),
                    Text::make(__('Dostępne placeholdery: :placeholders', ['placeholders' => $placeholders]))
                        ->appearance('text-xs text-slate-500'),
                ]),
            Input::make('subject')
                ->label(__('Temat wiadomości'))
                ->value($subjectValue)
                ->placeholder(__('Np. Kod logowania: {code}')),
            Textarea::make('body')
                ->label(__('Treść wiadomości'))
                ->value($bodyValue)
                ->tiptap()
                ->placeholders($placeholderTokens)
                ->placeholder(__('Np. Witaj! Twój kod: {code}'))
                ->appearance('min-h-56'),
            Text::make(__('Pozostaw oba pola puste, aby usunąć nadpisanie i wrócić do domyślnej treści z klasy Notification.'))
                ->appearance('text-xs text-slate-500'),
        ];
    }

    protected function save(PanelContext $context, string $template): RedirectResult
    {
        $notification = $this->resolveNotification($template);
        if (! is_array($notification)) {
            abort(404);
        }

        $validated = $context->validated();
        $locale = $this->currentLocale();

        app(NotificationTemplateStoreService::class)->saveLocaleTemplate(
            (string) ($notification['class'] ?? ''),
            (string) ($notification['key'] ?? ''),
            $locale,
            (string) ($validated['subject'] ?? ''),
            (string) ($validated['body'] ?? '')
        );

        return RedirectResult::to(
            panel_href('system/mail-templates/'.$template.'/edit', $context->panel()->name)
        )->success(__('Zapisano szablon wiadomości.'));
    }

    /**
     * @return array{
     *   id:string,
     *   key:string,
     *   class:string,
     *   label:string,
     *   source:string,
     *   source_key:string,
     *   file:string,
     *   placeholders:array<int,string>
     * }|null
     */
    protected function resolveNotification(string $template): ?array
    {
        return app(NotificationCatalogService::class)->findById($template);
    }

    /**
     * @param  array<int, string>  $placeholders
     */
    protected function formatPlaceholders(array $placeholders): string
    {
        $normalized = $this->normalizePlaceholders($placeholders);

        if ($normalized === []) {
            return '{system}';
        }

        return implode(', ', array_map(static fn (string $item): string => '{'.$item.'}', $normalized));
    }

    /**
     * @param  array<int, string>  $placeholders
     * @return array<int, string>
     */
    protected function normalizePlaceholders(array $placeholders): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $placeholders
        ), static fn (string $item): bool => $item !== ''));
    }

    protected function currentLocale(): string
    {
        $locale = trim((string) app()->getLocale());

        return $locale !== '' ? strtolower($locale) : strtolower((string) config('app.locale', 'en'));
    }

    /**
     * @return array{subject:string,body:string}
     */
    protected function resolveDefaultTemplate(string $notificationClass): array
    {
        $basename = class_basename($notificationClass);

        return match ($basename) {
            'SendCodeNotificationEmailLogin' => [
                'subject' => __('svarium::email.Your one-time login code :code', ['code' => '{code}']),
                'body' => implode("\n", [
                    __('svarium::email.We received a request to log in to your account in the').' {system}',
                    __('svarium::email.To confirm the login, enter the code below:'),
                    '{code}',
                    __('svarium::email.The code and the link will expire in 30 minutes (:expires).', ['expires' => '{expires}']),
                    __('svarium::email.If you did not request a verification code, you can safely ignore this message. If the message keeps repeating, please contact us.'),
                ]),
            ],
            'SendCodeNotificationEmailRegister' => [
                'subject' => __('svarium::email.Your one-time login code :code', ['code' => '{code}']),
                'body' => implode("\n", [
                    __('svarium::email.We received a request to log in to your account in the :system system.', ['system' => '{system}']),
                    __('svarium::email.To confirm the login, enter the code below:'),
                    '{code}',
                    __('svarium::email.The code and the link will expire in 30 minutes (:expires).', ['expires' => '{expires}']),
                    __('svarium::email.If you did not request a verification code, you can safely ignore this message. If the message keeps repeating, please contact us.'),
                ]),
            ],
            'SendCodeNotificationEmailReset' => [
                'subject' => __('svarium::email.Your one-time code :code for password reset', ['code' => '{code}']),
                'body' => implode("\n", [
                    __('svarium::email.We received a request to reset the password for your account in the :system system.', ['system' => '{system}']),
                    __('svarium::email.To confirm the request to set a new password, enter the code below:'),
                    '{code}',
                    __('svarium::email.The code and the link will expire in 30 minutes (:expires).', ['expires' => '{expires}']),
                    __('svarium::email.If you did not request a verification code, you can safely ignore this message. If the message keeps repeating, please contact us.'),
                ]),
            ],
            'UserChangePasswordNotify' => [
                'subject' => __('svarium::email.Confirmation of password change'),
                'body' => implode("\n", [
                    __('svarium::email.Your password for accessing the :system panel has been changed.', ['system' => '{system}']),
                    __('svarium::email.Please remember this the next time you log in.'),
                    __('svarium::email.If you have not changed your password or believe this message to be incorrect, please contact us as soon as possible.'),
                ]),
            ],
            'LoginFromNewDeviceNotify' => [
                'subject' => __('svarium::email.We have detected a new login to your account'),
                'body' => implode("\n", [
                    __('IP').': {ip}',
                    __('svarium::email.Device').': {deviceType}',
                    __('svarium::email.Operating system').': {platform} {platformVer}',
                    __('svarium::email.Browser').': {browser} {browserVer}',
                    __('svarium::email.If this was your login, you do not need to do anything.'),
                    __('svarium::email.If you do not recognise this login, change your password immediately and contact us.'),
                ]),
            ],
            default => [
                'subject' => '',
                'body' => '',
            ],
        };
    }
}
