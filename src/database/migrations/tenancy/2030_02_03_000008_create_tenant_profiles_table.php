<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = $this->profileTable();
        $foreignKey = $this->profileForeignKey();

        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $blueprint) use ($foreignKey): void {
                $blueprint->id();
                $this->addTenantKeyColumn($blueprint, $foreignKey);
                $blueprint->json('payload')->nullable();
                $blueprint->timestamps();
                $blueprint->unique($foreignKey, "{$table}_{$foreignKey}_unique");
            });
        } else {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $foreignKey): void {
                if (! Schema::hasColumn($table, $foreignKey)) {
                    $this->addTenantKeyColumn($blueprint, $foreignKey);
                }

                if (! Schema::hasColumn($table, 'payload')) {
                    $blueprint->json('payload')->nullable();
                }

                if (! Schema::hasColumn($table, 'created_at')) {
                    $blueprint->timestamp('created_at')->nullable();
                }

                if (! Schema::hasColumn($table, 'updated_at')) {
                    $blueprint->timestamp('updated_at')->nullable();
                }
            });
        }

        if (! Schema::hasIndex($table, "{$table}_{$foreignKey}_unique", 'unique')) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $foreignKey): void {
                $blueprint->unique($foreignKey, "{$table}_{$foreignKey}_unique");
            });
        }

        $this->ensureForeignKey($table, $foreignKey);
    }

    public function down(): void
    {
        Schema::dropIfExists($this->profileTable());
    }

    protected function profileTable(): string
    {
        $table = trim((string) config('upsoftware.tenancy.profile.table', 'tenant_profiles'));
        return $table !== '' ? $table : 'tenant_profiles';
    }

    protected function profileForeignKey(): string
    {
        $key = trim((string) config('upsoftware.tenancy.profile.foreign_key', 'tenant_id'));
        return $key !== '' ? $key : 'tenant_id';
    }

    protected function addTenantKeyColumn(Blueprint $table, string $column): void
    {
        if ($this->tenantIdUsesNumericType()) {
            $table->unsignedBigInteger($column);
            return;
        }

        $table->string($column);
    }

    protected function ensureForeignKey(string $table, string $column): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasColumn($table, $column)) {
            return;
        }

        if (! $this->tenantColumnsCompatible($table, $column)) {
            return;
        }

        if (Schema::hasIndex($table, [$column], 'foreign')) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $table): void {
                $blueprint->foreign($column, "{$table}_{$column}_foreign")
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();
            });
        } catch (Throwable) {
            // Ignore invalid existing schema.
        }
    }

    protected function tenantIdUsesNumericType(): bool
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasColumn('tenants', 'id')) {
            return true;
        }

        try {
            $type = strtolower((string) Schema::getColumnType('tenants', 'id'));
        } catch (Throwable) {
            return true;
        }

        return $this->isNumericType($type);
    }

    protected function tenantColumnsCompatible(string $table, string $column): bool
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasTable($table)) {
            return false;
        }

        if (! Schema::hasColumn('tenants', 'id') || ! Schema::hasColumn($table, $column)) {
            return false;
        }

        try {
            $tenantType = strtolower((string) Schema::getColumnType('tenants', 'id'));
            $profileType = strtolower((string) Schema::getColumnType($table, $column));
        } catch (Throwable) {
            return false;
        }

        return $this->isNumericType($tenantType) === $this->isNumericType($profileType);
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
};
