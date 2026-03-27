<?php

namespace Upsoftware\Svarium\Modules\Builtin\MenuManager\Panel;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Throwable;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\Form\Input;
use Upsoftware\Svarium\UI\Components\Form\SelectIcon;
use Upsoftware\Svarium\UI\Components\LocaleInline;
use Upsoftware\Svarium\UI\Components\Text;

class MenuManagerEditOperation extends Operation
{
    public static string|array $panels = '*';

    public static function uri(): string
    {
        return 'system/menu-manager/edit/{menuKey}';
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
            'label' => ['required', 'array'],
        ];
    }

    public function title(PanelContext $context, string $menuKey): string
    {
        return __('Edit menu item');
    }

    public function authorize(PanelContext $context): bool
    {
        return $this->isSuperadmin($context->request()->user() ?? auth()->user());
    }

    protected function formLayout(PanelContext $context, ...$args): array
    {
        $selectedNavigation = $this->resolveSelectedNavigationToken($context);

        return [
            'view' => 'card',
            'title' => __('Edit menu item'),
            'subtitle' => __('Update menu label and icon'),
            'icon' => 'lucide:menu',
            'backUrl' => $this->menuManagerUrl($selectedNavigation),
            'contentCols' => 1,
            'contentGap' => 4,
            'paddingContent' => '4',
            'action' => LocaleInline::make('1')
                ->languageSelector()
                ->showIcon(false)
                ->showLabel(true)
                ->multiple(false),
        ];
    }

    public function schema(PanelContext $context, string $menuKey): array
    {
        $decodedMenuKey = urldecode($menuKey);
        $selectedNavigation = $this->resolveSelectedNavigationToken($context);
        $node = $this->resolveNodeByMenuKey($decodedMenuKey, $selectedNavigation);
        $normalizedKey = $this->normalizeOverrideNodeKey($decodedMenuKey);

        if ($node === null) {
            abort(404);
        }

        $label = $this->resolveLabelValueForEditor(
            $node,
            $normalizedKey,
            $this->normalizeNavigationBucket($selectedNavigation)
        );
        $url = trim((string) ($node['url'] ?? ''));
        $icon = trim((string) ($node['icon']['value'] ?? ''));

        return [
            Block::make()
                ->appearance('space-y-3')
                ->children([
                    Text::make(__('Menu key: :key', ['key' => $decodedMenuKey]))
                        ->appearance('text-xs text-slate-500'),
                    $url !== ''
                        ? Text::make(__('URL: :url', ['url' => $url]))
                            ->appearance('text-xs text-slate-500')
                        : Text::make(__('No URL assigned'))
                            ->appearance('text-xs text-slate-400'),
                ]),
            Input::make('label')
                ->label(__('Label'))
                ->value($label)
                ->required()
                ->language()
                ->max(255),
            Input::make('menu_key')
                ->label(__('Key'))
                ->value($decodedMenuKey)
                ->prop('disabled', true),
            SelectIcon::make('icon')
                ->collections(['lucide'])
                ->value($icon)
                ->label(__('Icon')),
        ];
    }

    protected function save(PanelContext $context, string $menuKey): RedirectResult
    {
        $decodedMenuKey = urldecode($menuKey);
        $selectedNavigation = $this->resolveSelectedNavigationToken($context);
        $bucket = $this->normalizeNavigationBucket($selectedNavigation);
        $node = $this->resolveNodeByMenuKey($decodedMenuKey, $selectedNavigation);

        if ($node === null) {
            abort(404);
        }

        $validated = $context->validated();
        $label = $validated['label'] ?? [];

        $existing = setting('menu_manager.overrides', []);
        if (! is_array($existing)) {
            $existing = [];
        }

        $currentBucket = $existing[$bucket] ?? [];
        if (! is_array($currentBucket)) {
            $currentBucket = [];
        }

        $normalizedKey = $this->normalizeOverrideNodeKey($decodedMenuKey);
        if ($normalizedKey === '') {
            return RedirectResult::to($this->menuManagerUrl($selectedNavigation))
                ->warning(__('Invalid menu key.'));
        }

        $entry = $currentBucket[$normalizedKey] ?? [];
        if (! is_array($entry)) {
            $entry = [];
        }

        $resolvedLabel = null;

        if (is_array($label)) {
            $translations = $this->normalizeTranslations($label);
            if ($translations !== []) {
                $translationKey = $this->resolveMenuTranslationKey($normalizedKey);
                $this->persistMenuTranslationsToPhpFiles($translationKey, $translations);
                $resolvedLabel = $translationKey;
            }
        } else {
            $plainLabel = trim((string) $label);
            if ($plainLabel !== '') {
                $resolvedLabel = $plainLabel;
            }
        }

        if (is_string($resolvedLabel) && $resolvedLabel !== '') {
            $entry['label'] = $resolvedLabel;
        } else {
            unset($entry['label']);
        }

        $currentBucket[$normalizedKey] = $entry;
        $existing[$bucket] = $currentBucket;

        $settingModel = (string) config('upsoftware.models.setting', \Upsoftware\Svarium\Models\Setting::class);
        if (class_exists($settingModel) && method_exists($settingModel, 'setSettingGlobal')) {
            $settingModel::setSettingGlobal('menu_manager.overrides', $existing, true);
        }

        return RedirectResult::to($this->menuManagerUrl($selectedNavigation))
            ->success(__('Menu item has been updated.'));
    }

    /**
     * @return array<string, string>|string
     */
    protected function resolveLabelValueForEditor(
        array $node,
        string $normalizedKey = '',
        string $bucket = 'main_menu'
    ): array|string
    {
        $rawLabel = trim((string) ($this->resolveStoredOverrideLabel($normalizedKey, $bucket) ?? ''));
        if ($rawLabel === '') {
            $rawLabel = trim((string) ($node['label'] ?? ''));
        }

        if ($rawLabel === '') {
            return '';
        }

        if (! str_starts_with($rawLabel, 'messages.')) {
            $translations = [];
            foreach ($this->resolveLocaleCodes() as $locale) {
                $translations[$locale] = $rawLabel;
            }

            if ($translations !== []) {
                return $translations;
            }

            $locale = $this->resolveCurrentLocaleCode();

            return [$locale => $rawLabel];
        }

        $path = trim(substr($rawLabel, strlen('messages.')));
        if ($path === '') {
            $locale = $this->resolveCurrentLocaleCode();

            return [$locale => $rawLabel];
        }

        $translations = [];
        $locales = $this->resolveLocaleCodes();
        foreach ($locales as $locale) {
            $messages = $this->loadMessagesTranslations($locale);
            $value = Arr::get($messages, $path);
            if (! is_scalar($value)) {
                $translations[$locale] = '';
                continue;
            }

            $text = trim((string) $value);
            $translations[$locale] = $text;
        }

        $nonEmptyValues = array_values(array_filter(
            $translations,
            static fn (string $value): bool => trim($value) !== ''
        ));

        if ($nonEmptyValues !== []) {
            $fallbackValue = trim((string) ($nonEmptyValues[0] ?? ''));
            if ($fallbackValue !== '') {
                foreach ($translations as $locale => $value) {
                    if (trim((string) $value) === '') {
                        $translations[$locale] = $fallbackValue;
                    }
                }
            }

            return $translations;
        }

        $locale = $this->resolveCurrentLocaleCode();

        return [$locale => $rawLabel];
    }

    protected function resolveStoredOverrideLabel(string $normalizedKey, string $bucket = 'main_menu'): ?string
    {
        $normalizedKey = trim($normalizedKey);
        if ($normalizedKey === '') {
            return null;
        }

        $existing = setting('menu_manager.overrides', []);
        if (! is_array($existing)) {
            return null;
        }

        $bucketEntries = $existing[$this->normalizeNavigationBucket($bucket)] ?? null;
        if (! is_array($bucketEntries)) {
            return null;
        }

        $entry = $bucketEntries[$normalizedKey] ?? null;
        if (! is_array($entry)) {
            return null;
        }

        $label = trim((string) ($entry['label'] ?? ''));

        return $label !== '' ? $label : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveNodeByMenuKey(string $menuKey, string|int|null $navigation = 'main_menu'): ?array
    {
        $menuKey = trim($menuKey);
        if ($menuKey === '') {
            return null;
        }

        $tree = menu_children($navigation);
        if (! is_array($tree)) {
            return null;
        }

        return $this->findNodeInTree($tree, $menuKey);
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @return array<string, mixed>|null
     */
    protected function findNodeInTree(array $nodes, string $menuKey): ?array
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $nodeValue = trim((string) ($node['__menu_key'] ?? $node['id'] ?? ''));
            if ($nodeValue === $menuKey) {
                return $node;
            }

            $children = $node['children'] ?? null;
            if (is_array($children) && $children !== []) {
                $found = $this->findNodeInTree($children, $menuKey);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    protected function normalizeOverrideNodeKey(string $nodeKey): string
    {
        $nodeKey = trim($nodeKey);
        if ($nodeKey === '') {
            return '';
        }

        if (str_starts_with($nodeKey, 'menu:') || str_starts_with($nodeKey, 'id:')) {
            return $nodeKey;
        }

        if (str_starts_with($nodeKey, 'navigation-static-')) {
            return 'id:'.$nodeKey;
        }

        return 'menu:'.$nodeKey;
    }

    protected function normalizeNavigationBucket(mixed $bucket): string
    {
        $normalized = strtolower(trim((string) $bucket));

        if ($normalized === '' || in_array($normalized, ['main', 'main_menu', 'default', '__default'], true)) {
            return 'main_menu';
        }

        return $normalized;
    }

    protected function resolveSelectedNavigationToken(PanelContext $context): string
    {
        $request = $context->request();
        $requested = trim((string) $request->input(
            '_menu_manager_navigation',
            $request->query('menu', $request->query('navigation', ''))
        ));

        if ($requested === '') {
            $requested = $this->extractNavigationFromReferer($request->headers->get('referer'));
        }

        return $requested !== '' ? $requested : 'main_menu';
    }

    protected function extractNavigationFromReferer(?string $referer): string
    {
        if (! is_string($referer) || trim($referer) === '') {
            return '';
        }

        $query = parse_url($referer, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return '';
        }

        parse_str($query, $params);
        if (! is_array($params)) {
            return '';
        }

        return trim((string) ($params['menu'] ?? $params['navigation'] ?? ''));
    }

    protected function menuManagerUrl(string $navigationToken = 'main_menu'): string
    {
        $base = panel_href('system/menu-manager');

        return $base.'?menu='.rawurlencode($navigationToken);
    }

    /**
     * @param  array<string, mixed>  $translations
     * @return array<string, string>
     */
    protected function normalizeTranslations(array $translations): array
    {
        $normalized = [];

        foreach ($translations as $locale => $value) {
            $code = strtolower(trim((string) $locale));
            $text = trim((string) $value);

            if ($code === '' || $text === '') {
                continue;
            }

            $normalized[$code] = $text;
        }

        return $normalized;
    }

    protected function resolveMenuTranslationKey(string $normalizedKey): string
    {
        $suffix = strtolower(trim($normalizedKey));
        $suffix = preg_replace('/[^a-z0-9_]+/i', '_', $suffix) ?? '';
        $suffix = trim($suffix, '_');

        if ($suffix === '') {
            $suffix = sha1($normalizedKey);
        }

        return 'messages.menu_manager.items.'.$suffix;
    }

    /**
     * @param  array<string, string>  $translations
     */
    protected function persistMenuTranslationsToPhpFiles(string $translationKey, array $translations): void
    {
        $translationKey = trim($translationKey);
        if (! str_starts_with($translationKey, 'messages.')) {
            return;
        }

        $pathKey = trim(substr($translationKey, strlen('messages.')));
        if ($pathKey === '') {
            return;
        }

        foreach ($translations as $locale => $value) {
            $messages = $this->loadMessagesTranslations($locale);
            Arr::set($messages, $pathKey, $value);
            $this->saveMessagesTranslations($locale, $messages);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function resolveLocaleCodes(): array
    {
        $locales = [];

        $fromSettings = setting('locales', []);
        if (is_array($fromSettings)) {
            foreach ($fromSettings as $key => $item) {
                $code = '';

                if (is_array($item)) {
                    $code = trim((string) ($item['value'] ?? $item['code'] ?? $item['locale'] ?? $key));
                } else {
                    $code = trim((string) $key);
                }

                if ($code === '') {
                    continue;
                }

                $locales[] = strtolower($code);
            }
        }

        if (function_exists('locales')) {
            try {
                $configured = locales();
                if (is_array($configured)) {
                    foreach ($configured as $key => $item) {
                        if (is_array($item)) {
                            $code = trim((string) ($item['value'] ?? $item['code'] ?? $item['locale'] ?? $key));
                        } else {
                            $code = trim((string) $key);
                        }

                        if ($code === '') {
                            continue;
                        }

                        $locales[] = strtolower($code);
                    }
                }
            } catch (Throwable) {
                // ignore and fallback
            }
        }

        $appSvariumLangDir = base_path('app/Svarium/Lang');
        if (File::isDirectory($appSvariumLangDir)) {
            foreach (File::directories($appSvariumLangDir) as $localeDir) {
                $code = strtolower(trim((string) basename($localeDir)));
                if ($code === '') {
                    continue;
                }

                $locales[] = $code;
            }
        }

        if (function_exists('lang_path')) {
            $appLangDir = lang_path();
            if (is_string($appLangDir) && $appLangDir !== '' && File::isDirectory($appLangDir)) {
                foreach (File::directories($appLangDir) as $localeDir) {
                    $code = strtolower(trim((string) basename($localeDir)));
                    if ($code === '') {
                        continue;
                    }

                    $locales[] = $code;
                }
            }
        }

        if ($locales === []) {
            $locales[] = $this->resolveCurrentLocaleCode();
        }

        return array_values(array_unique(array_filter($locales, static fn (string $code): bool => $code !== '')));
    }

    protected function resolveCurrentLocaleCode(): string
    {
        $requested = strtolower(trim((string) request()->query('_locale', request()->input('_locale', app()->getLocale()))));
        if ($requested !== '') {
            return $requested;
        }

        return strtolower(trim((string) config('app.locale', 'en'))) ?: 'en';
    }

    protected function messagesFilePath(string $locale): string
    {
        $locale = strtolower(trim($locale));

        return base_path("app/Svarium/Lang/{$locale}/messages.php");
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadMessagesTranslations(string $locale): array
    {
        $file = $this->messagesFilePath($locale);
        if (! File::exists($file)) {
            return [];
        }

        $loaded = include $file;

        return is_array($loaded) ? $loaded : [];
    }

    /**
     * @param  array<string, mixed>  $messages
     */
    protected function saveMessagesTranslations(string $locale, array $messages): void
    {
        $file = $this->messagesFilePath($locale);
        File::ensureDirectoryExists(dirname($file));
        File::put($file, "<?php\n\nreturn ".var_export($messages, true).";\n");
    }

    protected function isSuperadmin(?object $user): bool
    {
        if (! is_object($user)) {
            return false;
        }

        if (function_exists('get_roles')) {
            try {
                foreach (get_roles($user) as $role) {
                    if (! is_array($role)) {
                        continue;
                    }

                    $roleKey = strtolower(trim((string) ($role['role_key'] ?? '')));
                    $roleName = strtolower(trim((string) ($role['name'] ?? '')));
                    $roleNameLocale = strtolower(trim((string) ($role['name_locale'] ?? '')));

                    if (
                        in_array($roleKey, ['superadmin', 'superadministrator'], true)
                        || in_array($roleName, ['superadmin', 'superadministrator'], true)
                        || in_array($roleNameLocale, ['superadmin', 'superadministrator'], true)
                    ) {
                        return true;
                    }
                }
            } catch (Throwable) {
                // fallback below
            }
        }

        if (method_exists($user, 'hasRole')) {
            try {
                return (bool) $user->hasRole('superadmin');
            } catch (Throwable) {
                return false;
            }
        }

        $roles = [];
        try {
            if (method_exists($user, 'roles')) {
                $roles = $user->roles;
            }
        } catch (Throwable) {
            return false;
        }

        if ($roles instanceof Collection) {
            return $roles->contains(static function (mixed $role): bool {
                if (! is_object($role)) {
                    return false;
                }

                $roleKey = strtolower(trim((string) ($role->role_key ?? '')));
                $roleName = strtolower(trim((string) ($role->name ?? '')));

                return in_array($roleKey, ['superadmin', 'superadministrator'], true)
                    || in_array($roleName, ['superadmin', 'superadministrator'], true);
            });
        }

        return false;
    }
}
