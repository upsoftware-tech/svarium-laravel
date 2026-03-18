<?php

namespace Upsoftware\Svarium\Auth\Handlers;

use Upsoftware\Svarium\Contracts\ApiAuthHandler;

class JwtHandler implements ApiAuthHandler {
    protected array $defaultScopes;

    public function __construct(array $defaultScopes = [])
    {
        $this->defaultScopes = $defaultScopes;
    }

    public function createToken($user, string $deviceName, array $scopes = []): string {
        $guard = trim((string) config('upsoftware.api.auth.guard', 'api'));
        if ($guard === '') {
            $guard = 'api';
        }

        return auth($guard)->login($user);
    }
}
