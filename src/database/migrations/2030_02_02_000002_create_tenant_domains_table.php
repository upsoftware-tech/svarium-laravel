<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_domains')) {
            Schema::create('tenant_domains', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('domain')->unique();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });

            return;
        }

        Schema::table('tenant_domains', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenant_domains', 'tenant_id')) {
                $table->string('tenant_id');
            }

            if (! Schema::hasColumn('tenant_domains', 'domain')) {
                $table->string('domain')->unique();
            }

            if (! Schema::hasColumn('tenant_domains', 'is_primary')) {
                $table->boolean('is_primary')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_domains');
    }
};
