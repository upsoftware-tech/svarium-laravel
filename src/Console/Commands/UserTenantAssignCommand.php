<?php

namespace Upsoftware\Svarium\Console\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

class UserTenantAssignCommand extends CoreCommand
{
    protected $signature = 'svarium:user.tenant.assign
        {--user= : ID lub adres e-mail użytkownika}';

    protected $description = 'Przypisuje tenanty do użytkownika (dodawanie i usuwanie przez zaznaczenie)';

    public function handle(): int
    {
        try {
            $this->ensureTenancyEnabled();

            $userModelClass = $this->resolveUserModelClass();
            $tenantModelClass = $this->resolveModelClass('upsoftware.models.tenant', \Upsoftware\Svarium\Models\Tenant::class);

            $this->validatePrerequisites($userModelClass, $tenantModelClass);

            $user = $this->resolveUser($userModelClass);

            [$selectedTenantIds, $selectedTenantLabels] = $this->resolveTenantSelection($tenantModelClass, $user);

            $this->syncUserTenants($user, $selectedTenantIds);

            $this->info('Zapisano przypisanie tenantów dla użytkownika.');
            $this->line('Użytkownik: '.$this->userDisplay($user));
            $this->line('Tenanty: '.($selectedTenantLabels === [] ? '-' : implode(', ', $selectedTenantLabels)));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    protected function ensureTenancyEnabled(): void
    {
        $enabled = (bool) config('upsoftware.tenancy.enabled', config('tenancy.enabled', false));
        if (! $enabled) {
            throw new RuntimeException('Tenancy jest wyłączone. Włącz tenancy i spróbuj ponownie.');
        }
    }

    /**
     * @param class-string<Model> $userModelClass
     * @param class-string<Model> $tenantModelClass
     */
    protected function validatePrerequisites(string $userModelClass, string $tenantModelClass): void
    {
        $errors = [];

        /** @var Model $userPrototype */
        $userPrototype = new $userModelClass();
        $usersTable = $userPrototype->getTable();
        if (! Schema::hasTable($usersTable)) {
            $errors[] = "Tabela użytkowników [{$usersTable}] nie istnieje.";
        }

        /** @var Model $tenantPrototype */
        $tenantPrototype = new $tenantModelClass();
        $tenantsTable = $tenantPrototype->getTable();

        if (! Schema::hasTable($tenantsTable)) {
            $errors[] = 'Tabela tenants nie istnieje. Utwórz tenanty przed przypisaniem.';
        } else {
            try {
                if (! $tenantModelClass::query()->exists()) {
                    $errors[] = 'Brak tenantów. Najpierw utwórz tenant.';
                }
            } catch (Throwable $exception) {
                $errors[] = 'Nie udało się odczytać tenantów: '.$exception->getMessage();
            }
        }

        $table = $this->tenantMapTable();
        if (! Schema::hasTable($table)) {
            $errors[] = "Brak tabeli mapowania tenantów [{$table}].";
        }

        if ($errors !== []) {
            throw new RuntimeException(implode(PHP_EOL, array_values(array_unique($errors))));
        }
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
            if ($user !== null) {
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
            $email = strtolower($identifier);

            $found = $userModelClass::query()
                ->where('email', $email)
                ->first();

            if ($found instanceof Model) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param class-string<Model> $tenantModelClass
     * @return array{0: array<int, string|int>, 1: array<int, string>}
     */
    protected function resolveTenantSelection(string $tenantModelClass, Model $user): array
    {
        $tenants = $tenantModelClass::query()
            ->orderBy('name')
            ->get();

        if ($tenants->isEmpty()) {
            throw new RuntimeException('Brak tenantów. Najpierw utwórz tenant.');
        }

        $options = [];
        $idsMap = [];
        $labelsById = [];

        foreach ($tenants as $tenant) {
            $tenantId = $tenant->getKey();
            if ($tenantId === null || $tenantId === '') {
                continue;
            }

            $idKey = (string) $tenantId;
            $name = trim((string) ($tenant->name ?? $idKey));
            $status = (bool) ($tenant->status ?? true);
            $label = $name.' ('.$idKey.')'.($status ? '' : ' [inactive]');

            $options[$idKey] = $label;
            $idsMap[$idKey] = $tenantId;
            $labelsById[$idKey] = $name;
        }

        if ($options === []) {
            throw new RuntimeException('Nie udało się przygotować listy tenantów.');
        }

        $defaults = array_values(array_filter(
            $this->currentUserTenantIds($user),
            static fn (string $id): bool => array_key_exists($id, $options)
        ));

        if (! $this->input->isInteractive()) {
            throw new RuntimeException('Ta komenda wymaga trybu interaktywnego do wyboru tenantów.');
        }

        $selected = multiselect(
            'Wybierz tenanty (zaznaczone = przypisane)',
            $options,
            $defaults
        );

        $resolvedIds = [];
        $resolvedLabels = [];

        foreach ((array) $selected as $selectedId) {
            $selectedKey = (string) $selectedId;
            if (! array_key_exists($selectedKey, $idsMap)) {
                continue;
            }

            $resolvedIds[] = $idsMap[$selectedKey];
            $resolvedLabels[] = $labelsById[$selectedKey] ?? $selectedKey;
        }

        $resolvedIds = array_values(array_unique($resolvedIds, SORT_REGULAR));
        $resolvedLabels = array_values(array_unique($resolvedLabels));

        return [$resolvedIds, $resolvedLabels];
    }

    /**
     * @return array<int, string>
     */
    protected function currentUserTenantIds(Model $user): array
    {
        $table = $this->tenantMapTable();
        $modelId = $user->getKey();
        if ($modelId === null) {
            return [];
        }

        $modelType = svarium_model_type($user);
        $connection = $this->resolveConnectionFromConfiguredModel(
            (string) config('upsoftware.models.model_has_tenant', \Upsoftware\Svarium\Models\ModelHasTenant::class)
        );

        $query = is_string($connection) && $connection !== ''
            ? DB::connection($connection)->table($table)
            : DB::table($table);

        return $query
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->pluck('tenant_id')
            ->map(static fn ($value): string => (string) $value)
            ->values()
            ->all();
    }

    /**
     * @param array<int, string|int> $tenantIds
     */
    protected function syncUserTenants(Model $user, array $tenantIds): void
    {
        $table = $this->tenantMapTable();
        $modelId = $user->getKey();
        if ($modelId === null) {
            throw new RuntimeException('Brak ID użytkownika.');
        }

        $modelType = svarium_model_type($user);

        $connection = $this->resolveConnectionFromConfiguredModel(
            (string) config('upsoftware.models.model_has_tenant', \Upsoftware\Svarium\Models\ModelHasTenant::class)
        );

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

    protected function tenantMapTable(): string
    {
        $table = (string) config('upsoftware.tenancy.column.model_maps.tenants.table', 'model_has_tenants');
        $table = trim($table);

        return $table !== '' ? $table : 'model_has_tenants';
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

    protected function userDisplay(Model $user): string
    {
        $id = (string) ($user->getKey() ?? '');
        $email = trim((string) ($user->getAttribute('email') ?? ''));

        if ($email !== '') {
            return "{$email} ({$id})";
        }

        return $id !== '' ? $id : 'unknown';
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
            // Fallback below.
        }

        $trackingUserModel = config('upsoftware.tracking.user_model', config('upsoftware.user_model'));
        if (is_string($trackingUserModel) && trim($trackingUserModel) !== '' && class_exists($trackingUserModel) && is_subclass_of($trackingUserModel, Model::class)) {
            return $trackingUserModel;
        }

        return $this->resolveModelClass(
            'upsoftware.models.user',
            \Upsoftware\Svarium\Models\User::class
        );
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
}
