<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class UserAccessCommand extends CoreCommand
{
    protected $signature = 'svarium:user.access
        {--user= : ID lub adres e-mail użytkownika}
        {--action= : Akcja: add albo revoke}
        {--type= : Typ: role albo permission}
        {--guard= : Guard (np. web, api)}
        {--role=* : Klucze/ID ról do przypisania/odebrania}
        {--permission=* : Nazwy/ID permissionów do przypisania/odebrania}';

    protected $description = 'Przypisuje lub odbiera role/uprawnienia użytkownikowi';

    public function handle(): int
    {
        try {
            $userModelClass = $this->resolveUserModelClass();
            $roleModelClass = $this->resolveModelClass('permission.models.role', \Spatie\Permission\Models\Role::class);
            $permissionModelClass = $this->resolveModelClass('permission.models.permission', \Spatie\Permission\Models\Permission::class);

            $this->validatePrerequisites($userModelClass, $roleModelClass, $permissionModelClass);

            $action = $this->resolveAction();
            $type = $this->resolveType();
            $guard = $this->resolveGuard();
            $user = $this->resolveUser($userModelClass);

            if ($type === 'role') {
                [$selected, $labels] = $this->resolveRoleSelection($roleModelClass, $user, $guard, $action);
                if ($selected->isEmpty()) {
                    $this->warn($action === 'add'
                        ? 'Brak ról do przypisania.'
                        : 'Brak ról do odebrania.');

                    return self::SUCCESS;
                }

                $this->applyRoles($user, $selected, $action);
                $this->info($action === 'add'
                    ? 'Przypisano role użytkownikowi.'
                    : 'Odebrano role użytkownikowi.');
                $this->line('Role: '.implode(', ', $labels));
            } else {
                [$selected, $labels] = $this->resolvePermissionSelection($permissionModelClass, $user, $guard, $action);
                if ($selected->isEmpty()) {
                    $this->warn($action === 'add'
                        ? 'Brak uprawnień do przypisania.'
                        : 'Brak uprawnień do odebrania.');

                    return self::SUCCESS;
                }

                $this->applyPermissions($user, $selected, $action);
                $this->info($action === 'add'
                    ? 'Przypisano uprawnienia użytkownikowi.'
                    : 'Odebrano uprawnienia użytkownikowi.');
                $this->line('Uprawnienia: '.implode(', ', $labels));
            }

            if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            }

            $this->line('Użytkownik: '.$this->userDisplay($user));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    protected function resolveAction(): string
    {
        $action = strtolower(trim((string) $this->option('action')));
        if (in_array($action, ['add', 'revoke'], true)) {
            return $action;
        }

        if (! $this->input->isInteractive()) {
            return 'add';
        }

        return (string) select(
            label: 'Chcesz dodać czy odebrać dostęp?',
            options: [
                'add' => 'Dodać',
                'revoke' => 'Odebrać',
            ],
            default: 'add'
        );
    }

    protected function resolveType(): string
    {
        $type = strtolower(trim((string) $this->option('type')));
        if (in_array($type, ['role', 'permission'], true)) {
            return $type;
        }

        if (! $this->input->isInteractive()) {
            return 'role';
        }

        return (string) select(
            label: 'Przypisać rolę czy uprawnienie użytkownikowi?',
            options: [
                'role' => 'Rola',
                'permission' => 'Uprawnienie',
            ],
            default: 'role'
        );
    }

    protected function resolveGuard(): string
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

        $guards = array_keys((array) config('auth.guards', []));
        if ($guards === []) {
            return $defaultGuard;
        }

        if (! in_array($defaultGuard, $guards, true)) {
            $defaultGuard = (string) ($guards[0] ?? 'web');
        }

        return (string) select(
            label: 'Wybierz guard',
            options: array_combine($guards, $guards),
            default: $defaultGuard
        );
    }

    /**
     * @param class-string<Model> $userModelClass
     */
    protected function resolveUser(string $userModelClass): Model
    {
        $identifier = trim((string) $this->option('user'));

        while (true) {
            if ($identifier === '') {
                if (! $this->input->isInteractive()) {
                    throw new RuntimeException('Podaj użytkownika przez --user={id|email}.');
                }

                $identifier = trim((string) text('Podaj ID lub adres e-mail użytkownika'));
                continue;
            }

            $user = $this->findUserByIdentifier($userModelClass, $identifier);
            if ($user instanceof Model) {
                return $user;
            }

            if (! $this->input->isInteractive()) {
                throw new RuntimeException('Użytkownik nie istnieje.');
            }

            $this->error('Użytkownik nie istnieje.');
            $identifier = '';
        }
    }

    /**
     * @param class-string<Model> $userModelClass
     */
    protected function findUserByIdentifier(string $userModelClass, string $identifier): ?Model
    {
        /** @var Model $prototype */
        $prototype = new $userModelClass();
        $table = $prototype->getTable();
        $keyName = $prototype->getKeyName();

        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        if (is_numeric($identifier)) {
            $found = $userModelClass::query()
                ->where($keyName, $identifier)
                ->first();

            if ($found instanceof Model) {
                return $found;
            }
        }

        if (Schema::hasColumn($table, 'email')) {
            $found = $userModelClass::query()
                ->where('email', strtolower($identifier))
                ->first();

            if ($found instanceof Model) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param class-string<Model> $roleModelClass
     * @return array{0: Collection<int, Model>, 1: array<int, string>}
     */
    protected function resolveRoleSelection(string $roleModelClass, Model $user, string $guard, string $action): array
    {
        $allRoles = $roleModelClass::query()
            ->where('guard_name', $guard)
            ->orderBy('id')
            ->get();

        if ($allRoles->isEmpty()) {
            throw new RuntimeException("Brak ról dla guard [{$guard}].");
        }

        $assignedRoleKeys = $this->userRoleKeys($user);
        $options = [];
        $roleByOptionKey = [];

        foreach ($allRoles as $role) {
            $optionKey = (string) ($role->getKey() ?? '');
            if ($optionKey === '') {
                continue;
            }

            $roleKey = strtolower(trim((string) ($role->getAttribute('role_key') ?? '')));
            $nameLocale = strtolower(trim((string) ($role->getAttribute('name_locale') ?? '')));
            $display = $this->roleDisplayName($role).' [id:'.$optionKey.']';
            $isAssigned = ($roleKey !== '' && isset($assignedRoleKeys[$roleKey]))
                || ($nameLocale !== '' && isset($assignedRoleKeys[$nameLocale]))
                || isset($assignedRoleKeys[strtolower($this->roleDisplayName($role))]);

            if ($action === 'add' && $isAssigned) {
                continue;
            }

            if ($action === 'revoke' && ! $isAssigned) {
                continue;
            }

            $options[$optionKey] = $display;
            $roleByOptionKey[$optionKey] = $role;
        }

        if ($options === []) {
            return [collect(), []];
        }

        $selectedKeys = $this->resolveSelectionKeys('Wybierz role', $options, (array) $this->option('role'));

        $selected = collect();
        $labels = [];

        foreach ($selectedKeys as $selectedKey) {
            if (! isset($roleByOptionKey[$selectedKey])) {
                continue;
            }

            /** @var Model $role */
            $role = $roleByOptionKey[$selectedKey];
            $selected->push($role);
            $labels[] = $this->roleDisplayName($role);
        }

        return [$selected, array_values(array_unique($labels))];
    }

    /**
     * @param class-string<Model> $permissionModelClass
     * @return array{0: Collection<int, Model>, 1: array<int, string>}
     */
    protected function resolvePermissionSelection(string $permissionModelClass, Model $user, string $guard, string $action): array
    {
        $allPermissions = $permissionModelClass::query()
            ->where('guard_name', $guard)
            ->orderBy('name')
            ->get();

        if ($allPermissions->isEmpty()) {
            throw new RuntimeException("Brak uprawnień dla guard [{$guard}]. Uruchom: php artisan svarium:permission.sync");
        }

        $directPermissionNames = $this->userDirectPermissionNames($user);
        $groupedPermissions = [];
        $permissionByName = [];

        foreach ($allPermissions as $permission) {
            $name = trim((string) ($permission->getAttribute('name') ?? ''));
            if ($name === '') {
                continue;
            }

            $permissionByName[strtolower($name)] = $permission;

            $groupKey = $this->permissionGroupKey($name);
            if ($groupKey === null) {
                continue;
            }

            $groupedPermissions[$groupKey] ??= [];
            $groupedPermissions[$groupKey][] = $permission;
        }

        $options = [];
        $permissionByOptionKey = [];
        $groupByOptionKey = [];

        foreach ($groupedPermissions as $groupKey => $permissionsInGroup) {
            $total = count($permissionsInGroup);
            if ($total === 0) {
                continue;
            }

            $assigned = 0;
            foreach ($permissionsInGroup as $permission) {
                $permissionName = strtolower(trim((string) ($permission->getAttribute('name') ?? '')));
                if ($permissionName !== '' && isset($directPermissionNames[$permissionName])) {
                    $assigned++;
                }
            }

            $wildcardKey = strtolower($groupKey);
            $wildcardAssigned = isset($directPermissionNames[$wildcardKey]);

            if ($action === 'add' && ($wildcardAssigned || $assigned >= $total)) {
                continue;
            }

            if ($action === 'revoke' && ! $wildcardAssigned && $assigned === 0) {
                continue;
            }

            $options[$groupKey] = sprintf('%s [%d]', $groupKey, $total);
            $groupByOptionKey[$groupKey] = [
                'permissions' => $permissionsInGroup,
                'wildcard_assigned' => $wildcardAssigned,
            ];
        }

        foreach ($allPermissions as $permission) {
            $optionKey = (string) ($permission->getKey() ?? '');
            if ($optionKey === '') {
                continue;
            }

            $name = trim((string) ($permission->getAttribute('name') ?? ''));
            if ($name === '') {
                continue;
            }

            $isAssigned = isset($directPermissionNames[strtolower($name)]);

            if ($action === 'add' && $isAssigned) {
                continue;
            }

            if ($action === 'revoke' && ! $isAssigned) {
                continue;
            }

            $options[$optionKey] = $name;
            $permissionByOptionKey[$optionKey] = $permission;
        }

        if ($options === []) {
            return [collect(), []];
        }

        $selectedKeys = $this->resolveSelectionKeys('Wybierz uprawnienia', $options, (array) $this->option('permission'));

        $selected = collect();
        $labels = [];
        $selectedMap = [];

        foreach ($selectedKeys as $selectedKey) {
            if (isset($groupByOptionKey[$selectedKey])) {
                $group = $groupByOptionKey[$selectedKey];
                $groupPermissions = is_array($group['permissions'] ?? null)
                    ? $group['permissions']
                    : [];

                if ($action === 'add') {
                    $wildcardPermission = $this->resolveWildcardPermissionModel(
                        $permissionModelClass,
                        (string) $selectedKey,
                        $guard,
                        true,
                        $permissionByName
                    );

                    if ($wildcardPermission instanceof Model) {
                        $selectedKeyMap = $this->selectedModelMapKey($wildcardPermission);
                        if ($selectedKeyMap !== '' && ! isset($selectedMap[$selectedKeyMap])) {
                            $selected->push($wildcardPermission);
                            $selectedMap[$selectedKeyMap] = true;
                            $labels[] = (string) $wildcardPermission->getAttribute('name');
                        }
                    }

                    continue;
                }

                $wildcardPermission = $this->resolveWildcardPermissionModel(
                    $permissionModelClass,
                    (string) $selectedKey,
                    $guard,
                    false,
                    $permissionByName
                );

                if ($wildcardPermission instanceof Model) {
                    $selectedKeyMap = $this->selectedModelMapKey($wildcardPermission);
                    if ($selectedKeyMap !== '' && ! isset($selectedMap[$selectedKeyMap])) {
                        $selected->push($wildcardPermission);
                        $selectedMap[$selectedKeyMap] = true;
                        $labels[] = (string) $wildcardPermission->getAttribute('name');
                    }
                }

                foreach ($groupPermissions as $permissionFromGroup) {
                    if (! $permissionFromGroup instanceof Model) {
                        continue;
                    }

                    $selectedKeyMap = $this->selectedModelMapKey($permissionFromGroup);
                    if ($selectedKeyMap === '' || isset($selectedMap[$selectedKeyMap])) {
                        continue;
                    }

                    $selected->push($permissionFromGroup);
                    $selectedMap[$selectedKeyMap] = true;
                    $labels[] = (string) $permissionFromGroup->getAttribute('name');
                }

                continue;
            }

            if (! isset($permissionByOptionKey[$selectedKey])) {
                continue;
            }

            /** @var Model $permission */
            $permission = $permissionByOptionKey[$selectedKey];
            $selectedKeyMap = $this->selectedModelMapKey($permission);
            if ($selectedKeyMap === '' || isset($selectedMap[$selectedKeyMap])) {
                continue;
            }

            $selected->push($permission);
            $selectedMap[$selectedKeyMap] = true;
            $labels[] = (string) $permission->getAttribute('name');
        }

        return [$selected, array_values(array_unique($labels))];
    }

    protected function permissionGroupKey(string $permissionName): ?string
    {
        $permissionName = trim($permissionName);
        if ($permissionName === '') {
            return null;
        }

        $segments = explode('.', $permissionName);
        if (count($segments) < 3) {
            return null;
        }

        $scope = trim((string) ($segments[0] ?? ''));
        $module = trim((string) ($segments[1] ?? ''));
        if (! in_array($scope, ['resource', 'operation'], true) || $module === '') {
            return null;
        }

        return $scope.'.'.$module.'.*';
    }

    /**
     * @param class-string<Model> $permissionModelClass
     * @param array<string, Model> $permissionByName
     */
    protected function resolveWildcardPermissionModel(
        string $permissionModelClass,
        string $permissionName,
        string $guard,
        bool $createIfMissing,
        array &$permissionByName
    ): ?Model {
        $permissionName = trim($permissionName);
        if ($permissionName === '') {
            return null;
        }

        $normalized = strtolower($permissionName);
        if (isset($permissionByName[$normalized])) {
            return $permissionByName[$normalized];
        }

        $existing = $permissionModelClass::query()
            ->where('guard_name', $guard)
            ->where('name', $permissionName)
            ->first();

        if ($existing instanceof Model) {
            $permissionByName[$normalized] = $existing;

            return $existing;
        }

        if (! $createIfMissing) {
            return null;
        }

        $created = $permissionModelClass::query()->firstOrCreate([
            'name' => $permissionName,
            'guard_name' => $guard,
        ]);

        if (! $created instanceof Model) {
            return null;
        }

        $permissionByName[$normalized] = $created;

        return $created;
    }

    protected function selectedModelMapKey(Model $model): string
    {
        $id = (string) $model->getKey();
        if ($id === '') {
            return '';
        }

        return ltrim($model::class, '\\').':'.$id;
    }

    /**
     * @param array<string, string> $options
     * @param array<int, mixed> $inputValues
     * @return list<string>
     */
    protected function resolveSelectionKeys(string $label, array $options, array $inputValues = []): array
    {
        $normalizedInput = array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $inputValues
        ), static fn (string $value): bool => $value !== ''));

        if ($normalizedInput !== []) {
            $byLabel = array_flip($options);
            $resolved = [];

            foreach ($normalizedInput as $value) {
                if (array_key_exists($value, $options)) {
                    $resolved[] = $value;
                    continue;
                }

                if (isset($byLabel[$value])) {
                    $resolved[] = (string) $byLabel[$value];
                }
            }

            return array_values(array_unique(array_filter($resolved, static fn (string $key): bool => isset($options[$key]))));
        }

        if (! $this->input->isInteractive()) {
            return [];
        }

        $selected = multiselect(
            $label,
            $options
        );

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            (array) $selected
        ), static fn (string $value): bool => $value !== '' && isset($options[$value]))));
    }

    /**
     * @param Collection<int, Model> $selectedRoles
     */
    protected function applyRoles(Model $user, Collection $selectedRoles, string $action): void
    {
        if ($selectedRoles->isEmpty()) {
            return;
        }

        if ($action === 'add') {
            if (method_exists($user, 'assignRole')) {
                $user->assignRole($selectedRoles->all());
                return;
            }

            throw new RuntimeException('Model użytkownika nie wspiera assignRole().');
        }

        if (method_exists($user, 'removeRole')) {
            foreach ($selectedRoles as $role) {
                $user->removeRole($role);
            }

            return;
        }

        throw new RuntimeException('Model użytkownika nie wspiera removeRole().');
    }

    /**
     * @param Collection<int, Model> $selectedPermissions
     */
    protected function applyPermissions(Model $user, Collection $selectedPermissions, string $action): void
    {
        if ($selectedPermissions->isEmpty()) {
            return;
        }

        if ($action === 'add') {
            if (method_exists($user, 'givePermissionTo')) {
                $user->givePermissionTo($selectedPermissions->all());
                return;
            }

            throw new RuntimeException('Model użytkownika nie wspiera givePermissionTo().');
        }

        if (method_exists($user, 'revokePermissionTo')) {
            foreach ($selectedPermissions as $permission) {
                $user->revokePermissionTo($permission);
            }

            return;
        }

        throw new RuntimeException('Model użytkownika nie wspiera revokePermissionTo().');
    }

    /**
     * @return array<string, true>
     */
    protected function userRoleKeys(Model $user): array
    {
        $values = [];

        try {
            if (method_exists($user, 'roles')) {
                foreach ($user->roles as $role) {
                    $roleKey = strtolower(trim((string) ($role->getAttribute('role_key') ?? '')));
                    if ($roleKey !== '') {
                        $values[$roleKey] = true;
                    }

                    $nameLocale = strtolower(trim((string) ($role->getAttribute('name_locale') ?? '')));
                    if ($nameLocale !== '') {
                        $values[$nameLocale] = true;
                    }

                    $display = strtolower(trim((string) $this->roleDisplayName($role)));
                    if ($display !== '') {
                        $values[$display] = true;
                    }
                }
            }
        } catch (Throwable) {
            return [];
        }

        return $values;
    }

    /**
     * @return array<string, true>
     */
    protected function userDirectPermissionNames(Model $user): array
    {
        $values = [];

        try {
            if (method_exists($user, 'getDirectPermissions')) {
                foreach ($user->getDirectPermissions() as $permission) {
                    $name = strtolower(trim((string) ($permission->getAttribute('name') ?? '')));
                    if ($name !== '') {
                        $values[$name] = true;
                    }
                }

                return $values;
            }

            if (method_exists($user, 'permissions')) {
                foreach ($user->permissions as $permission) {
                    $name = strtolower(trim((string) ($permission->getAttribute('name') ?? '')));
                    if ($name !== '') {
                        $values[$name] = true;
                    }
                }
            }
        } catch (Throwable) {
            return [];
        }

        return $values;
    }

    /**
     * @param class-string<Model> $userModelClass
     * @param class-string<Model> $roleModelClass
     * @param class-string<Model> $permissionModelClass
     */
    protected function validatePrerequisites(string $userModelClass, string $roleModelClass, string $permissionModelClass): void
    {
        $errors = [];

        /** @var Model $userPrototype */
        $userPrototype = new $userModelClass();
        if (! Schema::hasTable($userPrototype->getTable())) {
            $errors[] = "Tabela użytkowników [{$userPrototype->getTable()}] nie istnieje.";
        }

        /** @var Model $rolePrototype */
        $rolePrototype = new $roleModelClass();
        if (! Schema::hasTable($rolePrototype->getTable())) {
            $errors[] = "Tabela ról [{$rolePrototype->getTable()}] nie istnieje.";
        }

        /** @var Model $permissionPrototype */
        $permissionPrototype = new $permissionModelClass();
        if (! Schema::hasTable($permissionPrototype->getTable())) {
            $errors[] = "Tabela uprawnień [{$permissionPrototype->getTable()}] nie istnieje.";
        }

        if ($errors !== []) {
            throw new RuntimeException(implode(PHP_EOL, array_values(array_unique($errors))));
        }
    }

    /**
     * @return class-string<Model>
     */
    protected function resolveUserModelClass(): string
    {
        try {
            if (function_exists('get_model')) {
                $model = get_model('user');
                if (is_string($model) && trim($model) !== '' && class_exists($model) && is_subclass_of($model, Model::class)) {
                    return $model;
                }
            }
        } catch (Throwable) {
            // fallback below
        }

        $trackingUserModel = config('upsoftware.tracking.user_model', config('upsoftware.user_model'));
        if (is_string($trackingUserModel) && trim($trackingUserModel) !== '' && class_exists($trackingUserModel) && is_subclass_of($trackingUserModel, Model::class)) {
            return $trackingUserModel;
        }

        return $this->resolveModelClass('upsoftware.models.user', \Upsoftware\Svarium\Models\User::class);
    }

    /**
     * @param string|class-string<Model>|null $fallback
     * @return class-string<Model>
     */
    protected function resolveModelClass(string $configKey, string|null $fallback = null): string
    {
        $model = config($configKey, $fallback);

        if (! is_string($model) || trim($model) === '' || ! class_exists($model)) {
            throw new RuntimeException("Model pod kluczem [{$configKey}] nie istnieje.");
        }

        if (! is_subclass_of($model, Model::class)) {
            throw new RuntimeException("Model [{$model}] nie dziedziczy po Eloquent Model.");
        }

        return $model;
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

    protected function userDisplay(Model $user): string
    {
        $id = (string) ($user->getKey() ?? '');
        $email = trim((string) ($user->getAttribute('email') ?? ''));
        $name = trim((string) ($user->getAttribute('name') ?? ''));

        if ($name !== '' && $email !== '') {
            return "{$name} <{$email}> [id:{$id}]";
        }

        if ($email !== '') {
            return "{$email} [id:{$id}]";
        }

        if ($name !== '') {
            return "{$name} [id:{$id}]";
        }

        return "id:{$id}";
    }
}
