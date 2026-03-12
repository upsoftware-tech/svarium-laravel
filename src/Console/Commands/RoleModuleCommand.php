<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use Upsoftware\Svarium\Modules\Module;
use Upsoftware\Svarium\Modules\ModuleRegistry;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\OperationRegistry;
use Upsoftware\Svarium\Panel\Resource;
use Upsoftware\Svarium\Panel\ResourceRegistry;
use Upsoftware\Svarium\Roles\RolePermissionCatalog;
use function Laravel\Prompts\select;

class RoleModuleCommand extends CoreCommand
{
    protected const RESOURCE_ACTIONS = [
        'list',
        'create',
        'edit',
        'preview',
        'duplicate',
        'delete',
        'import',
    ];

    protected $signature = 'svarium:role.module
        {--target= : Cel: role albo user}
        {--role= : Rola (ID, role_key lub nazwa)}
        {--module= : Klucz modułu (np. patient, user, role)}
        {--guard= : Guard (np. web, api)}
        {--action= : Akcja: add albo revoke}
        {--revoke : Odbierz permissiony modułu zamiast przypisywać}
        {--list : Pokaż permissiony modułu i zakończ}';

    protected $description = 'Przypisuje (lub odbiera) wszystkie permissiony modułu do roli';

    /**
     * @var array<string, list<string>>
     */
    protected array $modulePermissionsCache = [];

    public function handle(): int
    {
        try {
            $action = $this->resolveAction();
            $target = $this->resolveTarget();

            if ($target === 'user') {
                return $this->forwardToUserAccess($action);
            }

            $roleModelClass = $this->resolveRoleModelClass();

            /** @var Model $role */
            $role = $this->resolveRole($roleModelClass, $this->resolveGuardOption());
            $guard = trim((string) ($role->getAttribute('guard_name') ?? ''));
            if ($guard === '') {
                $guard = $this->resolveGuardOption() ?? 'web';
            }

            app(RolePermissionCatalog::class)->ensurePermissionsForGuard($guard);
            $moduleKey = $this->resolveModuleKeyForRole($role, $guard, $action);

            $permissions = $this->modulePermissions($moduleKey, $guard);
            if ($permissions === []) {
                $this->warn("Brak permissionów dla modułu [{$moduleKey}].");

                return self::SUCCESS;
            }

            $this->line("Moduł: {$moduleKey}");
            $this->line('Permissiony: '.count($permissions));
            foreach ($permissions as $permission) {
                $this->line(" - {$permission}");
            }

            if ((bool) $this->option('list')) {
                return self::SUCCESS;
            }

            if (! method_exists($role, 'givePermissionTo') || ! method_exists($role, 'revokePermissionTo')) {
                throw new RuntimeException('Model roli nie wspiera metod givePermissionTo/revokePermissionTo.');
            }

            if ($action === 'revoke') {
                $role->revokePermissionTo($permissions);
                $this->info("Odebrano permissiony modułu [{$moduleKey}] dla roli [{$this->roleDisplayName($role)}].");
            } else {
                $role->givePermissionTo($permissions);
                $this->info("Przypisano permissiony modułu [{$moduleKey}] do roli [{$this->roleDisplayName($role)}].");
            }

            if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    protected function resolveAction(): string
    {
        $action = strtolower(trim((string) $this->option('action')));

        if ((bool) $this->option('revoke')) {
            $action = 'revoke';
        }

        if (in_array($action, ['add', 'revoke'], true)) {
            return $action;
        }

        if (! $this->input->isInteractive()) {
            return 'add';
        }

        return (string) select(
            label: 'Chcesz dodać czy odebrać dostęp?',
            options: [
                'add' => 'Dodać dostęp',
                'revoke' => 'Odebrać dostęp',
            ],
            default: 'add'
        );
    }

    protected function resolveTarget(): string
    {
        $target = strtolower(trim((string) $this->option('target')));
        if (in_array($target, ['role', 'user'], true)) {
            return $target;
        }

        if (! $this->input->isInteractive()) {
            return 'role';
        }

        return (string) select(
            label: 'Dostęp dla roli czy użytkownika?',
            options: [
                'role' => 'Rola',
                'user' => 'Użytkownik',
            ],
            default: 'role'
        );
    }

    protected function forwardToUserAccess(string $action): int
    {
        $parameters = [
            '--action' => $action,
        ];

        $guard = trim((string) $this->option('guard'));
        if ($guard !== '') {
            $parameters['--guard'] = $guard;
        }

        $role = trim((string) $this->option('role'));
        if ($role !== '') {
            $parameters['--role'] = [$role];
            $parameters['--type'] = 'role';
        }

        $this->line('Przełączam na flow użytkownika: svarium:user.access');

        return (int) $this->call('svarium:user.access', $parameters);
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

    protected function resolveGuardOption(): ?string
    {
        $guard = trim((string) $this->option('guard'));
        if ($guard !== '') {
            return $guard;
        }

        return null;
    }

    /**
     * @param class-string<Model> $roleModelClass
     */
    protected function resolveRole(string $roleModelClass, ?string $guard = null): Model
    {
        $raw = trim((string) $this->option('role'));
        if ($raw !== '') {
            $role = $this->findRoleByIdentifier($roleModelClass, $raw, $guard);
            if (! $role instanceof Model) {
                $guardLabel = $guard !== null && trim($guard) !== '' ? " dla guard [{$guard}]" : '';
                throw new RuntimeException("Nie znaleziono roli [{$raw}]{$guardLabel}.");
            }

            return $role;
        }

        if (! $this->input->isInteractive()) {
            throw new RuntimeException('Parametr --role jest wymagany.');
        }

        $query = $roleModelClass::query()->orderBy('id');
        if (is_string($guard) && trim($guard) !== '') {
            $query->where('guard_name', trim($guard));
        }
        $roles = $query->get();

        if ($roles->isEmpty()) {
            if (is_string($guard) && trim($guard) !== '') {
                throw new RuntimeException("Brak ról dla guard [{$guard}].");
            }

            throw new RuntimeException('Brak ról w systemie.');
        }

        $options = [];
        foreach ($roles as $role) {
            $id = (string) ($role->getAttribute('id') ?? '');
            if ($id === '') {
                continue;
            }

            $options[$id] = sprintf(
                '%s [id:%s, guard:%s]',
                $this->roleDisplayName($role),
                $id,
                (string) $role->getAttribute('guard_name')
            );
        }

        $selectedId = (string) select(
            label: 'Wybierz rolę',
            options: $options
        );

        $selectedRole = $roles->firstWhere('id', (int) $selectedId);
        if (! $selectedRole instanceof Model) {
            throw new RuntimeException('Nie udało się wybrać roli.');
        }

        return $selectedRole;
    }

    protected function resolveModuleKeyForRole(Model $role, string $guard, string $action): string
    {
        $allModules = $this->moduleOptions();
        if ($allModules === []) {
            throw new RuntimeException('Brak zarejestrowanych modułów.');
        }

        $raw = trim((string) $this->option('module'));
        if ($raw !== '') {
            $normalized = $this->normalizeModuleKey($raw);
            if (! isset($allModules[$normalized])) {
                throw new RuntimeException("Nie znaleziono modułu [{$raw}].");
            }

            return $normalized;
        }

        if (! $this->input->isInteractive()) {
            throw new RuntimeException('Parametr --module jest wymagany.');
        }

        $filtered = $this->moduleOptionsForRoleAction($role, $guard, $action, $allModules);
        if ($filtered === []) {
            if ($action === 'revoke') {
                throw new RuntimeException('Ta rola nie ma aktualnie żadnych przypisanych modułów do odebrania.');
            }

            throw new RuntimeException('Ta rola ma już dostęp do wszystkich modułów.');
        }

        return (string) select(
            label: 'Wybierz moduł',
            options: $filtered
        );
    }

    /**
     * @param class-string<Model> $roleModelClass
     */
    protected function findRoleByIdentifier(string $roleModelClass, string $identifier, ?string $guard = null): ?Model
    {
        $query = $roleModelClass::query();
        if (is_string($guard) && trim($guard) !== '') {
            $query->where('guard_name', trim($guard));
        }
        $normalized = trim($identifier);

        if ($normalized === '') {
            return null;
        }

        if (ctype_digit($normalized)) {
            $byId = (clone $query)->whereKey((int) $normalized)->first();
            if ($byId instanceof Model) {
                return $byId;
            }
        }

        if ($this->roleColumnExists($roleModelClass, 'role_key')) {
            $byRoleKey = (clone $query)->where('role_key', $normalized)->first();
            if ($byRoleKey instanceof Model) {
                return $byRoleKey;
            }
        }

        if ($this->roleColumnExists($roleModelClass, 'name_locale')) {
            $byNameLocale = (clone $query)->where('name_locale', $normalized)->first();
            if ($byNameLocale instanceof Model) {
                return $byNameLocale;
            }
        }

        $roles = $query->get();
        foreach ($roles as $role) {
            if ($this->roleDisplayName($role) === $normalized) {
                return $role;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function moduleOptions(): array
    {
        /** @var ModuleRegistry $registry */
        $registry = app(ModuleRegistry::class);
        $modules = $registry->all();

        $options = [];

        foreach ($modules as $module) {
            if (! $module instanceof Module) {
                continue;
            }

            $key = $this->normalizeModuleKey($module->name());
            $label = trim($module->name());

            if ($key === '') {
                continue;
            }

            if ($label === '') {
                $label = $key;
            }

            $options[$key] = "{$label} ({$key})";
        }

        $appModulesPath = svarium_modules();
        if (is_dir($appModulesPath)) {
            foreach (File::directories($appModulesPath) as $directory) {
                $folderName = trim((string) basename($directory));
                if ($folderName === '') {
                    continue;
                }

                $key = $this->normalizeModuleKey($folderName);
                if ($key === '' || isset($options[$key])) {
                    continue;
                }

                $label = (string) Str::of($folderName)->headline()->toString();
                $options[$key] = "{$label} ({$key})";
            }
        }

        ksort($options);

        return $options;
    }

    /**
     * @param array<string, string> $allModules
     * @return array<string, string>
     */
    protected function moduleOptionsForRoleAction(
        Model $role,
        string $guard,
        string $action,
        array $allModules
    ): array {
        $rolePermissions = $this->rolePermissionNames($role);
        $result = [];

        foreach ($allModules as $moduleKey => $label) {
            $permissions = $this->modulePermissions($moduleKey, $guard);
            if ($permissions === [] && $action === 'revoke') {
                continue;
            }

            $hasAny = $this->roleHasAnyPermission($rolePermissions, $permissions);
            $hasAll = $this->roleHasAllPermissions($rolePermissions, $permissions);
            if ($action === 'revoke' && ! $hasAny) {
                continue;
            }

            if ($action === 'add' && $hasAll) {
                continue;
            }

            if ($permissions === [] && $action === 'add') {
                $result[$moduleKey] = $label.' [brak permissionów]';
                continue;
            }

            $result[$moduleKey] = $label;
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    protected function rolePermissionNames(Model $role): array
    {
        try {
            if (method_exists($role, 'getPermissionNames')) {
                $names = $role->getPermissionNames();

                if (is_object($names) && method_exists($names, 'all')) {
                    return array_values(array_unique(array_filter(array_map(
                        static fn (mixed $value): string => trim((string) $value),
                        $names->all()
                    ), static fn (string $value): bool => $value !== '')));
                }
            }
        } catch (Throwable) {
            // fallback below
        }

        try {
            if (method_exists($role, 'permissions')) {
                return array_values(array_unique(array_filter(array_map(
                    static fn (mixed $value): string => trim((string) $value),
                    $role->permissions()->pluck('name')->all()
                ), static fn (string $value): bool => $value !== '')));
            }
        } catch (Throwable) {
            return [];
        }

        return [];
    }

    /**
     * @param list<string> $rolePermissions
     * @param list<string> $modulePermissions
     */
    protected function roleHasAnyPermission(array $rolePermissions, array $modulePermissions): bool
    {
        if ($rolePermissions === [] || $modulePermissions === []) {
            return false;
        }

        $rolePermissionsMap = array_fill_keys($rolePermissions, true);

        foreach ($modulePermissions as $permission) {
            if (isset($rolePermissionsMap[$permission])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $rolePermissions
     * @param list<string> $modulePermissions
     */
    protected function roleHasAllPermissions(array $rolePermissions, array $modulePermissions): bool
    {
        if ($modulePermissions === []) {
            return false;
        }

        $rolePermissionsMap = array_fill_keys($rolePermissions, true);

        foreach ($modulePermissions as $permission) {
            if (! isset($rolePermissionsMap[$permission])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    protected function modulePermissions(string $moduleKey, string $guard): array
    {
        $cacheKey = strtolower($guard).'|'.$moduleKey;

        if (array_key_exists($cacheKey, $this->modulePermissionsCache)) {
            return $this->modulePermissionsCache[$cacheKey];
        }

        return $this->modulePermissionsCache[$cacheKey] = $this->resolveModulePermissions($moduleKey, $guard);
    }

    /**
     * @return list<string>
     */
    protected function resolveModulePermissions(string $moduleKey, string $guard): array
    {
        $catalog = app(RolePermissionCatalog::class);
        $permissions = [];
        $moduleCandidates = $this->moduleCandidates($moduleKey);

        /** @var ResourceRegistry $resourceRegistry */
        $resourceRegistry = app(ResourceRegistry::class);
        foreach ($resourceRegistry->all() as $resourceClass) {
            if (! is_string($resourceClass) || ! class_exists($resourceClass) || ! is_subclass_of($resourceClass, Resource::class)) {
                continue;
            }

            if (! $this->resourceBelongsToModule($resourceClass, $moduleKey, $moduleCandidates)) {
                continue;
            }

            foreach (self::RESOURCE_ACTIONS as $action) {
                $permissions[] = $catalog->resourcePermissionName($resourceClass, $action);
            }
        }

        /** @var OperationRegistry $operationRegistry */
        $operationRegistry = app(OperationRegistry::class);
        foreach ($operationRegistry->definitions() as $definition) {
            $operationClass = trim((string) ($definition['operation'] ?? ''));
            if ($operationClass === '' || ! class_exists($operationClass)) {
                continue;
            }

            $uri = $this->extractOperationUri((string) ($definition['pattern'] ?? ''));
            if (! $this->operationBelongsToModule($operationClass, $uri, $moduleKey, $moduleCandidates)) {
                continue;
            }

            $permission = $catalog->operationPermissionName($operationClass, $uri);
            if (is_string($permission) && trim($permission) !== '') {
                $permissions[] = trim($permission);
            }
        }

        $permissions = [
            ...$permissions,
            ...$this->permissionsFromDatabaseFallback($moduleCandidates, $guard),
            ...$this->permissionsFromFilesystemFallback($moduleCandidates, $guard),
        ];

        $permissions = array_values(array_unique(array_filter(array_map(
            static fn (mixed $permission): string => trim((string) $permission),
            $permissions
        ), static fn (string $permission): bool => $permission !== '')));

        sort($permissions);

        return $permissions;
    }

    /**
     * @param list<string> $moduleCandidates
     * @return list<string>
     */
    protected function permissionsFromFilesystemFallback(array $moduleCandidates, string $guard): array
    {
        $catalog = app(RolePermissionCatalog::class);
        $permissions = [];

        $modulePaths = [];
        $basePath = svarium_modules();
        if (! is_dir($basePath)) {
            return [];
        }

        foreach (File::directories($basePath) as $directory) {
            $folderName = trim((string) basename($directory));
            if ($folderName === '') {
                continue;
            }

            $normalized = $this->normalizeModuleKey($folderName);
            if (! in_array($normalized, $moduleCandidates, true)) {
                continue;
            }

            $modulePaths[] = $directory;
        }

        foreach ($modulePaths as $modulePath) {
            foreach (File::allFiles($modulePath) as $file) {
                if (strtolower((string) $file->getExtension()) !== 'php') {
                    continue;
                }

                $class = $this->classFromPhpFile($file->getPathname());
                if ($class === null || ! class_exists($class)) {
                    continue;
                }

                if (is_subclass_of($class, Resource::class)) {
                    foreach (self::RESOURCE_ACTIONS as $action) {
                        $permissions[] = $catalog->resourcePermissionName($class, $action);
                    }

                    continue;
                }

                if (is_subclass_of($class, Operation::class)) {
                    $uri = method_exists($class, 'uri')
                        ? trim((string) $class::uri(), '/')
                        : '';

                    $permission = $catalog->operationPermissionName($class, $uri);
                    if (is_string($permission) && trim($permission) !== '') {
                        $permissions[] = trim($permission);
                    }
                }
            }
        }

        $permissions = array_values(array_unique(array_filter(array_map(
            static fn (mixed $permission): string => trim((string) $permission),
            $permissions
        ), static fn (string $permission): bool => $permission !== '')));

        if ($permissions !== []) {
            $this->ensurePermissionRecords($permissions, $guard);
        }

        return $permissions;
    }

    /**
     * @param list<string> $permissions
     */
    protected function ensurePermissionRecords(array $permissions, string $guard): void
    {
        $permissionModelClass = (string) config('permission.models.permission', \Spatie\Permission\Models\Permission::class);
        if ($permissionModelClass === '' || ! class_exists($permissionModelClass) || ! is_subclass_of($permissionModelClass, Model::class)) {
            return;
        }

        try {
            /** @var class-string<Model> $permissionModelClass */
            foreach ($permissions as $permission) {
                $permission = trim((string) $permission);
                if ($permission === '') {
                    continue;
                }

                $permissionModelClass::query()->firstOrCreate([
                    'name' => $permission,
                    'guard_name' => $guard,
                ]);
            }
        } catch (Throwable) {
            // ignore
        }
    }

    protected function classFromPhpFile(string $path): ?string
    {
        try {
            $contents = (string) File::get($path);
        } catch (Throwable) {
            return null;
        }

        $namespace = null;
        if (preg_match('/^\s*namespace\s+([^;]+);/m', $contents, $namespaceMatch)) {
            $namespace = trim((string) ($namespaceMatch[1] ?? ''));
        }

        $class = null;
        if (preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/m', $contents, $classMatch)) {
            $class = trim((string) ($classMatch[1] ?? ''));
        }

        if ($namespace !== null && $namespace !== '' && $class !== null && $class !== '') {
            return $namespace.'\\'.$class;
        }

        return null;
    }

    /**
     * @param array<int, string> $moduleCandidates
     */
    protected function resourceBelongsToModule(string $resourceClass, string $moduleKey, array $moduleCandidates): bool
    {
        $fromClass = $this->moduleKeyFromClass($resourceClass);
        if ($fromClass === $moduleKey) {
            return true;
        }

        if (is_subclass_of($resourceClass, Resource::class)) {
            try {
                $slug = trim((string) $resourceClass::slug(), '/');
                $firstSegment = trim((string) explode('/', $slug)[0]);
                $normalized = $this->normalizeModuleKey($firstSegment);
                if ($normalized !== '' && in_array($normalized, $moduleCandidates, true)) {
                    return true;
                }
            } catch (Throwable) {
                // ignore
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $moduleCandidates
     */
    protected function operationBelongsToModule(
        string $operationClass,
        string $uri,
        string $moduleKey,
        array $moduleCandidates
    ): bool {
        $fromClass = $this->moduleKeyFromClass($operationClass);
        if ($fromClass === $moduleKey) {
            return true;
        }

        $uri = trim($uri, '/');
        if ($uri === '') {
            return false;
        }

        $firstSegment = trim((string) explode('/', $uri)[0]);
        $normalized = $this->normalizeModuleKey($firstSegment);

        return $normalized !== '' && in_array($normalized, $moduleCandidates, true);
    }

    /**
     * @return list<string>
     */
    protected function moduleCandidates(string $moduleKey): array
    {
        $base = $this->normalizeModuleKey($moduleKey);
        $kebab = (string) Str::of($base)->replace('_', '-')->toString();
        $compact = (string) Str::of($base)->replace('_', '')->toString();

        $candidates = array_filter([
            $base,
            $this->normalizeModuleKey((string) Str::plural($base)),
            $this->normalizeModuleKey($kebab),
            $this->normalizeModuleKey((string) Str::plural($kebab)),
            $this->normalizeModuleKey($compact),
            $this->normalizeModuleKey((string) Str::plural($compact)),
        ], static fn (string $value): bool => $value !== '');

        return array_values(array_unique($candidates));
    }

    /**
     * @param list<string> $moduleCandidates
     * @return list<string>
     */
    protected function permissionsFromDatabaseFallback(array $moduleCandidates, string $guard): array
    {
        $permissionModelClass = (string) config('permission.models.permission', \Spatie\Permission\Models\Permission::class);
        if ($permissionModelClass === '' || ! class_exists($permissionModelClass) || ! is_subclass_of($permissionModelClass, Model::class)) {
            return [];
        }

        try {
            /** @var class-string<Model> $permissionModelClass */
            $query = $permissionModelClass::query()
                ->where('guard_name', $guard);

            $query->where(function ($builder) use ($moduleCandidates): void {
                foreach ($moduleCandidates as $candidate) {
                    $builder->orWhere('name', 'like', "resource.{$candidate}.%");
                    $builder->orWhere('name', 'like', "operation.{$candidate}%");
                }
            });

            return array_values(array_filter(array_map(
                static fn (mixed $value): string => trim((string) $value),
                $query->pluck('name')->all()
            ), static fn (string $value): bool => $value !== ''));
        } catch (Throwable) {
            return [];
        }
    }

    protected function moduleKeyFromClass(string $className): ?string
    {
        if (preg_match('/\\\\Modules\\\\Builtin\\\\([^\\\\]+)\\\\/i', $className, $matches) === 1) {
            return $this->normalizeModuleKey((string) ($matches[1] ?? ''));
        }

        if (preg_match('/\\\\Modules\\\\([^\\\\]+)\\\\/i', $className, $matches) === 1) {
            return $this->normalizeModuleKey((string) ($matches[1] ?? ''));
        }

        return null;
    }

    protected function normalizeModuleKey(string $value): string
    {
        return (string) Str::of(trim($value))
            ->replace(['-', ' '], '_')
            ->snake()
            ->toString();
    }

    protected function extractOperationUri(string $pattern): string
    {
        $trimmed = trim($pattern, '#^$');

        return preg_replace('/\(\[\^\/\]\+\)/', '{param}', $trimmed) ?? '';
    }

    protected function roleDisplayName(Model $role): string
    {
        $roleKey = trim((string) ($role->getAttribute('role_key') ?? ''));
        if ($roleKey !== '') {
            return $roleKey;
        }

        $nameLocale = trim((string) ($role->getAttribute('name_locale') ?? ''));
        if ($nameLocale !== '') {
            return $nameLocale;
        }

        $name = $role->getAttribute('name');
        if (is_string($name)) {
            $decoded = json_decode($name, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $locale = (string) app()->getLocale();
                $candidate = $decoded[$locale] ?? reset($decoded);

                return is_string($candidate) && trim($candidate) !== '' ? trim($candidate) : trim($name);
            }

            return trim($name);
        }

        if (is_array($name)) {
            $locale = (string) app()->getLocale();
            $candidate = $name[$locale] ?? reset($name);

            return is_string($candidate) ? trim($candidate) : '';
        }

        return (string) ($role->getAttribute('id') ?? 'role');
    }

    /**
     * @param class-string<Model> $roleModelClass
     */
    protected function roleColumnExists(string $roleModelClass, string $column): bool
    {
        try {
            /** @var Model $model */
            $model = new $roleModelClass();
            $table = (string) $model->getTable();

            if ($table === '' || ! Schema::hasTable($table)) {
                return false;
            }

            return Schema::hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }
}
