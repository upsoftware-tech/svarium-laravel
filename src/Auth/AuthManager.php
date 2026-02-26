<?php

namespace Upsoftware\Svarium\Auth;

use Upsoftware\Svarium\Auth\Handlers\JwtHandler;
use Upsoftware\Svarium\Auth\Handlers\PassportHandler;
use Upsoftware\Svarium\Auth\Handlers\SanctumHandler;

class AuthManager
{
    public function resolveHandler()
    {
        $config = config('svarium.api.auth');
        $driver = $config['driver'] ?? 'sanctum';

        return match ($driver) {
            'sanctum' => new SanctumHandler(
                $config['default_scopes'] ?? []
            ),
            'jwt' => new JwtHandler(
                $config['default_scopes'] ?? []
            ),
            'passport' => new PassportHandler(
                $config['default_scopes'] ?? []
            ),
            'custom' => app($config['custom_handler']),
            default => app($driver),
        };
    }
}
