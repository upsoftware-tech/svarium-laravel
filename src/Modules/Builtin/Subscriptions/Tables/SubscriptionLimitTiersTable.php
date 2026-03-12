<?php

namespace Upsoftware\Svarium\Modules\Builtin\Subscriptions\Tables;

use Illuminate\Database\Eloquent\Builder;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\ColumnVisibility;
use Upsoftware\Svarium\UI\Components\Search\InputSearch;
use Upsoftware\Svarium\UI\Components\Table\Action;
use Upsoftware\Svarium\UI\Components\Table\Column;

class SubscriptionLimitTiersTable
{
    public static function make(?Builder $query = null): TableBuilder
    {
        return TableBuilder::make($query ?? static::query())
            ->columns(static::columns())
            ->actions(static::rowActions())
            ->actionDisplay('dropdown')
            ->header(static::headerComponents())
            ->searchbar(static::searchbar())
            ->title(__('svarium::messages.Subscription limits'))
            ->searchable(static::searchableColumns());
    }

    protected static function query(): Builder
    {
        $modelClass = get_model('subscription_limit_tier');

        return $modelClass::query()
            ->orderBy('sort')
            ->orderBy('name');
    }

    protected static function columns(): array
    {
        return [
            Column::make('id')
                ->label('ID')
                ->sortable(),
            Column::make('name')
                ->label(__('svarium::messages.Name'))
                ->sortable()
                ->searchable()
                ->action(Action::edit()),
            Column::make('key')
                ->label(__('svarium::messages.Key'))
                ->sortable()
                ->searchable(),
            Column::make('min_value')
                ->label(__('svarium::messages.Min')),
            Column::make('max_value')
                ->label(__('svarium::messages.Max'))
                ->state(static function (array $row): string {
                    if ((bool) ($row['is_unlimited'] ?? false)) {
                        return __('svarium::messages.No limit');
                    }

                    $value = $row['max_value'] ?? null;

                    return $value === null ? '-' : (string) $value;
                }),
            Column::make('price_delta')
                ->label(__('svarium::messages.Price delta'))
                ->sortable(),
            Column::make('currency')
                ->label(__('svarium::messages.Currency'))
                ->sortable(),
            Column::make('status')
                ->label(__('svarium::messages.Status'))
                ->state(static fn (array $row): string => ((bool) ($row['status'] ?? false)) ? __('svarium::messages.Active') : __('svarium::messages.Inactive')),
            Column::make('sort')
                ->label(__('svarium::messages.Sort'))
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
        return ['name', 'key', 'description'];
    }
}
