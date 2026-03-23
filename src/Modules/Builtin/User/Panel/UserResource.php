<?php

namespace Upsoftware\Svarium\Modules\Builtin\User\Panel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Upsoftware\Svarium\Modules\Builtin\Support\AuthorizesResourcePermissions;
use Upsoftware\Svarium\Modules\Builtin\User\Tables\UserTable;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Resource;
use Upsoftware\Svarium\Panel\Table\Table;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\Form\Input;
use Upsoftware\Svarium\UI\Components\Form\Select;

class UserResource extends Resource
{
    use AuthorizesResourcePermissions;

    protected static ?string $slug = 'users';

    protected array $pendingRoleIds = [];

    public static function model(): string
    {
        return get_model('user');
    }

    public function fields(): array
    {
        return [
            'name' => __('Name'),
            'first_name' => __('First name'),
            'last_name' => __('Last name'),
            'email' => __('Email address'),
            'password' => __('Password'),
            'created_at' => __('Created at'),
        ];
    }

    public function form(?Model $record = null): array
    {
        $schema = [];

        foreach (['name', 'first_name', 'last_name'] as $field) {
            if (! $this->hasColumn($field)) {
                continue;
            }

            $schema[] = Input::make($field)
                ->label($this->resolveFieldLabel($field));
        }

        if ($this->hasColumn('email')) {
            $schema[] = Input::make('email')
                ->label(__('Email address'))
                ->type('email')
                ->required()
                ->email();
        }

        if ($this->hasColumn('password')) {
            $password = Input::make('password')
                ->label(__('Password'))
                ->type('password');

            if ($record === null) {
                $password->required()->min(8);
            }

            $schema[] = $password;
        }

        if ($this->supportsRoles()) {
            $schema[] = Select::make('roles')
                ->label(svarium_label('modules.role.plural', __('Roles')))
                ->hint(__('Select one or many roles'))
                ->placeholder(__('Select'))
                ->options($this->roleOptions())
                ->multiple()
                ->searchable()
                ->clear()
                ->value($record ? $this->selectedRoleIds($record) : []);
        }

        return $schema;
    }

    public function table(): TableBuilder
    {
        return Table::make(UserTable::class);
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
        $this->pendingRoleIds = $this->normalizeJsonArray($data['roles'] ?? []);
        unset($data['roles']);

        if (array_key_exists('password', $data)) {
            $password = trim((string) $data['password']);

            if ($password === '') {
                unset($data['password']);
            } else {
                $data['password'] = Hash::make($password);
            }
        }
    }

    public function afterSave(Model $model): void
    {
        if ($this->pendingRoleIds !== [] && method_exists($model, 'syncRoles')) {
            $model->syncRoles($this->pendingRoleIds);
        } elseif ($this->pendingRoleIds === [] && method_exists($model, 'syncRoles')) {
            $model->syncRoles([]);
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

    protected function supportsRoles(): bool
    {
        return method_exists($this->newModel(), 'roles') && class_exists($this->roleModelClass());
    }

    protected function roleOptions(): array
    {
        if (! $this->supportsRoles()) {
            return [];
        }

        $roleModel = $this->roleModelClass();

        return $roleModel::query()
            ->orderBy('id')
            ->get()
            ->map(function ($role): array {
                $label = trim((string) ($role->name_locale ?? ''));

                if ($label === '') {
                    $name = $role->name ?? null;

                    if (is_array($name)) {
                        $label = trim((string) ($name[app()->getLocale()] ?? reset($name) ?? ''));
                    } elseif (is_string($name)) {
                        $label = trim($name);
                    }
                }

                if ($label === '') {
                    $label = trim((string) ($role->role_key ?? ''));
                }

                return [
                    'value' => (string) $role->getKey(),
                    'label' => $label !== '' ? $label : '#'.$role->getKey(),
                    'description' => trim((string) ($role->guard_name ?? '')),
                ];
            })
            ->all();
    }

    protected function selectedRoleIds(Model $record): array
    {
        try {
            $modelHasRoleClass = (string) get_model('model_has_role');

            if ($modelHasRoleClass !== '' && class_exists($modelHasRoleClass)) {
                $modelHasRole = new $modelHasRoleClass;
                $query = $modelHasRoleClass::query()
                    ->where('model_id', (string) $record->getKey())
                    ->where('model_type', svarium_model_type($record));

                if ($this->modelHasRoleColumnExists($modelHasRole, 'status')) {
                    $query->where('status', 1);
                }

                if (
                    function_exists('svarium_tenancy_column_mode')
                    && svarium_tenancy_column_mode()
                    && $this->modelHasRoleColumnExists($modelHasRole, 'tenant_id')
                ) {
                    $tenantId = function_exists('tenant') ? (tenant()?->id) : null;

                    if ($tenantId !== null && $tenantId !== '') {
                        $query->where(function ($builder) use ($tenantId): void {
                            $builder->where('tenant_id', (string) $tenantId)
                                ->orWhereNull('tenant_id')
                                ->orWhere('tenant_id', '');
                        });
                    }
                }

                $roleIds = $query->pluck('role_id')
                    ->map(static fn ($id) => trim((string) $id))
                    ->filter(static fn (string $id) => $id !== '')
                    ->unique()
                    ->values()
                    ->all();

                if ($roleIds !== []) {
                    return $roleIds;
                }
            }
        } catch (Throwable) {
            // Fallback below.
        }

        if (! method_exists($record, 'roles')) {
            return [];
        }

        try {
            return $record->roles()->pluck('id')->map(static fn ($id) => trim((string) $id))
                ->filter(static fn (string $id) => $id !== '')
                ->unique()
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    protected function modelHasRoleColumnExists(Model $model, string $column): bool
    {
        try {
            return Schema::connection($model->getConnectionName())
                ->hasColumn($model->getTable(), $column);
        } catch (Throwable) {
            return false;
        }
    }

    protected function normalizeJsonArray(mixed $value): array
    {
        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } elseif (str_contains($trimmed, ',')) {
                $value = array_map(static fn (string $item): string => trim($item), explode(',', $trimmed));
            } else {
                $value = [$trimmed];
            }
        }

        if (! is_array($value)) {
            return [];
        }

        $result = [];

        $append = static function (mixed $item) use (&$append, &$result): void {
            if (is_array($item)) {
                if (array_is_list($item)) {
                    foreach ($item as $entry) {
                        $append($entry);
                    }

                    return;
                }

                if (array_key_exists('value', $item)) {
                    $normalized = trim((string) $item['value']);
                    if ($normalized !== '') {
                        $result[] = $normalized;
                    }

                    return;
                }

                if (array_key_exists('id', $item)) {
                    $normalized = trim((string) $item['id']);
                    if ($normalized !== '') {
                        $result[] = $normalized;
                    }

                    return;
                }

                foreach ($item as $entry) {
                    $append($entry);
                }

                return;
            }

            $normalized = trim((string) $item);
            if ($normalized !== '') {
                $result[] = $normalized;
            }
        };

        $append($value);

        return array_values(array_unique($result));
    }

    protected function resolveFieldLabel(string $field): string
    {
        return match ($field) {
            'first_name' => __('First name'),
            'last_name' => __('Last name'),
            default => __('Name'),
        };
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

    protected function roleModelClass(): string
    {
        return (string) config('permission.models.role', get_model('role'));
    }

    protected function singularLabel(): string
    {
        return svarium_label('modules.user.singular', __('User'));
    }

    protected function pluralLabel(): string
    {
        return svarium_label('modules.user.plural', __('Users'));
    }
}
