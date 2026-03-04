<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $tenantDomainsTable = 'tenant_domains';
    protected string $plainDomainsTable = 'domains';
    protected string $oldModelMapTable = 'model_has_domain_tenants';
    protected string $newModelMapTable = 'model_has_domains';

    public function up(): void
    {
        $this->migrateDomainsTable();

        if ($this->tenancyEnabled()) {
            $this->migrateModelMapTable();
        }
    }

    public function down(): void
    {
        $target = $this->targetDomainsTable();
        $alternate = $this->alternateDomainsTable();

        if (Schema::hasTable($target) && ! Schema::hasTable($alternate)) {
            Schema::rename($target, $alternate);
        }

        if (Schema::hasTable($this->newModelMapTable) && ! Schema::hasTable($this->oldModelMapTable)) {
            Schema::rename($this->newModelMapTable, $this->oldModelMapTable);
        }
    }

    protected function migrateDomainsTable(): void
    {
        $target = $this->targetDomainsTable();
        $alternate = $this->alternateDomainsTable();

        if (! Schema::hasTable($target) && Schema::hasTable($alternate)) {
            Schema::rename($alternate, $target);
        }

        if (! Schema::hasTable($target)) {
            Schema::create($target, function (Blueprint $table): void {
                $table->id();
                $this->addTenantIdColumn($table);
                $table->string('domain')->unique();
                $table->boolean('is_primary')->default(false);
                $table->string('locale', 12)->nullable();
                $table->string('theme', 100)->nullable();
                $table->boolean('status')->default(true);
                $table->boolean('redirect_to_primary')->default(false);
                $table->boolean('force_https')->default(false);
                $table->timestamps();
            });
        }

        Schema::table($target, function (Blueprint $table) use ($target): void {
            if (! Schema::hasColumn($target, 'tenant_id')) {
                $this->addTenantIdColumn($table);
            }

            if (! Schema::hasColumn($target, 'domain')) {
                $table->string('domain')->unique();
            }

            if (! Schema::hasColumn($target, 'is_primary')) {
                $table->boolean('is_primary')->default(false);
            }

            if (! Schema::hasColumn($target, 'locale')) {
                $table->string('locale', 12)->nullable();
            }

            if (! Schema::hasColumn($target, 'theme')) {
                $table->string('theme', 100)->nullable();
            }

            if (! Schema::hasColumn($target, 'status')) {
                $table->boolean('status')->default(true);
            }

            if (! Schema::hasColumn($target, 'redirect_to_primary')) {
                $table->boolean('redirect_to_primary')->default(false);
            }

            if (! Schema::hasColumn($target, 'force_https')) {
                $table->boolean('force_https')->default(false);
            }

            if (! Schema::hasColumn($target, 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }

            if (! Schema::hasColumn($target, 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        $this->ensureDomainTenantForeignKey($target);
    }

    protected function migrateModelMapTable(): void
    {
        if (! Schema::hasTable($this->newModelMapTable) && Schema::hasTable($this->oldModelMapTable)) {
            Schema::rename($this->oldModelMapTable, $this->newModelMapTable);
        }

        if (! Schema::hasTable($this->newModelMapTable)) {
            Schema::create($this->newModelMapTable, function (Blueprint $table): void {
                $table->unsignedBigInteger('domain_id');
                $table->string('model_type');
                $table->string('model_id');
                $table->timestamps();
                $table->index(['model_type', 'model_id'], 'model_has_domains_model_index');
                $table->index('domain_id', 'model_has_domains_domain_index');
                $table->unique(['domain_id', 'model_type', 'model_id'], 'model_has_domains_unique');
            });
        }

        if (
            Schema::hasColumn($this->newModelMapTable, 'tenant_domain_id')
            && ! Schema::hasColumn($this->newModelMapTable, 'domain_id')
        ) {
            try {
                DB::statement("ALTER TABLE `{$this->newModelMapTable}` DROP FOREIGN KEY `model_has_domain_tenants_domain_foreign`");
            } catch (Throwable) {
                // Ignore.
            }

            try {
                DB::statement("ALTER TABLE `{$this->newModelMapTable}` DROP FOREIGN KEY `model_has_domains_domain_foreign`");
            } catch (Throwable) {
                // Ignore.
            }

            try {
                Schema::table($this->newModelMapTable, function (Blueprint $table): void {
                    $table->renameColumn('tenant_domain_id', 'domain_id');
                });
            } catch (Throwable) {
                // Ignore.
            }
        }

        Schema::table($this->newModelMapTable, function (Blueprint $table): void {
            if (! Schema::hasColumn($this->newModelMapTable, 'domain_id')) {
                $table->unsignedBigInteger('domain_id');
            }

            if (! Schema::hasColumn($this->newModelMapTable, 'model_type')) {
                $table->string('model_type');
            }

            if (! Schema::hasColumn($this->newModelMapTable, 'model_id')) {
                $table->string('model_id');
            }

            if (! Schema::hasColumn($this->newModelMapTable, 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }

            if (! Schema::hasColumn($this->newModelMapTable, 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        $this->ensureMapIndexes();
        $this->ensureMapForeignKey();
    }

    protected function ensureMapIndexes(): void
    {
        Schema::table($this->newModelMapTable, function (Blueprint $table): void {
            if (! Schema::hasIndex($this->newModelMapTable, 'model_has_domains_model_index')) {
                $table->index(['model_type', 'model_id'], 'model_has_domains_model_index');
            }

            if (! Schema::hasIndex($this->newModelMapTable, 'model_has_domains_domain_index')) {
                $table->index('domain_id', 'model_has_domains_domain_index');
            }

            if (! Schema::hasIndex($this->newModelMapTable, 'model_has_domains_unique', 'unique')) {
                $table->unique(['domain_id', 'model_type', 'model_id'], 'model_has_domains_unique');
            }
        });
    }

    protected function ensureMapForeignKey(): void
    {
        $domainsTable = $this->targetDomainsTable();

        if (! Schema::hasTable($this->newModelMapTable) || ! Schema::hasTable($domainsTable)) {
            return;
        }

        try {
            Schema::table($this->newModelMapTable, function (Blueprint $table) use ($domainsTable): void {
                $table->foreign('domain_id', 'model_has_domains_domain_foreign')
                    ->references('id')
                    ->on($domainsTable)
                    ->cascadeOnDelete();
            });
        } catch (Throwable) {
            // Ignore existing/invalid FK.
        }
    }

    protected function ensureDomainTenantForeignKey(string $table): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasColumn($table, 'tenant_id')) {
            return;
        }

        if (! $this->tenantColumnsCompatible($table)) {
            return;
        }

        if (Schema::hasIndex($table, ['tenant_id'], 'foreign')) {
            return;
        }

        $foreignName = $table === 'tenant_domains'
            ? 'tenant_domains_tenant_id_foreign'
            : 'domains_tenant_foreign';

        try {
            Schema::table($table, function (Blueprint $table) use ($foreignName): void {
                $table->foreign('tenant_id', $foreignName)
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();
            });
        } catch (Throwable) {
            // Ignore existing/invalid FK edge cases.
        }
    }

    protected function addTenantIdColumn(Blueprint $table): void
    {
        if ($this->tenantIdUsesNumericType()) {
            $table->unsignedBigInteger('tenant_id')->nullable();
            return;
        }

        $table->string('tenant_id')->nullable();
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

    protected function tenantColumnsCompatible(string $table): bool
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasTable($table)) {
            return false;
        }

        if (! Schema::hasColumn('tenants', 'id') || ! Schema::hasColumn($table, 'tenant_id')) {
            return false;
        }

        try {
            $tenantType = strtolower((string) Schema::getColumnType('tenants', 'id'));
            $domainType = strtolower((string) Schema::getColumnType($table, 'tenant_id'));
        } catch (Throwable) {
            return false;
        }

        return $this->isNumericType($tenantType) === $this->isNumericType($domainType);
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

    protected function tenancyEnabled(): bool
    {
        return (bool) config('upsoftware.tenancy.enabled', config('tenancy.enabled', false));
    }

    protected function targetDomainsTable(): string
    {
        return $this->tenancyEnabled()
            ? $this->tenantDomainsTable
            : $this->plainDomainsTable;
    }

    protected function alternateDomainsTable(): string
    {
        return $this->targetDomainsTable() === $this->tenantDomainsTable
            ? $this->plainDomainsTable
            : $this->tenantDomainsTable;
    }
};

