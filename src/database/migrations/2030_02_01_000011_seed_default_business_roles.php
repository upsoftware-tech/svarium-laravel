<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    private function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }

    private function resolveDisplayName(mixed $name, string $locale): string
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

    private function shouldStoreNameAsJson(): bool
    {
        $type = strtolower((string) Schema::getColumnType('roles', 'name'));
        if (in_array($type, ['json', 'jsonb'], true)) {
            return true;
        }

        $sample = DB::table('roles')
            ->whereNotNull('name')
            ->value('name');

        if (! is_string($sample) || trim($sample) === '') {
            return false;
        }

        $decoded = json_decode($sample, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded);
    }

    private function roleDefinitions(): array
    {
        return [
            'admin' => [
                'label' => 'Administrator',
                'aliases' => ['admin', 'administrator'],
            ],
            'superadmin' => [
                'label' => 'Superadministrator',
                'aliases' => ['superadmin', 'super_admin', 'superadministrator'],
            ],
        ];
    }

    private function findExistingRole(
        string $guard,
        string $roleKey,
        array $aliases,
        string $locale,
        bool $hasRoleKeyColumn
    ): ?object {
        if ($hasRoleKeyColumn) {
            $byKey = DB::table('roles')
                ->where('guard_name', $guard)
                ->where('role_key', $roleKey)
                ->first();

            if ($byKey) {
                return $byKey;
            }
        }

        $normalizedAliases = array_values(array_unique(array_filter(array_map(
            fn (string $alias): string => $this->normalize($alias),
            $aliases
        ))));

        $rows = DB::table('roles')
            ->where('guard_name', $guard)
            ->get(['id', 'name', 'role_key']);

        foreach ($rows as $row) {
            $displayName = $this->resolveDisplayName($row->name ?? null, $locale);
            if ($displayName === '') {
                continue;
            }

            $normalizedName = $this->normalize($displayName);
            if (in_array($normalizedName, $normalizedAliases, true)) {
                return $row;
            }
        }

        return null;
    }

    public function up(): void
    {
        if (! $this->tableExists('roles')) {
            return;
        }

        if (! $this->columnExists('roles', 'name') || ! $this->columnExists('roles', 'guard_name')) {
            return;
        }

        $hasRoleKeyColumn = $this->columnExists('roles', 'role_key');
        $storeNameAsJson = $this->shouldStoreNameAsJson();
        $locale = trim((string) config('app.locale', app()->getLocale()));
        if ($locale === '') {
            $locale = 'en';
        }

        $guard = trim((string) config('auth.defaults.guard', 'web'));
        if ($guard === '') {
            $guard = 'web';
        }

        foreach ($this->roleDefinitions() as $roleKey => $definition) {
            $label = trim((string) ($definition['label'] ?? $roleKey));
            if ($label === '') {
                $label = Str::headline($roleKey);
            }

            $aliases = (array) ($definition['aliases'] ?? []);
            $aliases[] = $label;

            $existing = $this->findExistingRole(
                guard: $guard,
                roleKey: $roleKey,
                aliases: $aliases,
                locale: $locale,
                hasRoleKeyColumn: $hasRoleKeyColumn
            );

            $nameValue = $storeNameAsJson
                ? json_encode([$locale => $label], JSON_UNESCAPED_UNICODE)
                : $label;

            $payload = [
                'name' => $nameValue,
                'guard_name' => $guard,
                'updated_at' => now(),
            ];

            if ($hasRoleKeyColumn) {
                $payload['role_key'] = $roleKey;
            }

            if ($existing) {
                DB::table('roles')
                    ->where('id', $existing->id)
                    ->update($payload);

                continue;
            }

            $payload['created_at'] = now();
            DB::table('roles')->insert($payload);
        }
    }

    public function down(): void
    {
        // Intentionally left empty to avoid deleting existing business roles in live systems.
    }
};
