<?php

namespace Upsoftware\Svarium\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LocaleMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $requestedLocale = trim((string) $request->header(
            'X-Svarium-Locale',
            $request->query('_locale', (string) $request->input('_locale', ''))
        ));

        $hasSession = $request->hasSession();

        if ($requestedLocale !== '') {
            if ($hasSession) {
                $request->session()->put('locale', $requestedLocale);
            }

            app()->setLocale($requestedLocale);
        } else {
            if ($hasSession && $request->session()->has('locale')) {
                app()->setLocale((string) $request->session()->get('locale'));
            } else {
                app()->setLocale((string) config('app.locale', app()->getLocale()));
            }
        }

        return $next($request);
    }
}
