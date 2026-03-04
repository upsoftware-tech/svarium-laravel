<?php

namespace Upsoftware\Svarium\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Inertia\Middleware;
use Inertia\Inertia;
use Throwable;
use Upsoftware\Svarium\Models\Navigation;
use Upsoftware\Svarium\Models\Setting;
use Upsoftware\Svarium\Services\NavigationService;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        $isAuthenticated = $this->safeAuthCheck();
        $user = $isAuthenticated
            ? $this->safe(fn () => Auth::user(), null)
            : null;

        return array_merge(parent::share($request), [
            'locale' => session()->has('locale') ? session()->get('locale') : app()->getLocale(),
            'locales' => Inertia::once(fn () => locales()),
            'theme' => fn () => $request->attributes->get('svarium.theme'),
            'seo' => fn () => $request->attributes->get('svarium.seo', []),
            'domain' => fn () => $this->resolveDomainContext($request),
            'workspaces' => $this->resolveWorkspaces($request, $user),
            'title' => fn () => get_title(),
            'layout' => [
                'panel' => $this->safeLayout(),
            ],
            'alert' => [
                'success' => fn () => $request->session()->get('alert_success'),
                'error' => fn () => $request->session()->get('alert_error'),
                'warning' => fn () => $request->session()->get('alert_warning'),
                'info' => fn () => $request->session()->get('alert_info'),
                'message' => fn () => $request->session()->get('alert_message'),
            ],
            'setting' => $this->resolveSettings(),
            'navigation' => $this->resolveNavigation($isAuthenticated),
            'ziggy' => function () {
                return array_merge((new Ziggy)->toArray(), [
                    'location' => request()->url(),
                ]);
            },
        ]);
    }

    public function layout()
    {
        return layout()->getComponents();
    }

    protected function resolveWorkspaces(Request $request, mixed $user): mixed
    {
        if (! $user || ! method_exists($user, 'getWorkspaces')) {
            return false;
        }

        return $this->safe(
            fn () => $request->user()?->getWorkspaces() ?? false,
            false
        );
    }

    protected function resolveSettings(): mixed
    {
        return $this->safe(function () {
            $layout = Setting::getSettingGlobal('layout');
            $layout = is_array($layout) ? $layout : [];

            $configLogo = config('upsoftware.logo', []);
            if (is_array($configLogo) && $configLogo !== []) {
                // Source of truth for logo paths is config/upsoftware.php.
                $layout['logo'] = $configLogo;
            } elseif (! array_key_exists('logo', $layout)) {
                $layout['logo'] = [];
            }

            return $this->hydrateNavigationComponentProps($layout);
        }, (object) []);
    }

    protected function hydrateNavigationComponentProps(mixed $value): mixed
    {
        $cache = [];

        return $this->mapNavigationComponents($value, $cache);
    }

    protected function mapNavigationComponents(mixed $value, array &$cache): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $mapped = [];
        foreach ($value as $key => $item) {
            $mapped[$key] = $this->mapNavigationComponents($item, $cache);
        }

        if (! $this->isNavigationComponentConfig($mapped)) {
            return $mapped;
        }

        $props = $mapped['props'] ?? [];
        if (! is_array($props)) {
            $props = [];
        }

        $navigationId = $this->normalizeNavigationId($props['navigation_id'] ?? null);
        if ($navigationId === null) {
            // Keep explicit props provided by runtime components (e.g. PanelNavigation).
            return $mapped;
        }

        $cacheKey = (string) $navigationId;
        if (! array_key_exists($cacheKey, $cache)) {
            $cache[$cacheKey] = NavigationService::make()->getTree($navigationId);
        }

        $tree = $cache[$cacheKey];
        $items = is_array($tree)
            ? ($tree['children'] ?? [])
            : [];

        $mapped['props'] = [
            ...$props,
            'navigation_id' => $navigationId,
            'navigation' => $tree,
            'items' => $items,
            'navigations' => $items,
        ];

        return $mapped;
    }

    protected function isNavigationComponentConfig(array $value): bool
    {
        $componentName = $value['name'] ?? $value['component'] ?? null;

        return is_string($componentName)
            && in_array($componentName, ['NavigationVertical', 'NavigationHorizontal'], true);
    }

    protected function normalizeNavigationId(mixed $navigationId): string|int|null
    {
        if (is_int($navigationId)) {
            return $navigationId;
        }

        if (! is_string($navigationId)) {
            return null;
        }

        $trimmed = trim($navigationId);
        if ($trimmed === '') {
            return null;
        }

        if (ctype_digit($trimmed)) {
            return (int) $trimmed;
        }

        return $trimmed;
    }

    protected function resolveNavigation(bool $isAuthenticated): mixed
    {
        if (! $isAuthenticated) {
            return [];
        }

        return $this->safe(function () {
            return Navigation::whereNull('parent_id')->get()->mapWithKeys(function ($navigation) {
                return [
                    $navigation->id => NavigationService::make()->getTree($navigation->id),
                ];
            });
        }, []);
    }

    protected function resolveDomainContext(Request $request): array
    {
        $domain = $request->attributes->get('svarium.domain');
        $primary = $request->attributes->get('svarium.domain.primary');

        return [
            'current' => $this->serializeDomain($domain),
            'primary' => $this->serializeDomain($primary),
        ];
    }

    protected function serializeDomain(mixed $domain): ?array
    {
        if (! $domain instanceof Model) {
            return null;
        }

        return [
            'id' => $domain->getAttribute('id'),
            'tenant_id' => $domain->getAttribute('tenant_id'),
            'domain' => $domain->getAttribute('domain'),
            'is_primary' => (bool) $domain->getAttribute('is_primary'),
            'locale' => $domain->getAttribute('locale'),
            'theme' => $domain->getAttribute('theme'),
            'status' => (bool) $domain->getAttribute('status'),
            'redirect_to_primary' => (bool) $domain->getAttribute('redirect_to_primary'),
            'force_https' => (bool) $domain->getAttribute('force_https'),
        ];
    }

    protected function safeLayout(): mixed
    {
        return $this->safe(
            fn () => $this->layout(),
            []
        );
    }

    protected function safeAuthCheck(): bool
    {
        return $this->safe(
            fn () => Auth::check(),
            false
        );
    }

    protected function safe(callable $callback, mixed $fallback = null): mixed
    {
        try {
            return $callback();
        } catch (Throwable) {
            return $fallback;
        }
    }
}
