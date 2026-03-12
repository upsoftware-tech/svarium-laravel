<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        Schema::table('settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('settings', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('key');
            }
        });

        $this->cleanupUserKeyDuplicates();

        Schema::table('settings', function (Blueprint $table): void {
            if (! Schema::hasIndex('settings', 'settings_key_index')) {
                $table->index('key', 'settings_key_index');
            }

            if (! Schema::hasIndex('settings', 'settings_user_id_key_unique', 'unique')) {
                $table->unique(['user_id', 'key'], 'settings_user_id_key_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        Schema::table('settings', function (Blueprint $table): void {
            if (Schema::hasIndex('settings', 'settings_user_id_key_unique', 'unique')) {
                $table->dropUnique('settings_user_id_key_unique');
            }

            if (Schema::hasIndex('settings', 'settings_key_index')) {
                $table->dropIndex('settings_key_index');
            }

            if (Schema::hasColumn('settings', 'user_id')) {
                $table->dropColumn('user_id');
            }
        });
    }

    protected function cleanupUserKeyDuplicates(): void
    {
        $duplicates = DB::table('settings')
            ->select(
                'user_id',
                'key',
                DB::raw('MAX(id) as keep_id'),
                DB::raw('COUNT(*) as aggregate')
            )
            ->whereNotNull('user_id')
            ->whereNotNull('key')
            ->groupBy('user_id', 'key')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            DB::table('settings')
                ->where('user_id', $row->user_id)
                ->where('key', $row->key)
                ->where('id', '<>', $row->keep_id)
                ->delete();
        }
    }
};

