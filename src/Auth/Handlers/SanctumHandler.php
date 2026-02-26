<?php

namespace Upsoftware\Svarium\Auth\Handlers;

use Upsoftware\Svarium\Contracts\ApiAuthHandler;

class SanctumHandler implements ApiAuthHandler {
    protected array $defaultScopes;

    public function __construct(array $defaultScopes = [])
    {
        $this->defaultScopes = $defaultScopes;
    }

    public function createToken($user, string $deviceName, array $scopes = []): string {
        $finalScopes = empty($scopes) ? $this->defaultScopes : $scopes;

        return $user->createToken($deviceName, $finalScopes)->plainTextToken;
    }
}
