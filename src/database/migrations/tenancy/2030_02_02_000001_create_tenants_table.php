<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return array<int, string>
     */
    protected function runtimeEnvironmentOptions(): array
    {
        return ['local', 'prod', 'development'];
    }

    protected function normalizeRuntimeEnvironment(?string $value = null): string
    {
        $environment = strtolower(trim((string) ($value ?? app()->environment())));

        if (in_array($environment, ['prod', 'production'], true)) {
            return 'prod';
        }

        if ($environment === 'local') {
            return 'local';
        }

        return 'development';
    }

    public function up(): void
    {
        $runtimeEnvironment = $this->normalizeRuntimeEnvironment();
        $runtimeEnvironmentOptions = $this->runtimeEnvironmentOptions();

        if (! Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) use ($runtimeEnvironment, $runtimeEnvironmentOptions): void {
                $table->id();
                $table->string('name')->nullable();
                $table->string('slug')->nullable()->unique();
                $table->boolean('status')->default(true);
                $table->enum('env', $runtimeEnvironmentOptions)->default($runtimeEnvironment);
                $table->string('tenancy_db_host')->nullable();
                $table->unsignedInteger('tenancy_db_port')->nullable();
                $table->string('tenancy_db_username')->nullable();
                $table->text('tenancy_db_name')->nullable();
                $table->text('tenancy_db_password')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('tenants', function (Blueprint $table) use ($runtimeEnvironment, $runtimeEnvironmentOptions): void {
            if (! Schema::hasColumn('tenants', 'name')) {
                $table->string('name')->nullable()->after('id');
            }

            if (! Schema::hasColumn('tenants', 'slug')) {
                $table->string('slug')->nullable()->unique()->after('name');
            }

            if (! Schema::hasColumn('tenants', 'status')) {
                $table->boolean('status')->default(true)->after('slug');
            }

            if (! Schema::hasColumn('tenants', 'env')) {
                $table->enum('env', $runtimeEnvironmentOptions)->default($runtimeEnvironment)->after('status');
            }

            if (! Schema::hasColumn('tenants', 'tenancy_db_host')) {
                $table->string('tenancy_db_host')->nullable();
            }

            if (! Schema::hasColumn('tenants', 'tenancy_db_port')) {
                $table->unsignedInteger('tenancy_db_port')->nullable();
            }

            if (! Schema::hasColumn('tenants', 'tenancy_db_username')) {
                $table->string('tenancy_db_username')->nullable();
            }

            if (! Schema::hasColumn('tenants', 'tenancy_db_name')) {
                $table->text('tenancy_db_name')->nullable();
            }

            if (! Schema::hasColumn('tenants', 'tenancy_db_password')) {
                $table->text('tenancy_db_password')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        Schema::dropIfExists('tenants');
    }
};
