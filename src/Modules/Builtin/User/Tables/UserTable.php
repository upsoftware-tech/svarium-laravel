<?php

namespace Upsoftware\Svarium\Modules\Builtin\User\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\ColumnVisibility;
use Upsoftware\Svarium\UI\Components\Search\InputSearch;
use Upsoftware\Svarium\UI\Components\Table\Action;
use Upsoftware\Svarium\UI\Components\Table\Column;

class UserTable
{
    public static function make(?Builder $query = null): TableBuilder
    {
        return TableBuilder::make($query ?? static::query())
            ->columns(static::columns())
            ->actions(static::rowActions())
            ->actionDisplay('dropdown')
            ->header(static::headerComponents())
            ->searchbar(static::searchbar())
            ->title(svarium_label('modules.user.plural', __('Users')))
            ->searchable(static::searchableColumns());
    }

    protected static function query(): Builder
    {
        $modelClass = static::modelClass();
        $query = $modelClass::query();

        if (method_exists(new $modelClass, 'roles')) {
            $query->with('roles');
        }

        return $query;
    }

    protected static function columns(): array
    {
        $columns = [
            Column::make('id')
                ->label('ID')
                ->sortable(),
        ];

        foreach (['name', 'first_name', 'last_name', 'email'] as $column) {
            if (! static::hasColumn($column)) {
                continue;
            }

            $columns[] = Column::make($column)
                ->sortable()
                ->searchable($column !== 'id')
                ->action($column === 'email' ? Action::edit() : 'edit');
        }

        if (method_exists(static::newModel(), 'roles')) {
            $columns[] = Column::make('roles')
                ->label(svarium_label('modules.role.plural', __('Roles')))
                ->state(static function (array $row): string {
                    $roles = data_get($row, 'roles', []);

                    if (! is_array($roles) || $roles === []) {
                        return '-';
                    }

                    $labels = [];

                    foreach ($roles as $role) {
                        $label = trim((string) data_get($role, 'name_locale', ''));

                        if ($label === '') {
                            $name = data_get($role, 'name');
                            if (is_array($name)) {
                                $label = trim((string) ($name[app()->getLocale()] ?? reset($name) ?? ''));
                            } elseif (is_string($name)) {
                                $label = trim($name);
                            }
                        }

                        if ($label === '') {
                            $label = trim((string) data_get($role, 'role_key', ''));
                        }

                        if ($label !== '') {
                            $labels[] = $label;
                        }
                    }

                    return $labels === [] ? '-' : implode(', ', $labels);
                });
        }

        if (static::hasColumn('created_at')) {
            $columns[] = Column::make('created_at')
                ->label(__('Created at'))
                ->dateTime()
                ->format('Y-m-d H:i');
        }

        return $columns;
    }

    protected static function rowActions(): array
    {
        return [
            Action::edit(),
            Action::delete(),
        ];
    }

    protected static function headerComponents(): array
    {
        return [
            ColumnVisibility::make()->variant('outline')->size('sm'),
            Action::create()->variant('outline')->size('sm'),
        ];
    }

    protected static function searchbar(): array
    {
        return [
            InputSearch::make('q')
                ->placeholder(__('Search...')),
        ];
    }

    protected static function searchableColumns(): array
    {
        return array_values(array_filter(['name', 'first_name', 'last_name', 'email'], fn ($column) => static::hasColumn($column)));
    }

    protected static function modelClass(): string
    {
        return get_model('user');
    }

    protected static function newModel(): Model
    {
        $class = static::modelClass();

        return new $class;
    }

    protected static function hasColumn(string $column): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::connection(static::newModel()->getConnectionName())
                ->hasColumn(static::newModel()->getTable(), $column);
        } catch (\Throwable) {
            return false;
        }
    }
}
