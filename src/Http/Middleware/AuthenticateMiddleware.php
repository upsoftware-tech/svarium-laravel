<?php

namespace Upsoftware\Svarium\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): mixed  $next
     */
    public function handle(Request $request, Closure $next)
    {
        if ($this->isPublicAuthRequest($request)) {
            return $next($request);
        }

        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(svarium_login_url());
        }

        return $next($request);
    }

    protected function isPublicAuthRequest(Request $request): bool
    {
        $defaultRoutePatterns = [
            'panel.auth.login',
            'panel.auth.login.*',
            'panel.auth.reset',
            'panel.auth.reset.*',
            'panel.auth.register',
            'panel.auth.register.*',
            'panel.auth.method',
            'panel.auth.method.*',
            'panel.auth.verification',
            'panel.auth.verification.*',
            'panel.auth.redirect',
            'panel.auth.callback',
        ];

        $routePatterns = config('upsoftware.panel.public_auth_route_patterns', $defaultRoutePatterns);
        if (! is_array($routePatterns)) {
            $routePatterns = $defaultRoutePatterns;
        }

        foreach ($routePatterns as $pattern) {
            if (is_string($pattern) && $pattern !== '' && $request->routeIs($pattern)) {
                return true;
            }
        }

        $panelPrefix = trim((string) config('upsoftware.panel.prefix', ''), '/');
        $base = $panelPrefix !== '' ? $panelPrefix.'/' : '';

        $defaultPathPatterns = [
            $base.'auth/login',
            $base.'auth/login/*',
            $base.'auth/reset',
            $base.'auth/reset/*',
            $base.'auth/register',
            $base.'auth/register/*',
        ];

        $pathPatterns = config('upsoftware.panel.public_auth_path_patterns', $defaultPathPatterns);
        if (! is_array($pathPatterns)) {
            $pathPatterns = $defaultPathPatterns;
        }

        foreach ($pathPatterns as $pattern) {
            if (is_string($pattern) && $pattern !== '' && $request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
