<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureColumns('tenant_domains');
        $this->ensureColumns('domains');
    }

    public function down(): void
    {
        // Intentionally left empty to avoid destructive rollback on production data.
    }

    protected function ensureColumns(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $blueprint) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'is_primary')) {
                $blueprint->boolean('is_primary')->default(false);
            }

            if (! Schema::hasColumn($tableName, 'locale')) {
                $blueprint->string('locale', 12)->nullable();
            }

            if (! Schema::hasColumn($tableName, 'theme')) {
                $blueprint->string('theme', 100)->nullable();
            }

            if (! Schema::hasColumn($tableName, 'status')) {
                $blueprint->boolean('status')->default(true);
            }

            if (! Schema::hasColumn($tableName, 'redirect_to_primary')) {
                $blueprint->boolean('redirect_to_primary')->default(false);
            }

            if (! Schema::hasColumn($tableName, 'force_https')) {
                $blueprint->boolean('force_https')->default(false);
            }
        });
    }
};
