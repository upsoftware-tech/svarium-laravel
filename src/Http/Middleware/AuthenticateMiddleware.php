<?php

namespace Upsoftware\Svarium\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Upsoftware\Svarium\Panel\Panel;

class AuthenticateMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): mixed  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $panelName = $this->resolvePanelNameFromRequest($request);

        if ($this->isPublicAuthRequest($request, $panelName)) {
            return $next($request);
        }

        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(svarium_login_url(false, $panelName));
        }

        return $next($request);
    }

    protected function isPublicAuthRequest(Request $request, ?string $panel = null): bool
    {
        return svarium_is_public_auth_request($request, $panel);
    }

    protected function resolvePanelNameFromRequest(Request $request): ?string
    {
        $panel = svarium_resolve_panel(null, $request);

        return $panel instanceof Panel
            ? trim((string) $panel->name)
            : null;
    }
}
