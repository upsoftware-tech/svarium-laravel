<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private function isSqlite(): bool
    {
        return DB::getDriverName() === 'sqlite';
    }

    private function columnExists(string $table, string $column): bool
    {
        if ($this->isSqlite()) {
            return Schema::hasColumn($table, $column);
        }

        return DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->exists();
    }

    private function indexExists(string $table, string $index): bool
    {
        if ($this->isSqlite()) {
            $indexes = DB::select(sprintf('PRAGMA index_list("%s")', $table));

            foreach ($indexes as $sqliteIndex) {
                if ((string) ($sqliteIndex->name ?? '') === $index) {
                    return true;
                }
            }

            return false;
        }

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

    private function dropColumnIfExists(string $table, string $column): void
    {
        if (! $this->columnExists($table, $column)) {
            return;
        }

        try {
            DB::statement("ALTER TABLE `$table` DROP COLUMN `$column`");
        } catch (QueryException $exception) {
            if ($this->isMissingColumnError($exception, $column)) {
                return;
            }

            // Jeżeli kolumna zniknęła w międzyczasie (stan częściowej migracji),
            // nie blokujemy całej migracji.
            if (! $this->columnExists($table, $column)) {
                return;
            }

            throw $exception;
        }
    }

    private function isMissingColumnError(QueryException $exception, string $column): bool
    {
        $errorInfo = $exception->errorInfo ?? [];
        $driverCode = (int) ($errorInfo[1] ?? 0);
        $message = strtolower($exception->getMessage());

        if (in_array($driverCode, [1072, 1091], true)) {
            return true;
        }

        return str_contains($message, strtolower($column))
            && str_contains($message, "doesn't exist");
    }

    private function resolveRoleDisplayName(mixed $name, string $locale): string
    {
        if (is_array($name)) {
            $candidate = $name[$locale] ?? reset($name);

            return is_string($candidate) ? trim($candidate) : '';
        }

        if (! is_string($name)) {
            return '';
        }

        $decoded = json_decode($name, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $candidate = $decoded[$locale] ?? reset($decoded);

            return is_string($candidate) ? trim($candidate) : '';
        }

        return trim($name);
    }

    private function resolveRoleKey(string $displayName, int|string|null $id = null): string
    {
        $normalized = Str::of($displayName)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        if (in_array($normalized, ['superadministrator', 'super_admin', 'superadmin'], true)) {
            return 'superadmin';
        }

        if (in_array($normalized, ['administrator', 'admin'], true)) {
            return 'admin';
        }

        if (in_array($normalized, ['pacjent', 'patient'], true)) {
            return 'patient';
        }

        if (in_array($normalized, ['specjalista', 'specialist', 'therapist', 'terapeuta'], true)) {
            return 'specialist';
        }

        if ($normalized !== '') {
            return $normalized;
        }

        return $id !== null && $id !== '' ? 'role_'.$id : 'role';
    }

    public function up(): void
    {
        $locale = app()->getLocale(); // stała wartość zapisana w strukturze DB

        // 1. usuń stary unique index
        if ($this->indexExists('roles', 'roles_name_guard_name_unique')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropUnique('roles_name_guard_name_unique');
            });
        }

        // 2. upewnij się, że istnieje kolumna name, a potem dodaj name_json
        if (! $this->columnExists('roles', 'name')) {
            $afterGuardName = $this->columnExists('roles', 'guard_name');

            Schema::table('roles', function (Blueprint $table) use ($afterGuardName) {
                $column = $table->string('name')->nullable();

                if ($afterGuardName) {
                    $column->after('guard_name');
                }
            });
        }

        if (! $this->columnExists('roles', 'name_json')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->json('name_json')->nullable()->after('name');
            });
        }

        // 3. migracja danych
        if ($this->columnExists('roles', 'name') && $this->columnExists('roles', 'name_json')) {
            DB::table('roles')->orderBy('id')->chunkById(100, function ($roles) use ($locale) {
                foreach ($roles as $role) {
                    DB::table('roles')
                        ->where('id', $role->id)
                        ->update([
                            'name_json' => json_encode([$locale => $role->name], JSON_UNESCAPED_UNICODE),
                        ]);
                }
            });
        }

        // 4. usuń starą kolumnę
        $this->dropColumnIfExists('roles', 'name');

        // 5. rename json → name
        if ($this->columnExists('roles', 'name_json') && ! $this->columnExists('roles', 'name')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->renameColumn('name_json', 'name');
            });
        }

        // 6. generated column (dla indeksu spatie)
        if (! $this->columnExists('roles', 'name_locale') && $this->columnExists('roles', 'name')) {
            if ($this->isSqlite()) {
                Schema::table('roles', function (Blueprint $table) {
                    $table->string('name_locale', 191)->nullable();
                });

                DB::table('roles')
                    ->select(['id', 'name'])
                    ->orderBy('id')
                    ->chunkById(100, function ($roles) use ($locale) {
                        foreach ($roles as $role) {
                            $displayName = $this->resolveRoleDisplayName($role->name ?? null, $locale);

                            DB::table('roles')
                                ->where('id', $role->id)
                                ->update(['name_locale' => $displayName !== '' ? $displayName : null]);
                        }
                    });
            } else {
                DB::statement("
                    ALTER TABLE roles
                    ADD COLUMN name_locale VARCHAR(191)
                    GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(name, '$.\"{$locale}\"'))) STORED
                ");
            }
        }

        // 7. klucz systemowy roli (np. superadmin/admin) do logiki niezależnej od tłumaczeń
        if (! $this->columnExists('roles', 'role_key')) {
            $afterColumn = $this->columnExists('roles', 'name_locale')
                ? 'name_locale'
                : ($this->columnExists('roles', 'guard_name') ? 'guard_name' : null);

            Schema::table('roles', function (Blueprint $table) use ($afterColumn) {
                $column = $table->string('role_key', 191)->nullable();

                if (is_string($afterColumn) && $afterColumn !== '') {
                    $column->after($afterColumn);
                }
            });
        }

        if ($this->columnExists('roles', 'role_key') && $this->columnExists('roles', 'name')) {
            DB::table('roles')
                ->select(['id', 'name', 'role_key'])
                ->orderBy('id')
                ->chunkById(100, function ($roles) use ($locale) {
                    foreach ($roles as $role) {
                        $currentKey = strtolower(trim((string) ($role->role_key ?? '')));
                        if ($currentKey !== '') {
                            continue;
                        }

                        $displayName = $this->resolveRoleDisplayName($role->name ?? null, $locale);
                        $roleKey = $this->resolveRoleKey($displayName, $role->id ?? null);

                        DB::table('roles')
                            ->where('id', $role->id)
                            ->update(['role_key' => $roleKey]);
                    }
                });
        }

        if (! $this->indexExists('roles', 'roles_role_key_index') && $this->columnExists('roles', 'role_key')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->index('role_key', 'roles_role_key_index');
            });
        }

        // 8. odtwórz unique index
        if (! $this->indexExists('roles', 'roles_name_guard_name_unique') && $this->columnExists('roles', 'name_locale')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->unique(['name_locale', 'guard_name'], 'roles_name_guard_name_unique');
            });
        }
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
            DB::statement('ALTER TABLE `roles` DROP INDEX `roles_name_guard_name_unique`');
        }

        if ($this->indexExists('roles', 'roles_role_key_index')) {
            DB::statement('ALTER TABLE `roles` DROP INDEX `roles_role_key_index`');
        }

        if ($this->columnExists('roles', 'role_key')) {
            DB::statement('ALTER TABLE `roles` DROP COLUMN `role_key`');
        }

        /*
         |-------------------------------------------------
         | 2. Usuń generated column (zależność od name)
         |-------------------------------------------------
         */
        if ($this->columnExists('roles', 'name_locale')) {
            DB::statement('ALTER TABLE `roles` DROP COLUMN `name_locale`');
        }

        /*
         |-------------------------------------------------
         | 3. Dodaj kolumnę string
         |-------------------------------------------------
         */
        if (! $this->columnExists('roles', 'name_string')) {
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
                        'name_string' => $json[$locale] ?? (is_array($json) ? reset($json) : null),
                    ]);
            }
        });

        /*
         |-------------------------------------------------
         | 5. Usuń kolumnę JSON (po usunięciu dependency)
         |-------------------------------------------------
         */
        if ($this->columnExists('roles', 'name')) {
            $this->dropColumnIfExists('roles', 'name');
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
        if (! $this->indexExists('roles', 'roles_name_guard_name_unique')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
            });
        }
    }
};
