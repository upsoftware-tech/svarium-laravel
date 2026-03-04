<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LocaleController extends Controller
{
    public function __invoke(string $locale, Request $request): Response
    {
        session()->put('locale', $locale);
        session()->forget('errors');
        app()->setLocale($locale);

        $fallbackUrl = url('/');
        $redirectTarget = $this->resolveRedirectTarget($request, $fallbackUrl);

        if ($request->header('X-Svarium-Locale-Ajax') === '1') {
            return response()->noContent();
        }

        return redirect()->to($redirectTarget);
    }

    protected function resolveRedirectTarget(Request $request, string $fallbackUrl): string
    {
        $fromQuery = $this->sanitizeRedirectTarget((string) $request->query('redirect', ''), $request);
        if ($fromQuery !== null) {
            return $fromQuery;
        }

        $fromPrevious = $this->sanitizeRedirectTarget(url()->previous($fallbackUrl), $request);
        if ($fromPrevious !== null) {
            return $fromPrevious;
        }

        return $fallbackUrl;
    }

    protected function sanitizeRedirectTarget(string $target, Request $request): ?string
    {
        $target = trim($target);
        if ($target === '') {
            return null;
        }

        if (str_starts_with($target, '/')) {
            return $this->buildInternalRedirect($target);
        }

        $host = parse_url($target, PHP_URL_HOST);
        if (! is_string($host) || strcasecmp($host, $request->getHost()) !== 0) {
            return null;
        }

        return $this->buildInternalRedirect($target);
    }

    protected function buildInternalRedirect(string $target): ?string
    {
        $path = (string) (parse_url($target, PHP_URL_PATH) ?? '/');
        $query = (string) (parse_url($target, PHP_URL_QUERY) ?? '');

        $normalizedPath = '/'.ltrim($path, '/');
        $trimmedPath = ltrim($normalizedPath, '/');

        if (
            $trimmedPath === ''
            || str_starts_with($trimmedPath, 'locale/')
            || str_starts_with($trimmedPath, '.well-known/')
            || str_starts_with($trimmedPath, '_debugbar/')
        ) {
            return null;
        }

        return $query !== ''
            ? $normalizedPath.'?'.$query
            : $normalizedPath;
    }
}
