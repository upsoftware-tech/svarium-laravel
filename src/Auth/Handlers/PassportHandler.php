<?php

namespace Upsoftware\Svarium\Auth\Handlers;

use Upsoftware\Svarium\Contracts\ApiAuthHandler;

class PassportHandler implements ApiAuthHandler
{
    protected array $defaultScopes;

    public function __construct(array $defaultScopes = [])
    {
        $this->defaultScopes = $defaultScopes;
    }

    public function createToken($user, string $deviceName, array $scopes = []): string {
    {
        return $user->createToken($deviceName)->accessToken;
    }

    public function revokeToken($user): bool
    {
        return $user->token()->revoke();
    }
}
