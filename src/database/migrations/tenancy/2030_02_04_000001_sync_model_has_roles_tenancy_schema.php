<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->tenancyEnabled()) {
            return;
        }

        $connection = $this->resolveConnection();
        $table = $this->modelHasRolesTable();

        if (! Schema::connection($connection)->hasTable($table)
            || ! Schema::connection($connection)->hasTable('tenants')) {
            return;
        }

        $useNumericTenantId = $this->tenantIdUsesNumericType($connection);

        if (! Schema::connection($connection)->hasColumn($table, 'tenant_id')) {
            Schema::connection($connection)->table($table, function (Blueprint $blueprint) use ($useNumericTenantId): void {
                if ($useNumericTenantId) {
                    $blueprint->unsignedBigInteger('tenant_id')->nullable();
                    return;
                }

                $blueprint->string('tenant_id')->nullable();
            });
        }

        if (! $this->tenantColumnsAreCompatible($connection, $table)
            && $this->isTableEmpty($connection, $table)) {
            $this->dropForeignIfExists($connection, $table, 'model_has_roles_tenant_foreign');
            $this->dropForeignIfExists($connection, $table, 'model_has_roles_tenant_id_foreign');
            $this->dropIndexIfExists($connection, $table, 'model_has_roles_role_model_tenant_unique', true);
            $this->dropIndexIfExists($connection, $table, 'model_has_roles_tenant_lookup_index');

            Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('tenant_id');
            });

            Schema::connection($connection)->table($table, function (Blueprint $blueprint) use ($useNumericTenantId): void {
                if ($useNumericTenantId) {
                    $blueprint->unsignedBigInteger('tenant_id')->nullable();
                    return;
                }

                $blueprint->string('tenant_id')->nullable();
            });
        }

        if (! $this->indexExists($connection, $table, 'model_has_roles_tenant_lookup_index')) {
            Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
                $blueprint->index(['tenant_id', 'model_type', 'model_id'], 'model_has_roles_tenant_lookup_index');
            });
        }

        if (! $this->indexExists($connection, $table, 'model_has_roles_role_model_tenant_unique')) {
            Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
                $blueprint->unique(
                    ['role_id', 'model_id', 'model_type', 'tenant_id'],
                    'model_has_roles_role_model_tenant_unique'
                );
            });
        }

        if ($this->indexExists($connection, $table, 'PRIMARY')) {
            try {
                Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropPrimary();
                });
            } catch (Throwable) {
                // Ignore when PK cannot be removed.
            }
        }

        if ($this->tenantColumnsAreCompatible($connection, $table)
            && ! $this->foreignKeyExists($connection, $table, 'model_has_roles_tenant_foreign')) {
            Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
                $blueprint->foreign('tenant_id', 'model_has_roles_tenant_foreign')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $connection = $this->resolveConnection();
        $table = $this->modelHasRolesTable();

        if (! Schema::connection($connection)->hasTable($table)) {
            return;
        }

        $this->dropForeignIfExists($connection, $table, 'model_has_roles_tenant_foreign');
        $this->dropForeignIfExists($connection, $table, 'model_has_roles_tenant_id_foreign');
        $this->dropIndexIfExists($connection, $table, 'model_has_roles_role_model_tenant_unique', true);
        $this->dropIndexIfExists($connection, $table, 'model_has_roles_tenant_lookup_index');

        if (! $this->indexExists($connection, $table, 'PRIMARY')) {
            try {
                Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
                    $blueprint->primary(['role_id', 'model_id', 'model_type']);
                });
            } catch (Throwable) {
                // Ignore.
            }
        }

        if (Schema::connection($connection)->hasColumn($table, 'tenant_id')) {
            Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('tenant_id');
            });
        }
    }

    protected function resolveConnection(): string
    {
        $candidate = function_exists('central_connection')
            ? (string) central_connection()
            : (string) config('database.default', 'mysql');

        $connections = (array) config('database.connections', []);

        if ($candidate !== '' && array_key_exists($candidate, $connections)) {
            return $candidate;
        }

        return (string) config('database.default', 'mysql');
    }

    protected function modelHasRolesTable(): string
    {
        $table = trim((string) config('permission.table_names.model_has_roles', 'model_has_roles'));
        return $table !== '' ? $table : 'model_has_roles';
    }

    protected function tenancyEnabled(): bool
    {
        return (bool) config('upsoftware.tenancy.enabled', config('tenancy.enabled', false));
    }

    protected function tenantIdUsesNumericType(string $connection): bool
    {
        try {
            $type = strtolower((string) Schema::connection($connection)->getColumnType('tenants', 'id'));
        } catch (Throwable) {
            return true;
        }

        return $this->isNumericType($type);
    }

    protected function tenantColumnsAreCompatible(string $connection, string $table): bool
    {
        if (! Schema::connection($connection)->hasColumn('tenants', 'id')
            || ! Schema::connection($connection)->hasColumn($table, 'tenant_id')) {
            return false;
        }

        try {
            $tenantType = strtolower((string) Schema::connection($connection)->getColumnType('tenants', 'id'));
            $pivotType = strtolower((string) Schema::connection($connection)->getColumnType($table, 'tenant_id'));
        } catch (Throwable) {
            return false;
        }

        return $this->isNumericType($tenantType) === $this->isNumericType($pivotType);
    }

    protected function isNumericType(string $type): bool
    {
        return in_array($type, [
            'bigint',
            'biginteger',
            'unsignedbigint',
            'int',
            'integer',
            'mediumint',
            'smallint',
            'tinyint',
            'unsignedinteger',
        ], true);
    }

    protected function isTableEmpty(string $connection, string $table): bool
    {
        try {
            return DB::connection($connection)->table($table)->count() === 0;
        } catch (Throwable) {
            return false;
        }
    }

    protected function indexExists(string $connection, string $table, string $indexName): bool
    {
        try {
            $databaseName = (string) DB::connection($connection)->getDatabaseName();
            if ($databaseName === '') {
                return false;
            }

            return DB::connection($connection)
                ->table('information_schema.statistics')
                ->where('table_schema', $databaseName)
                ->where('table_name', $table)
                ->where('index_name', $indexName)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    protected function foreignKeyExists(string $connection, string $table, string $constraintName): bool
    {
        try {
            $databaseName = (string) DB::connection($connection)->getDatabaseName();
            if ($databaseName === '') {
                return false;
            }

            return DB::connection($connection)
                ->table('information_schema.table_constraints')
                ->where('table_schema', $databaseName)
                ->where('table_name', $table)
                ->where('constraint_name', $constraintName)
                ->where('constraint_type', 'FOREIGN KEY')
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    protected function dropForeignIfExists(string $connection, string $table, string $constraintName): void
    {
        if (! $this->foreignKeyExists($connection, $table, $constraintName)) {
            return;
        }

        try {
            DB::connection($connection)->statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraintName}`");
        } catch (Throwable) {
            // Ignore.
        }
    }

    protected function dropIndexIfExists(string $connection, string $table, string $indexName, bool $unique = false): void
    {
        if (! $this->indexExists($connection, $table, $indexName)) {
            return;
        }

        try {
            Schema::connection($connection)->table($table, function (Blueprint $blueprint) use ($indexName, $unique): void {
                if ($unique) {
                    $blueprint->dropUnique($indexName);
                    return;
                }

                $blueprint->dropIndex($indexName);
            });
        } catch (Throwable) {
            // Ignore.
        }
    }
};
