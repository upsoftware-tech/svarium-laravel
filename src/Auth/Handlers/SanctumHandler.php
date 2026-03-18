<?php

namespace Upsoftware\Svarium\Auth\Handlers;

use RuntimeException;
use Upsoftware\Svarium\Contracts\ApiAuthHandler;

class SanctumHandler implements ApiAuthHandler {
    protected array $defaultScopes;

    public function __construct(array $defaultScopes = [])
    {
        $this->defaultScopes = $defaultScopes;
    }

    public function createToken($user, string $deviceName, array $scopes = []): string {
        $finalScopes = empty($scopes) ? $this->defaultScopes : $scopes;

        if (! is_object($user) || ! method_exists($user, 'createToken')) {
            $modelClass = is_object($user) ? $user::class : get_debug_type($user);

            throw new RuntimeException(
                sprintf(
                    'API driver "sanctum" requires createToken() on user model [%s]. '.
                    'Install laravel/sanctum and add trait Laravel\\Sanctum\\HasApiTokens to the configured user model.',
                    $modelClass
                )
            );
        }

        return $user->createToken($deviceName, $finalScopes)->plainTextToken;
    }
}
