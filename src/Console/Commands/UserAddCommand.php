<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class UserAddCommand extends CoreCommand
{
    protected bool $generatedPassword = false;

    protected $signature = 'svarium:user.add
        {--name= : Nazwa użytkownika}
        {--email= : Adres e-mail}
        {--password= : Hasło użytkownika}
        {--random-password : Wygeneruj losowe hasło}
        {--role= : ID roli}
        {--tenant=* : ID tenantów (wiele)}';

    protected $description = 'Dodaje użytkownika, przypisuje rolę i tenanty';

    public function handle(): int
    {
        try {
            $userModelClass = $this->resolveModelClass('upsoftware.models.user', config('auth.providers.users.model', \App\Models\User::class));
            $roleModelClass = $this->resolveModelClass('permission.models.role', \Spatie\Permission\Models\Role::class);
            $tenantModelClass = $this->resolveOptionalModelClass('upsoftware.models.tenant');
            $this->validatePrerequisitesBeforePrompt($userModelClass, $roleModelClass, $tenantModelClass);

            $name = $this->resolveName();
            $email = $this->resolveEmail($userModelClass);
            $password = $this->resolvePassword();
            $guard = $this->resolveGuardNameForUserModel($userModelClass, (string) config('auth.defaults.guard', 'web'));

            $role = $this->resolveRole($roleModelClass, $guard);
            $tenantIds = [];

            if ($this->shouldAssignTenants()) {
                $tenantIds = $this->resolveTenantIds($tenantModelClass);
            } elseif ((array) $this->option('tenant') !== []) {
                $this->warn('Tenancy jest wyłączone, więc parametr --tenant został pominięty.');
            }

            $user = $this->createOrUpdateUser($userModelClass, $name, $email, $password);

            $this->assignRoleToUser($user, $role, $tenantIds);
            $this->syncUserTenants($user, $tenantIds);

            $this->info('Użytkownik został zapisany.');
            $this->line('ID: '.(string) $user->getKey());
            $this->line('E-mail: '.$email);
            $this->line('Rola: '.$this->resolveRoleDisplayName($role));
            $this->line('Tenanty: '.($tenantIds === [] ? '-' : implode(', ', array_map(static fn ($id) => (string) $id, $tenantIds))));

            if ($this->generatedPassword) {
                $this->line('Hasło: '.$password);
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    protected function shouldAssignTenants(): bool
    {
        return (bool) config('upsoftware.tenancy.enabled', config('tenancy.enabled', false));
    }

    protected function validatePrerequisitesBeforePrompt(
        string $userModelClass,
        string $roleModelClass,
        ?string $tenantModelClass
    ): void {
        $errors = [];

        $errors = array_merge($errors, $this->validateUserStorage($userModelClass));
        $errors = array_merge($errors, $this->validateRolesStorage($roleModelClass));

        if ($this->shouldAssignTenants()) {
            $errors = array_merge($errors, $this->validateTenantsStorage($tenantModelClass));
        }

        if ($errors !== []) {
            throw new RuntimeException(implode(PHP_EOL, array_values(array_unique($errors))));
        }
    }

    /**
     * @return array<int, string>
     */
    protected function validateUserStorage(string $userModelClass): array
    {
        $errors = [];

        /** @var Model $prototype */
        $prototype = new $userModelClass();
        $table = $prototype->getTable();

        if (! Schema::hasTable($table)) {
            $errors[] = "Tabela użytkownika [{$table}] nie istnieje.";
            return $errors;
        }

        if (! Schema::hasColumn($table, 'email')) {
            $errors[] = "Tabela [{$table}] nie ma kolumny email.";
        }

        if (! Schema::hasColumn($table, 'password')) {
            $errors[] = "Tabela [{$table}] nie ma kolumny password.";
        }

        return $errors;
    }

    /**
     * @return array<int, string>
     */
    protected function validateRolesStorage(string $roleModelClass): array
    {
        $errors = [];

        /** @var Model $prototype */
        $prototype = new $roleModelClass();
        $table = $prototype->getTable();

        if (! Schema::hasTable($table)) {
            $errors[] = "Tabela [{$table}] nie istnieje. Najpierw uruchom migracje ról.";
            return $errors;
        }

        try {
            if (! $roleModelClass::query()->exists()) {
                $errors[] = 'Brak ról w tabeli roles. Najpierw utwórz role.';
            }
        } catch (Throwable $exception) {
            $errors[] = 'Nie udało się odczytać ról: '.$exception->getMessage();
        }

        return $errors;
    }

    /**
     * @param  class-string<Model>|null  $tenantModelClass
     * @return array<int, string>
     */
    protected function validateTenantsStorage(?string $tenantModelClass): array
    {
        $errors = [];

        if ($tenantModelClass === null) {
            $errors[] = 'Model tenanta nie jest skonfigurowany. Nie można przypisać tenanta.';
            return $errors;
        }

        if (! Schema::hasTable('tenants')) {
            $errors[] = 'Tabela tenants nie istnieje. Utwórz tenanty przed dodaniem użytkownika.';
            return $errors;
        }

        try {
            if (! $tenantModelClass::query()->exists()) {
                $errors[] = 'Brak tenantów. Najpierw utwórz tenant.';
            }
        } catch (Throwable $exception) {
            $errors[] = 'Nie udało się odczytać tenantów: '.$exception->getMessage();
        }

        return $errors;
    }

    protected function resolveName(): string
    {
        $name = trim((string) $this->option('name'));

        if ($name !== '') {
            return $name;
        }

        while ($name === '') {
            $name = trim((string) text('Nazwa użytkownika', 'np. Jan Kowalski'));
        }

        return $name;
    }

    protected function resolveEmail(string $userModelClass): string
    {
        $email = trim((string) $this->option('email'));

        while (true) {
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                if ($email !== '') {
                    $this->warn('Podaj poprawny adres e-mail.');
                }

                if (! $this->input->isInteractive()) {
                    throw new RuntimeException('Podano niepoprawny adres e-mail.');
                }

                $email = trim((string) text('Adres e-mail', 'np. user@example.com'));
                continue;
            }

            $email = Str::lower($email);

            if ($this->emailExists($userModelClass, $email)) {
                $this->error('Użytkownik z tym adresem e-mail już istnieje.');

                if (! $this->input->isInteractive()) {
                    throw new RuntimeException('Użytkownik z tym adresem e-mail już istnieje.');
                }

                $email = '';
                continue;
            }

            return $email;
        }
    }

    protected function emailExists(string $userModelClass, string $email): bool
    {
        try {
            return $userModelClass::query()
                ->where('email', Str::lower(trim($email)))
                ->exists();
        } catch (Throwable $exception) {
            throw new RuntimeException('Nie udało się zweryfikować adresu e-mail: '.$exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @param  class-string<Model>  $userModelClass
     */
    protected function createOrUpdateUser(string $userModelClass, string $name, string $email, string $password): Model
    {
        /** @var Model $prototype */
        $prototype = new $userModelClass();
        $table = $prototype->getTable();

        if (! Schema::hasTable($table)) {
            throw new RuntimeException("Tabela użytkownika [{$table}] nie istnieje.");
        }

        if (! Schema::hasColumn($table, 'email')) {
            throw new RuntimeException("Tabela [{$table}] nie ma kolumny email.");
        }

        if (! Schema::hasColumn($table, 'password')) {
            throw new RuntimeException("Tabela [{$table}] nie ma kolumny password.");
        }

        /** @var Model|null $existing */
        $existing = $userModelClass::query()->where('email', $email)->first();

        if ($existing !== null) {
            throw new RuntimeException('Użytkownik z tym adresem e-mail już istnieje.');
        }

        /** @var Model $user */
        $user = new $userModelClass();

        $user->setAttribute('email', $email);
        $user->setAttribute('password', Hash::make($password));

        if (Schema::hasColumn($table, 'name')) {
            $user->setAttribute('name', $name);
        }

        if (Schema::hasColumn($table, 'email_verified_at') && ! $user->getAttribute('email_verified_at')) {
            $user->setAttribute('email_verified_at', now());
        }

        $user->save();

        return $user;
    }
    protected function resolvePassword(): string
    {
        $this->generatedPassword = false;
        $optionPassword = (string) $this->option('password');

        if (trim($optionPassword) !== '') {
            if (Str::length($optionPassword) < 8) {
                throw new RuntimeException('Hasło musi mieć minimum 8 znaków.');
            }

            return $optionPassword;
        }

        if ((bool) $this->option('random-password')) {
            $this->generatedPassword = true;
            return Str::random(20);
        }

        if (! $this->input->isInteractive()) {
            throw new RuntimeException('Podaj hasło przez --password (min. 8 znaków) lub użyj --random-password.');
        }

        while (true) {
            $password = (string) text('Wpisz hasło lub zostaw puste aby wygenerować losowe');

            if (trim($password) === '' || Str::length($password) < 8) {
                $this->error('Hasło za krótkie (minimum 8 znaków).');
                continue;
            }

            return $password;
        }
    }

    protected function resolveRole(string $roleModelClass, string $guard): object
    {
        $roleOption = trim((string) $this->option('role'));
        $roleNameIsJson = $this->isRoleNameJsonColumn($roleModelClass);

        $roles = $roleModelClass::query()->get();

        if ($roles->isEmpty()) {
            throw new RuntimeException('Brak ról w tabeli roles. Najpierw utwórz role.');
        }

        if ($roleOption !== '') {
            $selected = $roles->first(function ($role) use ($roleOption): bool {
                return (string) ($role->id ?? '') === $roleOption;
            });

            if ($selected !== null) {
                return $selected;
            }

            if (! $this->input->isInteractive()) {
                throw new RuntimeException('Nieprawidłowa rola. Podaj poprawne --role={id}.');
            }

            $this->error('Nie wybrano poprawnej roli. Wybierz rolę ponownie.');
        }

        $options = [];
        foreach ($roles as $role) {
            $roleId = (string) ($role->id ?? '');
            if ($roleId === '') {
                continue;
            }

            $label = $this->resolveRoleDisplayName($role, $roleNameIsJson);
            $roleGuard = (string) ($role->guard_name ?? '');

            if ($roleGuard !== '') {
                $label .= " [{$roleGuard}]";
            }

            if ($roleGuard === $guard && $guard !== '') {
                $label .= ' *';
            }

            $options[$roleId] = $label;
        }

        if ($options === []) {
            throw new RuntimeException('Nie udało się przygotować listy ról.');
        }

        if (! $this->input->isInteractive()) {
            throw new RuntimeException('Rola jest wymagana. Użyj --role={id}.');
        }

        while (true) {
            $selectedRoleId = (string) select(
                'Wybierz rolę',
                $options
            );

            $selectedRole = $roles->first(function ($role) use ($selectedRoleId): bool {
                return (string) ($role->id ?? '') === $selectedRoleId;
            });

            if ($selectedRole !== null) {
                return $selectedRole;
            }

            $this->error('Rola jest wymagana. Wybierz rolę.');
        }
    }

    /**
     * @param  class-string<Model>|null  $tenantModelClass
     * @return array<int, string|int>
     */
    protected function resolveTenantIds(?string $tenantModelClass): array
    {
        if ($tenantModelClass === null) {
            throw new RuntimeException('Model tenanta nie jest skonfigurowany. Nie można przypisać tenanta.');
        }

        if (! Schema::hasTable('tenants')) {
            throw new RuntimeException('Tabela tenants nie istnieje. Utwórz tenanty przed dodaniem użytkownika.');
        }

        $tenantOptionIds = array_values(array_filter(array_map(
            static fn ($id) => trim((string) $id),
            (array) $this->option('tenant')
        ), static fn (string $id) => $id !== ''));

        $tenants = $tenantModelClass::query()
            ->orderBy('name')
            ->get();

        if ($tenants->isEmpty()) {
            throw new RuntimeException('Brak tenantów do wyboru. Najpierw utwórz tenant.');
        }

        $options = [];
        $idsMap = [];

        foreach ($tenants as $tenant) {
            $tenantId = $tenant->getKey();
            if ($tenantId === null || $tenantId === '') {
                continue;
            }

            $idKey = (string) $tenantId;
            $name = trim((string) ($tenant->name ?? $idKey));
            $status = (bool) ($tenant->status ?? true);

            $options[$idKey] = $name.' ('.$idKey.')'.($status ? '' : ' [inactive]');
            $idsMap[$idKey] = $tenantId;
        }

        if ($options === []) {
            throw new RuntimeException('Nie udało się przygotować listy tenantów.');
        }

        if ($tenantOptionIds !== []) {
            $resolved = [];

            foreach ($tenantOptionIds as $rawId) {
                if (array_key_exists($rawId, $idsMap)) {
                    $resolved[] = $idsMap[$rawId];
                }
            }

            $resolved = array_values(array_unique($resolved, SORT_REGULAR));

            if ($resolved !== []) {
                return $resolved;
            }

            if (! $this->input->isInteractive()) {
                throw new RuntimeException('Tenant jest wymagany. Podaj poprawne --tenant={id} (wiele razy dla multi).');
            }

            $this->error('Nie wybrano poprawnego tenanta. Wybierz tenant ponownie.');
        }

        if (! $this->input->isInteractive()) {
            throw new RuntimeException('Tenant jest wymagany. Użyj --tenant={id} (wiele razy dla multi).');
        }

        while (true) {
            $selected = multiselect(
                'Wybierz tenanty (może być wiele)',
                $options,
                []
            );

            $resolved = [];
            foreach ((array) $selected as $selectedId) {
                $selectedKey = (string) $selectedId;
                if (! array_key_exists($selectedKey, $idsMap)) {
                    continue;
                }

                $resolved[] = $idsMap[$selectedKey];
            }

            $resolved = array_values(array_unique($resolved, SORT_REGULAR));
            if ($resolved !== []) {
                return $resolved;
            }

            $this->error('Tenant jest wymagany. Wybierz przynajmniej jeden tenant.');
        }
    }

    /**
     * @param  array<int, string|int>  $tenantIds
     */
    protected function assignRoleToUser(Model $user, object $role, array $tenantIds): void
    {
        $table = (string) config('permission.table_names.model_has_roles', 'model_has_roles');

        if (! Schema::hasTable($table)) {
            throw new RuntimeException("Brak tabeli [{$table}] do przypisania roli.");
        }

        $modelKeyColumn = (string) config('permission.column_names.model_morph_key', 'model_id');
        $modelType = ltrim($user::class, '\\');
        $userKey = $user->getKey();
        $roleId = $role->id ?? null;

        if ($userKey === null || $roleId === null) {
            throw new RuntimeException('Brak klucza użytkownika lub roli.');
        }

        $connection = $this->resolveConnectionFromConfiguredModel((string) config('upsoftware.models.model_has_role', \Upsoftware\Svarium\Models\ModelHasRole::class));
        $query = is_string($connection) && $connection !== ''
            ? DB::connection($connection)->table($table)
            : DB::table($table);

        $query
            ->where('model_type', $modelType)
            ->where($modelKeyColumn, $userKey)
            ->delete();

        $hasTenantColumn = Schema::hasColumn($table, 'tenant_id');
        $statusColumn = Schema::hasColumn($table, 'status');

        if (! $hasTenantColumn || $tenantIds === []) {
            $this->insertRolePivotRow($query, $modelKeyColumn, $modelType, $userKey, $roleId, null, $statusColumn, $table);
            $this->forgetPermissionCache();

            return;
        }

        try {
            foreach ($tenantIds as $tenantId) {
                $this->insertRolePivotRow($query, $modelKeyColumn, $modelType, $userKey, $roleId, $tenantId, $statusColumn, $table);
            }
        } catch (Throwable $exception) {
            if (! $this->isDuplicateModelHasRolesException($exception)) {
                throw $exception;
            }

            // Fallback for installations with Spatie default primary key
            // (role_id + model_id + model_type) that does not include tenant_id.
            $query
                ->where('model_type', $modelType)
                ->where($modelKeyColumn, $userKey)
                ->delete();

            $this->insertRolePivotRow($query, $modelKeyColumn, $modelType, $userKey, $roleId, null, $statusColumn, $table);
            $this->warn('Tabela model_has_roles nie wspiera wielu tenantów dla tej samej roli (PK bez tenant_id). Zapisano rolę globalnie, a przypisanie tenantów realizuje model_has_tenants.');
        }

        $this->forgetPermissionCache();
    }

    /**
     * @param  array<int, string|int>  $tenantIds
     */
    protected function syncUserTenants(Model $user, array $tenantIds): void
    {
        $table = (string) config('upsoftware.tenancy.column.model_maps.tenants.table', 'model_has_tenants');
        if (! Schema::hasTable($table)) {
            return;
        }

        $modelType = ltrim($user::class, '\\');
        $modelId = $user->getKey();
        if ($modelId === null) {
            return;
        }

        $modelHasTenantClass = (string) config('upsoftware.models.model_has_tenant', \Upsoftware\Svarium\Models\ModelHasTenant::class);
        $connection = $this->resolveConnectionFromConfiguredModel($modelHasTenantClass);

        $query = is_string($connection) && $connection !== ''
            ? DB::connection($connection)->table($table)
            : DB::table($table);

        $query
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->delete();

        foreach ($tenantIds as $tenantId) {
            $query->updateOrInsert([
                'tenant_id' => $this->normalizeTenantIdForColumn($table, 'tenant_id', $tenantId),
                'model_type' => $modelType,
                'model_id' => $modelId,
            ], []);
        }
    }

    protected function insertRolePivotRow(
        \Illuminate\Database\Query\Builder $query,
        string $modelKeyColumn,
        string $modelType,
        mixed $modelId,
        mixed $roleId,
        mixed $tenantId,
        bool $statusColumn,
        string $table
    ): void {
        $attributes = [
            'role_id' => $roleId,
            'model_type' => $modelType,
            $modelKeyColumn => $modelId,
        ];

        if (Schema::hasColumn($table, 'tenant_id')) {
            $attributes['tenant_id'] = $tenantId === null
                ? null
                : $this->normalizeTenantIdForColumn($table, 'tenant_id', $tenantId);
        }

        $values = [];
        if ($statusColumn) {
            $values['status'] = 1;
        }

        $query->updateOrInsert($attributes, $values);
    }

    protected function normalizeTenantIdForColumn(string $table, string $column, mixed $tenantId): mixed
    {
        if (! Schema::hasColumn($table, $column)) {
            return $tenantId;
        }

        $type = strtolower((string) Schema::getColumnType($table, $column));

        if (in_array($type, ['bigint', 'integer', 'int', 'smallint', 'tinyint', 'mediumint'], true)) {
            return is_numeric($tenantId) ? (int) $tenantId : 0;
        }

        return (string) $tenantId;
    }

    protected function resolveRoleDisplayName(object $role, ?bool $roleNameIsJson = null): string
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

        if ($roleNameIsJson === true && is_string($name)) {
            $decoded = json_decode($name, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $candidate = $decoded[$locale] ?? reset($decoded);
                if (is_string($candidate) && trim($candidate) !== '') {
                    return trim($candidate);
                }
            }
        }

        if (is_array($name)) {
            $candidate = $name[$locale] ?? reset($name);
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        if (is_string($name) && trim($name) !== '') {
            return trim($name);
        }

        return (string) ($role->id ?? 'role');
    }

    protected function isRoleNameJsonColumn(string $roleModelClass): bool
    {
        if (! class_exists($roleModelClass)) {
            return false;
        }

        try {
            /** @var Model $roleModel */
            $roleModel = new $roleModelClass();
            $table = $roleModel->getTable();

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'name')) {
                return false;
            }

            $type = strtolower((string) Schema::getColumnType($table, 'name'));

            return in_array($type, ['json', 'jsonb'], true);
        } catch (Throwable) {
            return false;
        }
    }

    protected function resolveGuardNameForUserModel(string $userModelClass, string $fallbackGuard = 'web'): string
    {
        try {
            $user = new $userModelClass();

            if (method_exists($user, 'getDefaultGuardName')) {
                $guard = trim((string) $user->getDefaultGuardName());
                if ($guard !== '') {
                    return $guard;
                }
            }

            if (property_exists($user, 'guard_name')) {
                $guard = trim((string) ($user->guard_name ?? ''));
                if ($guard !== '') {
                    return $guard;
                }
            }
        } catch (Throwable) {
            // fallback below
        }

        $fallback = trim($fallbackGuard);

        return $fallback !== '' ? $fallback : 'web';
    }

    /**
     * @param  string|class-string<Model>|null  $fallback
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

    /**
     * @param  string|class-string<Model>|null  $fallback
     * @return class-string<Model>|null
     */
    protected function resolveOptionalModelClass(string $configKey, string|null $fallback = null): ?string
    {
        $model = config($configKey, $fallback);

        if (! is_string($model) || trim($model) === '' || ! class_exists($model)) {
            return null;
        }

        if (! is_subclass_of($model, Model::class)) {
            return null;
        }

        return $model;
    }

    protected function resolveConnectionFromConfiguredModel(string $modelClass): ?string
    {
        if (! is_string($modelClass) || ! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return null;
        }

        try {
            /** @var Model $model */
            $model = new $modelClass();
            $connection = $model->getConnectionName();

            return is_string($connection) && $connection !== '' ? $connection : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function forgetPermissionCache(): void
    {
        if (! class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
            return;
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function isDuplicateModelHasRolesException(Throwable $exception): bool
    {
        if (! $exception instanceof QueryException) {
            return false;
        }

        $table = strtolower((string) config('permission.table_names.model_has_roles', 'model_has_roles'));
        $message = strtolower($exception->getMessage());
        $driverCode = (string) ($exception->errorInfo[1] ?? '');

        return $driverCode === '1062'
            && str_contains($message, 'duplicate entry')
            && str_contains($message, $table);
    }
}
