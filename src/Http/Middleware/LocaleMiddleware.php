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

        if ($requestedLocale !== '') {
            session()->put('locale', $requestedLocale);
            app()->setLocale($requestedLocale);
        } else {
            app()->setLocale(session()->has('locale') ? session()->get('locale') : app()->getLocale());
        }

        return $next($request);
    }
}
