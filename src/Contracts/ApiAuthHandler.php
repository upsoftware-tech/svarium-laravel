<?php

namespace Upsoftware\Svarium\Contracts;

interface ApiAuthHandler {
    /**
     * Creates a token and returns it as a string.
     */
    public function createToken($user, string $deviceName, array $scopes = []): string;
}
