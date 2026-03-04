<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $table = 'model_has_domain_tenants';

    public function up(): void
    {
        if (! Schema::hasTable($this->table)) {
            Schema::create($this->table, function (Blueprint $table): void {
                $this->addTenantIdColumn($table);
                $table->unsignedBigInteger('tenant_domain_id');
                $table->string('model_type');
                $table->string('model_id');
                $table->timestamps();

                $table->index(['model_type', 'model_id'], 'model_has_domain_tenants_model_index');
                $table->index('tenant_id', 'model_has_domain_tenants_tenant_index');
                $table->index('tenant_domain_id', 'model_has_domain_tenants_domain_index');
                $table->unique(
                    ['tenant_id', 'tenant_domain_id', 'model_type', 'model_id'],
                    'model_has_domain_tenants_unique'
                );
            });

            $this->ensureForeignKeys();

            return;
        }

        Schema::table($this->table, function (Blueprint $table): void {
            if (! Schema::hasColumn($this->table, 'tenant_id')) {
                $this->addTenantIdColumn($table);
            }

            if (! Schema::hasColumn($this->table, 'tenant_domain_id')) {
                $table->unsignedBigInteger('tenant_domain_id');
            }

            if (! Schema::hasColumn($this->table, 'model_type')) {
                $table->string('model_type');
            }

            if (! Schema::hasColumn($this->table, 'model_id')) {
                $table->string('model_id');
            }

            if (! Schema::hasColumn($this->table, 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }

            if (! Schema::hasColumn($this->table, 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        $this->ensureIndexes();
        $this->alignTenantIdColumnType();
        $this->ensureForeignKeys();
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table);
    }

    protected function ensureIndexes(): void
    {
        Schema::table($this->table, function (Blueprint $table): void {
            if (! Schema::hasIndex($this->table, 'model_has_domain_tenants_model_index')) {
                $table->index(['model_type', 'model_id'], 'model_has_domain_tenants_model_index');
            }

            if (! Schema::hasIndex($this->table, 'model_has_domain_tenants_tenant_index')) {
                $table->index('tenant_id', 'model_has_domain_tenants_tenant_index');
            }

            if (! Schema::hasIndex($this->table, 'model_has_domain_tenants_domain_index')) {
                $table->index('tenant_domain_id', 'model_has_domain_tenants_domain_index');
            }

            if (! Schema::hasIndex($this->table, 'model_has_domain_tenants_unique', 'unique')) {
                $table->unique(
                    ['tenant_id', 'tenant_domain_id', 'model_type', 'model_id'],
                    'model_has_domain_tenants_unique'
                );
            }
        });
    }

    protected function ensureForeignKeys(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasTable('tenant_domains')) {
            return;
        }

        if ($this->tenantColumnsAreCompatible() && ! Schema::hasIndex($this->table, ['tenant_id'], 'foreign')) {
            try {
                Schema::table($this->table, function (Blueprint $table): void {
                    $table->foreign('tenant_id', 'model_has_domain_tenants_tenant_foreign')
                        ->references('id')
                        ->on('tenants')
                        ->cascadeOnDelete();
                });
            } catch (Throwable) {
                // Ignore incompatible existing schema.
            }
        }

        if (! Schema::hasIndex($this->table, ['tenant_domain_id'], 'foreign')) {
            try {
                Schema::table($this->table, function (Blueprint $table): void {
                    $table->foreign('tenant_domain_id', 'model_has_domain_tenants_domain_foreign')
                        ->references('id')
                        ->on('tenant_domains')
                        ->cascadeOnDelete();
                });
            } catch (Throwable) {
                // Ignore incompatible existing schema.
            }
        }
    }

    protected function addTenantIdColumn(Blueprint $table): void
    {
        if ($this->tenantIdUsesNumericType()) {
            $table->unsignedBigInteger('tenant_id');
            return;
        }

        $table->string('tenant_id');
    }

    protected function alignTenantIdColumnType(): void
    {
        if (! Schema::hasTable($this->table) || ! Schema::hasColumn($this->table, 'tenant_id')) {
            return;
        }

        if ($this->tenantColumnsAreCompatible()) {
            return;
        }

        try {
            Schema::table($this->table, function (Blueprint $table): void {
                if ($this->tenantIdUsesNumericType()) {
                    $table->unsignedBigInteger('tenant_id')->change();
                } else {
                    $table->string('tenant_id')->change();
                }
            });
        } catch (Throwable) {
            // Ignore conversion errors, FK creation will be skipped if still incompatible.
        }
    }

    protected function tenantColumnsAreCompatible(): bool
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasTable($this->table)) {
            return false;
        }

        if (! Schema::hasColumn('tenants', 'id') || ! Schema::hasColumn($this->table, 'tenant_id')) {
            return false;
        }

        try {
            $tenantType = strtolower((string) Schema::getColumnType('tenants', 'id'));
            $pivotType = strtolower((string) Schema::getColumnType($this->table, 'tenant_id'));
        } catch (Throwable) {
            return false;
        }

        return $this->isNumericType($tenantType) === $this->isNumericType($pivotType);
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
