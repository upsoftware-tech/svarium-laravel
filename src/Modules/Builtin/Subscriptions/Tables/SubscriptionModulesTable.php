<?php

namespace Upsoftware\Svarium\Modules\Builtin\Subscriptions\Tables;

use Illuminate\Database\Eloquent\Builder;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\ColumnVisibility;
use Upsoftware\Svarium\UI\Components\Search\InputSearch;
use Upsoftware\Svarium\UI\Components\Table\Action;
use Upsoftware\Svarium\UI\Components\Table\Column;

class SubscriptionModulesTable
{
    public static function make(?Builder $query = null): TableBuilder
    {
        return TableBuilder::make($query ?? static::query())
            ->columns(static::columns())
            ->actions(static::rowActions())
            ->actionDisplay('dropdown')
            ->header(static::headerComponents())
            ->searchbar(static::searchbar())
            ->title(__('svarium::messages.Subscription modules'))
            ->searchable(static::searchableColumns());
    }

    protected static function query(): Builder
    {
        $modelClass = get_model('subscription_module');

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
            Column::make('base_price')
                ->label(__('svarium::messages.Base price'))
                ->sortable(),
            Column::make('currency')
                ->label(__('svarium::messages.Currency'))
                ->sortable(),
            Column::make('billing_period')
                ->label(__('svarium::messages.Billing period'))
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
