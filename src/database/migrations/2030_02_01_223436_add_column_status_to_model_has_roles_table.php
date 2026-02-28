<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->tinyInteger('status')->default(1);
                $table->string('tenant_id')->nullable();

                $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
                $table->index('role_id', 'model_has_roles_role_id_foreign');
                $table->foreign('role_id', 'model_has_roles_role_id_foreign')
                    ->references('id')
                    ->on('roles')
                    ->onDelete('cascade');
            });

            return;
        }

        Schema::table('model_has_roles', function (Blueprint $table) {
            if (! Schema::hasColumn('model_has_roles', 'status')) {
                $table->tinyInteger('status')->default(1);
            }

            if (! Schema::hasColumn('model_has_roles', 'tenant_id')) {
                $table->string('tenant_id')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('model_has_roles')) {
            return;
        }

        Schema::table('model_has_roles', function (Blueprint $table) {
            $columnsToDrop = [];

            if (Schema::hasColumn('model_has_roles', 'status')) {
                $columnsToDrop[] = 'status';
            }

            if (Schema::hasColumn('model_has_roles', 'tenant_id')) {
                $columnsToDrop[] = 'tenant_id';
            }

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
