<?php

namespace Upsoftware\Svarium\Modules\Builtin\Role\Panel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Upsoftware\Svarium\Modules\Builtin\Role\Tables\RoleTable;
use Upsoftware\Svarium\Modules\Builtin\Support\AuthorizesResourcePermissions;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Resource;
use Upsoftware\Svarium\Panel\Table\Table;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\Roles\RoleParameterRegistry;
use Upsoftware\Svarium\Roles\RolePermissionCatalog;
use Upsoftware\Svarium\UI\Components\Checklist;
use Upsoftware\Svarium\UI\Components\Form\Input;
use Upsoftware\Svarium\UI\Components\Form\Select;

class RoleResource extends Resource
{
    use AuthorizesResourcePermissions;

    protected static ?string $slug = 'roles';

    protected array $pendingPermissions = [];

    protected array $pendingSettings = [];

    public static function model(): string
    {
        return get_model('role');
    }

    public function fields(): array
    {
        return [
            'guard_name' => __('Guard'),
            'role_key' => __('Key'),
            'permissions' => __('Permissions'),
        ];
    }

    public function form(?Model $record = null): array
    {
        $guardName = $record ? (string) ($record->guard_name ?? $this->defaultGuardName()) : $this->defaultGuardName();

        $schema = $this->translationFields($record);

        if ($this->hasColumn('role_key')) {
            $schema[] = Input::make('role_key')
                ->label(__('Key'))
                ->value($record ? (string) ($record->role_key ?? '') : '');
        }

        $schema[] = Select::make('guard_name')
            ->label(__('Guard'))
            ->options($this->guardOptions())
            ->value($guardName);

        $schema[] = Checklist::make('permissions')
            ->label(__('Permissions'))
            ->options(app(RolePermissionCatalog::class)->groupedOptions($guardName))
            ->value($record ? $this->selectedPermissions($record) : [])
            ->emptyLabel(__('No permissions available.'));

        foreach (app(RoleParameterRegistry::class)->all() as $definition) {
            $schema[] = $this->makeRoleParameterField($definition, $record);
        }

        return $schema;
    }

    public function table(): TableBuilder
    {
        return Table::make(RoleTable::class);
    }

    public function listTitle(PanelContext $context): string
    {
        return sprintf('%s list', $this->pluralLabel());
    }

    public function createTitle(PanelContext $context): string
    {
        return sprintf('Create %s', $this->singularLabel());
    }

    public function editTitle(PanelContext $context, Model $record): string
    {
        return sprintf('Edit %s', $this->singularLabel());
    }

    public function duplicateTitle(PanelContext $context, Model $record): string
    {
        return sprintf('Duplicate %s', $this->singularLabel());
    }

    public function previewTitle(PanelContext $context, Model $record): string
    {
        return sprintf('Preview %s', $this->singularLabel());
    }

    public function importTitle(PanelContext $context): string
    {
        return sprintf('Import %s', $this->pluralLabel());
    }

    public function beforeSave(Model $model, array &$data): void
    {
        $guardName = trim((string) ($data['guard_name'] ?? $this->defaultGuardName()));
        if ($guardName === '') {
            $guardName = $this->defaultGuardName();
        }

        app(RolePermissionCatalog::class)->ensurePermissionsForGuard($guardName);

        $this->pendingPermissions = $this->normalizeJsonArray($data['permissions'] ?? []);
        unset($data['permissions']);

        $this->pendingSettings = [];

        foreach (array_keys($data) as $key) {
            if (! str_starts_with($key, 'setting_')) {
                continue;
            }

            $settingKey = substr($key, 8);
            if ($settingKey === false || $settingKey === '') {
                continue;
            }

            $this->pendingSettings[$settingKey] = $this->normalizeJsonArray($data[$key]);
            unset($data[$key]);
        }

        $translations = [];

        foreach (array_keys($data) as $key) {
            if (! str_starts_with($key, 'name_')) {
                continue;
            }

            $locale = substr($key, 5);
            if ($locale === false || $locale === '') {
                continue;
            }

            $value = trim((string) $data[$key]);
            unset($data[$key]);

            if ($value !== '') {
                $translations[$locale] = $value;
            }
        }

        $primaryName = $this->resolvePrimaryRoleName($translations);
        $data['guard_name'] = $guardName;

        if ($translations !== [] && $this->supportsTranslatedName($model)) {
            if (method_exists($model, 'setTranslations')) {
                $model->setTranslations('name', $translations);
            } else {
                $data['name'] = $translations;
            }

            if ($this->hasColumn('name_locale')) {
                $data['name_locale'] = $primaryName;
            }
        } else {
            $data['name'] = $primaryName;

            if ($this->hasColumn('name_locale')) {
                $data['name_locale'] = $primaryName;
            }
        }
    }

    public function afterSave(Model $model): void
    {
        if (method_exists($model, 'syncPermissions')) {
            $model->syncPermissions($this->pendingPermissions);
        }

        if (method_exists($model, 'setSetting')) {
            foreach ($this->pendingSettings as $key => $value) {
                $model->setSetting($key, $value);
            }
        }
    }

    public function canList(PanelContext $context): bool
    {
        return $this->canResourceAction($context, 'list');
    }

    public function canCreate(PanelContext $context): bool
    {
        return $this->canResourceAction($context, 'create');
    }

    public function canEdit(PanelContext $context): bool
    {
        return $this->canResourceAction($context, 'edit');
    }

    public function canDelete(PanelContext $context): bool
    {
        return $this->canResourceAction($context, 'delete');
    }

    protected function translationFields(?Model $record): array
    {
        $fields = [];
        $translations = $record ? $this->currentTranslations($record) : [];
        $availableLocales = $this->availableLocales();
        $requiredLocale = app()->getLocale();

        foreach ($availableLocales as $locale) {
            $code = trim((string) ($locale['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $label = trim((string) ($locale['label'] ?? $code));
            $input = Input::make('name_'.$code)
                ->label(__('Name').' ('.$label.')')
                ->value((string) ($translations[$code] ?? ''));

            if ($code === $requiredLocale) {
                $input->required();
            }

            $fields[] = $input;
        }

        if ($fields === []) {
            $fields[] = Input::make('name_'.app()->getLocale())
                ->label(__('Name'))
                ->value((string) ($translations[app()->getLocale()] ?? ''))
                ->required();
        }

        return $fields;
    }

    protected function availableLocales(): array
    {
        $configured = locales();

        if ($configured !== []) {
            return array_map(static fn (array $locale): array => [
                'code' => (string) ($locale['value'] ?? ''),
                'label' => (string) ($locale['label'] ?? ($locale['value'] ?? '')),
            ], $configured);
        }

        return [[
            'code' => app()->getLocale(),
            'label' => strtoupper((string) app()->getLocale()),
        ]];
    }

    protected function currentTranslations(Model $record): array
    {
        $name = $record->getAttribute('name');

        if (is_array($name)) {
            return $name;
        }

        if (method_exists($record, 'getTranslations')) {
            try {
                $translations = $record->getTranslations('name');

                return is_array($translations) ? $translations : [];
            } catch (Throwable) {
                return [];
            }
        }

        if (is_string($name) && trim($name) !== '') {
            return [app()->getLocale() => trim($name)];
        }

        return [];
    }

    protected function selectedPermissions(Model $record): array
    {
        if (! method_exists($record, 'permissions')) {
            return [];
        }

        try {
            return $record->permissions()->pluck('name')->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected function selectedSettingValues(Model $record, string $key): array
    {
        if (! method_exists($record, 'getSetting')) {
            return [];
        }

        try {
            $value = $record->getSetting($key, []);

            return is_array($value) ? $value : [];
        } catch (Throwable) {
            return [];
        }
    }

    protected function makeRoleParameterField(array $definition, ?Model $record = null)
    {
        $name = 'setting_'.$definition['setting_key'];
        $label = (string) ($definition['label'] ?? $definition['key']);
        $hint = (string) ($definition['description'] ?? '');
        $options = (array) ($definition['options'] ?? []);
        $value = $record ? $this->selectedSettingValues($record, (string) $definition['setting_key']) : [];

        if ((string) ($definition['setting_key'] ?? '') === 'languages') {
            $languageConfig = (array) ($this->resolveFormConfig(
                app()->bound(PanelContext::class) ? app(PanelContext::class) : null,
                $record
            )['language'] ?? []);

            if (($languageConfig['display'] ?? 'inline') === 'select') {
                $multiple = (bool) ($languageConfig['multiple'] ?? false);

                return Select::make($name)
                    ->label($label)
                    ->hint($hint)
                    ->options($options)
                    ->multiple($multiple)
                    ->value($multiple ? $value : ($value[0] ?? null));
            }
        }

        return Checklist::make($name)
            ->label($label)
            ->hint($hint)
            ->options($options)
            ->value($value);
    }

    protected function normalizeJsonArray(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = [trim($value)];
            }
        } elseif (is_scalar($value) || $value instanceof \Stringable) {
            $value = [trim((string) $value)];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($item) => trim((string) $item),
            $value
        ), static fn ($item) => $item !== ''));
    }

    protected function resolvePrimaryRoleName(array $translations): string
    {
        $preferred = trim((string) ($translations[app()->getLocale()] ?? ''));

        if ($preferred !== '') {
            return $preferred;
        }

        foreach ($translations as $translation) {
            $value = trim((string) $translation);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    protected function supportsTranslatedName(Model $model): bool
    {
        if (method_exists($model, 'setTranslations')) {
            return true;
        }

        try {
            $type = strtolower((string) Schema::connection($model->getConnectionName())->getColumnType($model->getTable(), 'name'));

            return in_array($type, ['json', 'jsonb'], true);
        } catch (Throwable) {
            return false;
        }
    }

    protected function guardOptions(): array
    {
        $guards = array_keys((array) config('auth.guards', []));

        if ($guards === []) {
            $guards = ['web'];
        }

        return array_map(static fn ($guard) => [
            'value' => $guard,
            'label' => $guard,
        ], $guards);
    }

    protected function defaultGuardName(): string
    {
        $guard = trim((string) config('auth.defaults.guard', 'web'));

        return $guard !== '' ? $guard : 'web';
    }

    protected function hasColumn(string $column): bool
    {
        try {
            $model = $this->newModel();

            return Schema::connection($model->getConnectionName())
                ->hasColumn($model->getTable(), $column);
        } catch (Throwable) {
            return false;
        }
    }

    protected function singularLabel(): string
    {
        return svarium_label('modules.role.singular', __('Role'));
    }

    protected function pluralLabel(): string
    {
        return svarium_label('modules.role.plural', __('Roles'));
    }
}
