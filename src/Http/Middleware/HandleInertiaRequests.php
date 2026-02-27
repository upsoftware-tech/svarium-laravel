<?php

namespace Upsoftware\Svarium\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        return $this->safe(
            fn () => Setting::getSettingGlobal('layout'),
            (object) []
        );
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
