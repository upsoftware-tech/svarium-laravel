<?php

namespace Upsoftware\Svarium\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Upsoftware\Svarium\Models\Domain;
use Upsoftware\Svarium\Tenancy\TenancyManager;

class ResolveDomainContext
{
    public function __construct(protected TenancyManager $tenancy)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (! (bool) config('upsoftware.tenancy.domains.enabled', true)) {
            return $next($request);
        }

        $host = $this->normalizeHost((string) $request->getHost());
        if ($host === '') {
            return $next($request);
        }

        $domain = $this->resolveDomain($host);
        if (! $domain instanceof Model) {
            return $next($request);
        }

        if ($domain->getAttribute('status') !== null && ! (bool) $domain->getAttribute('status')) {
            abort(404);
        }

        $primaryDomain = $this->resolvePrimaryDomain($domain);

        $redirectToPrimary = (bool) ($domain->getAttribute('redirect_to_primary') ?? false);
        if ($redirectToPrimary && $primaryDomain instanceof Model) {
            $redirect = $this->buildRedirectToPrimary($request, $domain, $primaryDomain);
            if ($redirect instanceof RedirectResponse) {
                return $redirect;
            }
        }

        if (! $request->isSecure() && (bool) ($domain->getAttribute('force_https') ?? false)) {
            return redirect()->to($this->buildUrl(
                host: (string) $domain->getAttribute('domain'),
                request: $request,
                forceHttps: true
            ), 301);
        }

        $sessionLocale = trim((string) session('locale', ''));
        $domainLocale = trim((string) ($domain->getAttribute('locale') ?? ''));

        if ($sessionLocale !== '') {
            app()->setLocale($sessionLocale);
        } elseif ($domainLocale !== '') {
            app()->setLocale($domainLocale);
            session()->put('locale', $domainLocale);
        }

        $theme = trim((string) ($domain->getAttribute('theme') ?? ''));
        if ($theme !== '') {
            $request->attributes->set('svarium.theme', $theme);
        }

        $request->attributes->set('svarium.domain', $domain);
        $request->attributes->set('svarium.domain.primary', $primaryDomain);
        $request->attributes->set('svarium.seo', $this->buildSeo($request, $domain, $primaryDomain));

        return $next($request);
    }

    protected function resolveDomain(string $host): ?Model
    {
        $resolved = $this->tenancy->domain();
        if ($resolved instanceof Model) {
            return $resolved;
        }

        $domainModel = $this->resolveDomainModelClass();
        if ($domainModel === null) {
            return null;
        }

        try {
            return $domainModel::query()
                ->where('domain', $host)
                ->orWhere('domain', 'www.'.$host)
                ->first();
        } catch (Throwable) {
            return null;
        }
    }

    protected function resolvePrimaryDomain(Model $domain): ?Model
    {
        if ((bool) ($domain->getAttribute('is_primary') ?? false)) {
            return $domain;
        }

        $domainModel = $this->resolveDomainModelClass();
        if ($domainModel === null) {
            return null;
        }

        try {
            $query = $domainModel::query()->where('is_primary', true);

            $modelInstance = new $domainModel;
            if ($this->tableHasColumn($modelInstance->getTable(), 'status')) {
                $query->where('status', true);
            }

            $tenantId = $domain->getAttribute('tenant_id');
            if ($tenantId !== null && $tenantId !== '') {
                $query->where('tenant_id', $tenantId);
            }

            return $query->first();
        } catch (Throwable) {
            return null;
        }
    }

    protected function buildRedirectToPrimary(Request $request, Model $domain, Model $primaryDomain): ?RedirectResponse
    {
        $currentHost = $this->normalizeHost((string) ($domain->getAttribute('domain') ?? ''));
        $targetHost = $this->normalizeHost((string) ($primaryDomain->getAttribute('domain') ?? ''));

        if ($targetHost === '' || $targetHost === $currentHost) {
            return null;
        }

        $forceHttps = (bool) ($primaryDomain->getAttribute('force_https') ?? false);
        $targetUrl = $this->buildUrl($targetHost, $request, $forceHttps);

        return redirect()->to($targetUrl, 301);
    }

    protected function buildSeo(Request $request, Model $domain, ?Model $primaryDomain): array
    {
        $canonicalEnabled = (bool) config('upsoftware.tenancy.domains.seo.canonical_on_primary', true);
        $noindexAliases = (bool) config('upsoftware.tenancy.domains.seo.noindex_aliases', true);

        $currentHost = $this->normalizeHost((string) ($domain->getAttribute('domain') ?? $request->getHost()));
        $primaryHost = $this->normalizeHost((string) ($primaryDomain?->getAttribute('domain') ?? $currentHost));
        $isAlias = $primaryHost !== '' && $primaryHost !== $currentHost;

        $canonicalHost = $canonicalEnabled ? $primaryHost : $currentHost;
        if ($canonicalHost === '') {
            $canonicalHost = $request->getHost();
        }

        $forceHttps = (bool) ($primaryDomain?->getAttribute('force_https') ?? $domain->getAttribute('force_https') ?? false);

        return [
            'canonical' => $this->buildUrl($canonicalHost, $request, $forceHttps),
            'robots' => ($noindexAliases && $isAlias) ? 'noindex,follow' : 'index,follow',
            'is_alias' => $isAlias,
            'primary_domain' => $primaryHost,
            'current_domain' => $currentHost,
        ];
    }

    protected function buildUrl(string $host, Request $request, bool $forceHttps = false): string
    {
        $scheme = $forceHttps ? 'https' : ($request->isSecure() ? 'https' : 'http');
        $path = '/'.ltrim((string) $request->getPathInfo(), '/');
        $query = $request->getQueryString();

        return $scheme.'://'.$host.$path.($query ? '?'.$query : '');
    }

    protected function resolveDomainModelClass(): ?string
    {
        $domainModel = config('upsoftware.models.domain');

        if (! is_string($domainModel) || ! class_exists($domainModel)) {
            $domainModel = config('upsoftware.models.tenant_domain');
        }

        if (! is_string($domainModel) || ! class_exists($domainModel)) {
            $domainModel = Domain::class;
        }

        return is_string($domainModel) && class_exists($domainModel)
            ? $domainModel
            : null;
    }

    protected function normalizeHost(string $host): string
    {
        return strtolower(trim($host));
    }

    protected function tableHasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }
}
