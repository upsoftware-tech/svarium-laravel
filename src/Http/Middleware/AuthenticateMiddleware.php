<?php

namespace Upsoftware\Svarium\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
// Pobieramy bazę nazwy trasy z configu (np. "panel.auth")
        $authRoutePrefix = config('upsoftware.panel.route_prefix', 'panel.auth');

        // Sprawdzamy, czy obecna trasa zaczyna się od tego prefiksu (używamy gwiazdki *)
        if ($request->routeIs($authRoutePrefix . '.*')) {
            return $next($request);
        }

        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            // Przekierowanie na konkretną stronę logowania (np. panel.auth.login)
            return redirect()->guest(route($authRoutePrefix . '.login'));
        }

        return $next($request);
    }
}
