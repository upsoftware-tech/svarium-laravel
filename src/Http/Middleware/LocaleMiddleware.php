<?php

namespace Upsoftware\Svarium\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LocaleMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        app()->setLocale(session()->has('locale') ? session()->get('locale') : app()->getLocale(),);
        return $next($request);
    }
}
