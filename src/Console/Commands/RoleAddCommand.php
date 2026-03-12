<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class RoleAddCommand extends CoreCommand
{
    protected $signature = 'svarium:role.add
        {--name= : Nazwa roli}
        {--guard= : Guard roli (np. web, api)}';

    protected $description = 'Dodaje nową rolę do systemu';

    public function handle(): int
    {
        try {
            $roleModelClass = $this->resolveRoleModelClass();
            $name = $this->resolveRoleName();
            $guard = $this->resolveGuardName();

            $roleNameIsJson = $this->isRoleNameJsonColumn($roleModelClass);

            [$role, $created] = $this->findOrCreateRole(
                $roleModelClass,
                $name,
                $guard,
                $roleNameIsJson
            );

            if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            }

            if ($created) {
                $this->info('Rola została utworzona.');
            } else {
                $this->warn('Rola już istnieje. Zwracam istniejący rekord.');
            }

            $this->line('ID: '.(string) ($role->id ?? ''));
            $this->line('Nazwa: '.$this->resolveRoleDisplayName($role));
            $this->line('Guard: '.(string) ($role->guard_name ?? $guard));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return class-string<Model>
     */
    protected function resolveRoleModelClass(): string
    {
        $roleModelClass = (string) config('permission.models.role', \Spatie\Permission\Models\Role::class);

        if ($roleModelClass === '' || ! class_exists($roleModelClass)) {
            throw new RuntimeException("Nie znaleziono modelu roli: {$roleModelClass}");
        }

        if (! is_subclass_of($roleModelClass, Model::class)) {
            throw new RuntimeException("Model roli [{$roleModelClass}] nie dziedziczy po Illuminate\\Database\\Eloquent\\Model.");
        }

        return $roleModelClass;
    }

    protected function resolveRoleName(): string
    {
        $name = trim((string) $this->option('name'));

        if ($name !== '') {
            return $name;
        }

        if (! $this->input->isInteractive()) {
            throw new RuntimeException('Nazwa roli jest wymagana. Użyj --name=');
        }

        while ($name === '') {
            $name = trim((string) text('Nazwa roli', 'np. Editor'));
        }

        return $name;
    }

    protected function resolveGuardName(): string
    {
        $guard = trim((string) $this->option('guard'));

        if ($guard !== '') {
            return $guard;
        }

        $defaultGuard = trim((string) config('auth.defaults.guard', 'web'));
        if ($defaultGuard === '') {
            $defaultGuard = 'web';
        }

        if (! $this->input->isInteractive()) {
            return $defaultGuard;
        }

        $configuredGuards = array_keys((array) config('auth.guards', []));
        if ($configuredGuards === []) {
            return $defaultGuard;
        }

        if (! in_array($defaultGuard, $configuredGuards, true)) {
            $defaultGuard = $configuredGuards[0];
        }

        return (string) select(
            label: 'Wybierz guard roli',
            options: array_combine($configuredGuards, $configuredGuards),
            default: $defaultGuard
        );
    }

    /**
     * @param class-string<Model> $roleModelClass
     */
    protected function isRoleNameJsonColumn(string $roleModelClass): bool
    {
        try {
            /** @var Model $model */
            $model = new $roleModelClass();
            $table = $model->getTable();

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'name')) {
                return false;
            }

            $type = strtolower((string) Schema::getColumnType($table, 'name'));

            return in_array($type, ['json', 'jsonb'], true);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param class-string<Model> $roleModelClass
     * @return array{0: Model, 1: bool}
     */
    protected function findOrCreateRole(
        string $roleModelClass,
        string $roleName,
        string $guard,
        bool $roleNameIsJson
    ): array {
        $roleKey = $this->resolveRoleKey($roleName);
        $hasRoleKeyColumn = $this->roleTableHasRoleKeyColumn($roleModelClass);

        if ($hasRoleKeyColumn && $roleKey !== '') {
            /** @var Model|null $existingByKey */
            $existingByKey = $roleModelClass::query()
                ->where('guard_name', $guard)
                ->where('role_key', $roleKey)
                ->first();

            if ($existingByKey) {
                return [$existingByKey, false];
            }
        }

        if (! $roleNameIsJson) {
            /** @var Model $role */
            $role = $roleModelClass::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => $guard,
            ]);

            if ($hasRoleKeyColumn && trim((string) $role->getAttribute('role_key')) === '') {
                $role->setAttribute('role_key', $roleKey);
                $role->save();
            }

            return [$role, (bool) $role->wasRecentlyCreated];
        }

        $roles = $roleModelClass::query()
            ->where('guard_name', $guard)
            ->get();

        foreach ($roles as $existingRole) {
            if ($this->resolveRoleDisplayName($existingRole) === $roleName) {
                if ($hasRoleKeyColumn && trim((string) $existingRole->getAttribute('role_key')) === '') {
                    $existingRole->setAttribute('role_key', $roleKey);
                    $existingRole->save();
                }

                return [$existingRole, false];
            }
        }

        $locale = (string) app()->getLocale();
        if ($locale === '') {
            $locale = 'en';
        }

        /** @var Model $role */
        $role = new $roleModelClass();
        $role->setAttribute('guard_name', $guard);

        if (method_exists($role, 'setTranslation')) {
            $role->setTranslation('name', $locale, $roleName);
        } else {
            $role->setAttribute('name', json_encode([$locale => $roleName], JSON_UNESCAPED_UNICODE));
        }

        if ($hasRoleKeyColumn) {
            $role->setAttribute('role_key', $roleKey);
        }

        $role->save();

        return [$role, true];
    }

    protected function resolveRoleDisplayName(object $role): string
    {
        $locale = (string) app()->getLocale();
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

        $name = $role->name ?? null;

        if (is_string($name)) {
            $decoded = json_decode($name, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $candidate = $decoded[$locale] ?? reset($decoded);
                return is_string($candidate) ? trim($candidate) : '';
            }

            return trim($name);
        }

        if (is_array($name)) {
            $candidate = $name[$locale] ?? reset($name);
            return is_string($candidate) ? trim($candidate) : '';
        }

        return '';
    }

    /**
     * @param class-string<Model> $roleModelClass
     */
    protected function roleTableHasRoleKeyColumn(string $roleModelClass): bool
    {
        try {
            /** @var Model $model */
            $model = new $roleModelClass();
            $table = $model->getTable();

            return Schema::hasTable($table) && Schema::hasColumn($table, 'role_key');
        } catch (Throwable) {
            return false;
        }
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

        return $normalized !== '' ? $normalized : 'role';
    }
}
