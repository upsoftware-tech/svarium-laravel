<?php

namespace Upsoftware\Svarium\Http\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;
use Inertia\Inertia;
use Upsoftware\Svarium\Models\Navigation;
use Upsoftware\Svarium\Models\Setting;
use Upsoftware\Svarium\Services\LayoutService;
use Upsoftware\Svarium\Services\NavigationService;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        $setting = [];

        //echo '<pre>';print_r($this->layout()); die;

        return array_merge(parent::share($request), [
            'locale' => session()->has('locale') ? session()->get('locale') : app()->getLocale(),
            'locales' => Inertia::once(fn () => locales()),
            'workspaces' => Auth::check() && method_exists(Auth::user(), 'getWorkspaces') ? $request->user()->getWorkspaces() : false,
            'title' => fn () => get_title(),
            'layout' => [
                'panel' => $this->layout(),
            ],
            'alert' => [
                'success' => fn () => $request->session()->get('alert_success'),
                'error' => fn () => $request->session()->get('alert_error'),
                'warning' => fn () => $request->session()->get('alert_warning'),
                'info' => fn () => $request->session()->get('alert_info'),
                'message' => fn () => $request->session()->get('alert_message'),
            ],
            'setting' => Setting::getSettingGlobal('layout'),
            'navigation' => Auth::check() ?
                Navigation::whereNull('parent_id')->get()->mapWithKeys(function($navigation) {
                    return [
                        $navigation->id => NavigationService::make()->getTree($navigation->id),
                    ];
                })
            : [],
            'ziggy' => function () {
                return array_merge((new Ziggy)->toArray(), [
                    'location' => request()->url(),
                ]);
            },
        ]);
    }

    public function layout() {
        return layout()->getComponents();
    }
}
