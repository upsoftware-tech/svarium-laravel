<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;
use Upsoftware\Svarium\Models\Role;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class MakePermissionCommand extends CoreCommand
{
    protected $signature = 'svarium:permission';
    protected $description = 'Create base permission settings';
    protected $descriptionKey = 'permission';

    public function handle()
    {
        $presetRoles = multiselect(
            'Jakie role chcesz utworzyć w systemie?',
            [
                'superadmin' => 'Superadministrator',
                'admin' => 'Administrator',
            ]
        );

        foreach ($presetRoles as $roleKey) {
            $label = $roleKey === 'superadmin' ? 'Superadministrator' : 'Administrator';
            $this->upsertRole(
                $roleKey,
                [
                    'pl' => $label,
                    'en' => $label,
                ],
                'web'
            );
        }

        $newRole = false;
        while (! $newRole) {
            if (!confirm('Czy chcesz dodać kolejną rolę?', false, 'Tak', 'Nie')) {
                $newRole = true;
            } else {
                $role = [];
                foreach (locales() as $locale) {
                    $localeCode = (string) ($locale['value'] ?? '');
                    if ($localeCode === '') {
                        continue;
                    }

                    $role[$localeCode] = trim((string) text('Nazwa roli ('.($locale['label'] ?? $localeCode).')'));
                }

                $primaryName = $this->resolvePrimaryName($role);
                if ($primaryName === '') {
                    continue;
                }

                $guardName = select('Przestrzeń', ['web' => 'Front / Web', 'api' => 'Api', 'panel' => 'Panel']);
                $this->upsertRole($this->resolveRoleKey($primaryName), $role, $guardName);
            }
        }

        return self::SUCCESS;
    }

    protected function upsertRole(string $roleKey, array $translations, string $guard): void
    {
        $role = null;
        $hasRoleKeyColumn = $this->hasRoleKeyColumn();
        $nameIsJson = $this->isNameJsonColumn();

        if ($hasRoleKeyColumn) {
            $role = Role::query()
                ->where('guard_name', $guard)
                ->where('role_key', $roleKey)
                ->first();
        }

        if (! $role) {
            $primaryName = $this->resolvePrimaryName($translations);
            if ($primaryName === '') {
                return;
            }

            if (! $nameIsJson) {
                $role = Role::query()->firstOrNew([
                    'name' => $primaryName,
                    'guard_name' => $guard,
                ]);
            } else {
                $role = Role::query()
                    ->where('guard_name', $guard)
                    ->get()
                    ->first(function (Role $existing) use ($primaryName): bool {
                        return $this->resolveRoleDisplayName($existing) === $primaryName;
                    });

                if (! $role) {
                    $role = new Role;
                    $role->guard_name = $guard;
                }
            }
        }

        $role->guard_name = $guard;

        if ($hasRoleKeyColumn) {
            $role->setAttribute('role_key', $roleKey);
        }

        if ($nameIsJson) {
            $payload = $this->normalizeTranslations($translations);
            if ($payload === []) {
                return;
            }

            if (method_exists($role, 'setTranslations')) {
                $role->setTranslations('name', $payload);
            } else {
                $role->setAttribute('name', json_encode($payload, JSON_UNESCAPED_UNICODE));
            }
        } else {
            $role->setAttribute('name', $this->resolvePrimaryName($translations));
        }

        $role->save();
    }

    protected function resolvePrimaryName(array $translations): string
    {
        $preferredLocale = trim((string) app()->getLocale());
        if ($preferredLocale !== '') {
            $preferred = trim((string) ($translations[$preferredLocale] ?? ''));
            if ($preferred !== '') {
                return $preferred;
            }
        }

        foreach ($translations as $value) {
            $normalized = trim((string) $value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    protected function normalizeTranslations(array $translations): array
    {
        $normalized = [];
        foreach ($translations as $locale => $value) {
            $localeCode = trim((string) $locale);
            $translation = trim((string) $value);

            if ($localeCode === '' || $translation === '') {
                continue;
            }

            $normalized[$localeCode] = $translation;
        }

        return $normalized;
    }

    protected function hasRoleKeyColumn(): bool
    {
        try {
            return Schema::hasTable((new Role)->getTable())
                && Schema::hasColumn((new Role)->getTable(), 'role_key');
        } catch (Throwable) {
            return false;
        }
    }

    protected function isNameJsonColumn(): bool
    {
        try {
            $table = (new Role)->getTable();
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'name')) {
                return false;
            }

            $type = strtolower((string) Schema::getColumnType($table, 'name'));

            return in_array($type, ['json', 'jsonb'], true);
        } catch (Throwable) {
            return false;
        }
    }

    protected function resolveRoleDisplayName(Role $role): string
    {
        $locale = trim((string) app()->getLocale());
        if ($locale === '') {
            $locale = 'en';
        }

        if (method_exists($role, 'getTranslation')) {
            try {
                $translated = $role->getTranslation('name', $locale, false);
                if (is_string($translated) && trim($translated) !== '') {
                    return trim($translated);
                }
            } catch (Throwable) {
                // fallback below
            }
        }

        $name = $role->getAttribute('name');
        if (is_array($name)) {
            $candidate = $name[$locale] ?? reset($name);

            return is_string($candidate) ? trim($candidate) : '';
        }

        if (is_string($name)) {
            $decoded = json_decode($name, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $candidate = $decoded[$locale] ?? reset($decoded);

                return is_string($candidate) ? trim($candidate) : '';
            }

            return trim($name);
        }

        return '';
    }

    protected function resolveRoleKey(string $roleName): string
    {
        $normalized = Str::of($roleName)
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

        return $normalized !== '' ? $normalized : 'role';
    }
}
