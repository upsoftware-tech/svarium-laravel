<?php

namespace Upsoftware\Svarium\Modules\Builtin\Role\Tables;

use Illuminate\Database\Eloquent\Builder;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\ColumnVisibility;
use Upsoftware\Svarium\UI\Components\Search\InputSearch;
use Upsoftware\Svarium\UI\Components\Table\Action;
use Upsoftware\Svarium\UI\Components\Table\Column;

class RoleTable
{
    public static function make(?Builder $query = null): TableBuilder
    {
        return TableBuilder::make($query ?? static::query())
            ->columns(static::columns())
            ->actions(static::rowActions())
            ->actionDisplay('dropdown')
            ->header(static::headerComponents())
            ->searchbar(static::searchbar())
            ->title(svarium_label('modules.role.plural', __('Roles')))
            ->searchable(static::searchableColumns());
    }

    protected static function query(): Builder
    {
        $roleModel = get_model('role');

        return $roleModel::query()
            ->withCount('permissions');
    }

    protected static function columns(): array
    {
        $nameLocaleColumn = Column::make('name_locale')
            ->label(__('Name'))
            ->sortable(static::hasColumn('name_locale'))
            ->searchable(static::hasColumn('name_locale'))
            ->action('edit')
            ->state(static function (array $row): string {
                $label = trim((string) data_get($row, 'name_locale', ''));

                if ($label !== '') {
                    return $label;
                }

                $name = data_get($row, 'name');

                if (is_array($name)) {
                    return trim((string) ($name[app()->getLocale()] ?? reset($name) ?? '-'));
                }

                if (is_string($name) && trim($name) !== '') {
                    return trim($name);
                }

                return '-';
            });

        return [
            Column::make('id')
                ->label('ID')
                ->sortable(),

            $nameLocaleColumn,

            Column::make('role_key')
                ->label(__('Key'))
                ->sortable()
                ->searchable(),

            Column::make('guard_name')
                ->label(__('Guard'))
                ->sortable()
                ->searchable(),

            Column::make('permissions_count')
                ->label(__('Permissions'))
                ->sortable(),
        ];
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
        $columns = ['role_key', 'guard_name'];

        if (static::hasColumn('name_locale')) {
            array_unshift($columns, 'name_locale');
        }

        return $columns;
    }

    protected static function hasColumn(string $column): bool
    {
        try {
            $modelClass = get_model('role');
            $model = new $modelClass;

            return \Illuminate\Support\Facades\Schema::connection($model->getConnectionName())
                ->hasColumn($model->getTable(), $column);
        } catch (\Throwable) {
            return false;
        }
    }
}
