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
        if ($forcedConnection = config('svarium.database_connection')) {
            return $forcedConnection;
        }

        if (config()->has('tenancy.database.central_connection')) {
            return config('tenancy.database.central_connection');
        }

        if (config()->has('database.connections.central')) {
            return 'central';
        }

        return config('database.default');
    }
}
