<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        $typeColumn = trim((string) config('upsoftware.tenancy.owner.type_column', 'owner_type'));
        $idColumn = trim((string) config('upsoftware.tenancy.owner.id_column', 'owner_id'));

        if ($typeColumn === '') {
            $typeColumn = 'owner_type';
        }

        if ($idColumn === '') {
            $idColumn = 'owner_id';
        }

        Schema::table('tenants', function (Blueprint $table) use ($typeColumn, $idColumn): void {
            if (! Schema::hasColumn('tenants', $typeColumn)) {
                $table->string($typeColumn)->nullable()->after('status');
            }

            if (! Schema::hasColumn('tenants', $idColumn)) {
                $table->string($idColumn)->nullable()->after($typeColumn);
            }
        });

        if (! Schema::hasIndex('tenants', 'tenants_owner_lookup_index')) {
            Schema::table('tenants', function (Blueprint $table) use ($typeColumn, $idColumn): void {
                $table->index([$typeColumn, $idColumn], 'tenants_owner_lookup_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        $typeColumn = trim((string) config('upsoftware.tenancy.owner.type_column', 'owner_type'));
        $idColumn = trim((string) config('upsoftware.tenancy.owner.id_column', 'owner_id'));

        if ($typeColumn === '') {
            $typeColumn = 'owner_type';
        }

        if ($idColumn === '') {
            $idColumn = 'owner_id';
        }

        if (Schema::hasIndex('tenants', 'tenants_owner_lookup_index')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->dropIndex('tenants_owner_lookup_index');
            });
        }

        Schema::table('tenants', function (Blueprint $table) use ($typeColumn, $idColumn): void {
            if (Schema::hasColumn('tenants', $idColumn)) {
                $table->dropColumn($idColumn);
            }

            if (Schema::hasColumn('tenants', $typeColumn)) {
                $table->dropColumn($typeColumn);
            }
        });
    }
};

