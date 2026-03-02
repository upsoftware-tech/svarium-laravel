<?php

namespace Upsoftware\Svarium\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Upsoftware\Svarium\Tenancy\TenancyManager;

class InitializeTenancy
{
    public function __construct(protected TenancyManager $tenancy)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $this->tenancy->initialize($request);

        try {
            return $next($request);
        } finally {
            $this->tenancy->terminate();
        }
    }
}
