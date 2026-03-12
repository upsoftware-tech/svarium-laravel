<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $canonical = $this->canonicalUserModelType();
        if ($canonical === '') {
            return;
        }

        $aliases = $this->userModelTypeAliases($canonical);
        if ($aliases === []) {
            return;
        }

        $this->normalizePivotLikeTable('model_has_roles', 'model_type', $canonical, $aliases);
        $this->normalizePivotLikeTable('model_has_permissions', 'model_type', $canonical, $aliases);
        $this->normalizePivotLikeTable('model_has_tenants', 'model_type', $canonical, $aliases);
        $this->normalizePivotLikeTable('model_has_domains', 'model_type', $canonical, $aliases);
        $this->normalizePivotLikeTable('model_has_domain_tenants', 'model_type', $canonical, $aliases);
        $this->normalizeSettingsTable($canonical, $aliases);
    }

    public function down(): void
    {
        // Forward-only normalization.
    }

    protected function normalizePivotLikeTable(
        string $table,
        string $modelTypeColumn,
        string $canonical,
        array $aliases
    ): void {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $modelTypeColumn)) {
            return;
        }

        $columns = Schema::getColumnListing($table);
        $orderColumn = in_array('id', $columns, true) ? 'id' : 'model_id';

        $rows = DB::table($table)
            ->whereIn($modelTypeColumn, $aliases)
            ->orderBy($orderColumn)
            ->get();

        foreach ($rows as $row) {
            $currentType = (string) ($row->{$modelTypeColumn} ?? '');
            if ($currentType === '' || $currentType === $canonical) {
                continue;
            }

            $selector = $this->rowSelector((array) $row, $modelTypeColumn);
            $canonicalExists = $this->rowExistsWithModelType($table, $modelTypeColumn, $canonical, $selector);

            if ($canonicalExists) {
                $this->deleteRows($table, $modelTypeColumn, $currentType, $selector);
                continue;
            }

            $this->updateRowsModelType($table, $modelTypeColumn, $currentType, $canonical, $selector);
        }
    }

    protected function normalizeSettingsTable(string $canonical, array $aliases): void
    {
        if (! Schema::hasTable('settings') || ! Schema::hasColumn('settings', 'model_type')) {
            return;
        }

        $rows = DB::table('settings')
            ->whereNotNull('model_id')
            ->whereIn('model_type', $aliases)
            ->orderBy('id')
            ->get()
            ->groupBy(static fn (object $row): string => (string) ($row->model_id ?? ''));

        foreach ($rows as $group) {
            $this->normalizeSettingsGroup($group, $canonical);
        }
    }

    protected function normalizeSettingsGroup(Collection $group, string $canonical): void
    {
        if ($group->isEmpty()) {
            return;
        }

        /** @var object $keep */
        $keep = $group->lastWhere('model_type', $canonical) ?? $group->last();
        $merged = [];

        foreach ($group as $row) {
            $value = json_decode((string) ($row->value ?? '[]'), true);
            if (is_array($value)) {
                $merged = array_replace($merged, $value);
            }
        }

        DB::table('settings')
            ->where('id', $keep->id)
            ->update([
                'model_type' => $canonical,
                'value' => json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);

        $idsToDelete = $group
            ->pluck('id')
            ->filter(static fn ($id): bool => (int) $id !== (int) $keep->id)
            ->values()
            ->all();

        if ($idsToDelete !== []) {
            DB::table('settings')->whereIn('id', $idsToDelete)->delete();
        }
    }

    protected function canonicalUserModelType(): string
    {
        $model = config('upsoftware.models.user');

        if (! is_string($model) || $model === '' || ! class_exists($model)) {
            return '';
        }

        return $this->resolveModelType($model);
    }

    protected function userModelTypeAliases(string $canonical): array
    {
        $candidates = [
            $canonical,
            config('upsoftware.models.user'),
            config('upsoftware.tracking.user_model'),
            config('upsoftware.user_model'),
            \Upsoftware\Svarium\Models\User::class,
            'App\\Models\\User',
            'App\\Model\\User',
        ];

        foreach ((array) config('upsoftware.auth.original_provider_models', []) as $providerModel) {
            $candidates[] = $providerModel;
        }

        foreach ((array) config('auth.providers', []) as $definition) {
            $candidates[] = $definition['model'] ?? null;
        }

        $aliases = [];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $aliases[$this->resolveModelType($candidate)] = true;
            $aliases[ltrim($candidate, '\\')] = true;
        }

        return array_keys($aliases);
    }

    protected function resolveModelType(string $model): string
    {
        $class = ltrim($model, '\\');

        if ($class !== '' && class_exists($class)) {
            $instance = new $class;

            if (method_exists($instance, 'getMorphClass')) {
                return ltrim((string) $instance->getMorphClass(), '\\');
            }
        }

        return $class;
    }

    protected function rowSelector(array $row, string $modelTypeColumn): array
    {
        $selector = [];

        foreach ($row as $column => $value) {
            if (in_array($column, ['id', 'created_at', 'updated_at', $modelTypeColumn, 'status'], true)) {
                continue;
            }

            $selector[$column] = $value;
        }

        return $selector;
    }

    protected function rowExistsWithModelType(
        string $table,
        string $modelTypeColumn,
        string $modelType,
        array $selector
    ): bool {
        $query = DB::table($table)->where($modelTypeColumn, $modelType);

        foreach ($selector as $column => $value) {
            if ($value === null) {
                $query->whereNull($column);
                continue;
            }

            $query->where($column, $value);
        }

        return $query->exists();
    }

    protected function deleteRows(
        string $table,
        string $modelTypeColumn,
        string $currentType,
        array $selector
    ): void {
        $query = DB::table($table)->where($modelTypeColumn, $currentType);

        foreach ($selector as $column => $value) {
            if ($value === null) {
                $query->whereNull($column);
                continue;
            }

            $query->where($column, $value);
        }

        $query->delete();
    }

    protected function updateRowsModelType(
        string $table,
        string $modelTypeColumn,
        string $currentType,
        string $canonical,
        array $selector
    ): void {
        $query = DB::table($table)->where($modelTypeColumn, $currentType);

        foreach ($selector as $column => $value) {
            if ($value === null) {
                $query->whereNull($column);
                continue;
            }

            $query->where($column, $value);
        }

        $query->update([$modelTypeColumn => $canonical]);
    }
};
