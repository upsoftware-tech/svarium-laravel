<?php

namespace Upsoftware\Svarium\Traits;

trait UsesConnection
{
    /**
     * Get the current connection name for the model.
     *
     * @return string|null
     */
    public function getConnectionName(): ?string
    {
        $forcedConnection = config('svarium.database_connection');
        if (is_string($forcedConnection) && $forcedConnection !== '') {
            return $forcedConnection;
        }

        $defaultConnection = (string) config('database.default');
        $connections = (array) config('database.connections', []);
        $isConfigured = static fn (?string $name): bool => is_string($name) && $name !== '' && array_key_exists($name, $connections);

        if (! (function_exists('svarium_tenancy_database_mode') && svarium_tenancy_database_mode())) {
            return $defaultConnection;
        }

        $candidates = [
            config('upsoftware.tenancy.database.central_connection'),
            config('tenancy.database.central_connection'),
            'central',
        ];

        foreach ($candidates as $candidate) {
            if ($isConfigured($candidate)) {
                return $candidate;
            }
        }

        return $defaultConnection;
    }
}
