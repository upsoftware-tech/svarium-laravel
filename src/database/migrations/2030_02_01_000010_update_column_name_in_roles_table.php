<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function columnExists(string $table, string $column): bool
    {
        return DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->exists();
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->indexExists($table, $index)) {
            DB::statement("ALTER TABLE `$table` DROP INDEX `$index`");
        }
    }

    public function up(): void
    {
        $locale = app()->getLocale(); // stała wartość zapisana w strukturze DB

        // 1. usuń stary unique index
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_name_guard_name_unique');
        });

        // 2. dodaj kolumnę json
        Schema::table('roles', function (Blueprint $table) {
            $table->json('name_json')->nullable()->after('name');
        });

        // 3. migracja danych
        DB::table('roles')->orderBy('id')->chunkById(100, function ($roles) use ($locale) {
            foreach ($roles as $role) {
                DB::table('roles')
                    ->where('id', $role->id)
                    ->update([
                        'name_json' => json_encode([$locale => $role->name], JSON_UNESCAPED_UNICODE)
                    ]);
            }
        });

        // 4. usuń starą kolumnę
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        // 5. rename json → name
        Schema::table('roles', function (Blueprint $table) {
            $table->renameColumn('name_json', 'name');
        });

        // 6. generated column (dla indeksu spatie)
        DB::statement("
            ALTER TABLE roles
            ADD COLUMN name_locale VARCHAR(191)
            GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"{$locale}\"'))) STORED
        ");

        // 7. odtwórz unique index
        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['name_locale', 'guard_name'], 'roles_name_guard_name_unique');
        });
    }

    public function down(): void
    {
        $locale = app()->getLocale();

        /*
         |-------------------------------------------------
         | 1. Usuń UNIQUE jeśli istnieje
         |-------------------------------------------------
         */
        if ($this->indexExists('roles', 'roles_name_guard_name_unique')) {
            DB::statement("ALTER TABLE `roles` DROP INDEX `roles_name_guard_name_unique`");
        }

        /*
         |-------------------------------------------------
         | 2. Usuń generated column (zależność od name)
         |-------------------------------------------------
         */
        if ($this->columnExists('roles', 'name_locale')) {
            DB::statement("ALTER TABLE `roles` DROP COLUMN `name_locale`");
        }

        /*
         |-------------------------------------------------
         | 3. Dodaj kolumnę string
         |-------------------------------------------------
         */
        if (!$this->columnExists('roles', 'name_string')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('name_string')->nullable()->after('name');
            });
        }

        /*
         |-------------------------------------------------
         | 4. Przenieś dane JSON -> string
         |-------------------------------------------------
         */
        DB::table('roles')->orderBy('id')->chunkById(100, function ($roles) use ($locale) {
            foreach ($roles as $role) {
                $json = json_decode($role->name, true);

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update([
                        'name_string' => $json[$locale] ?? (is_array($json) ? reset($json) : null)
                    ]);
            }
        });

        /*
         |-------------------------------------------------
         | 5. Usuń kolumnę JSON (po usunięciu dependency)
         |-------------------------------------------------
         */
        if ($this->columnExists('roles', 'name')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('name');
            });
        }

        /*
         |-------------------------------------------------
         | 6. Rename string -> name
         |-------------------------------------------------
         */
        if ($this->columnExists('roles', 'name_string')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->renameColumn('name_string', 'name');
            });
        }

        /*
         |-------------------------------------------------
         | 7. Odtwórz oryginalny index Spatie
         |-------------------------------------------------
         */
        if (!$this->indexExists('roles', 'roles_name_guard_name_unique')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
            });
        }
    }
};
