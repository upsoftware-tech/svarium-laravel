<?php

namespace Upsoftware\Svarium\Modules\Builtin\Subscriptions\Tables;

use Illuminate\Database\Eloquent\Builder;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\ColumnVisibility;
use Upsoftware\Svarium\UI\Components\Search\InputSearch;
use Upsoftware\Svarium\UI\Components\Table\Action;
use Upsoftware\Svarium\UI\Components\Table\Column;

class TenantSubscriptionsTable
{
    public static function make(?Builder $query = null): TableBuilder
    {
        return TableBuilder::make($query ?? static::query())
            ->columns(static::columns())
            ->actions(static::rowActions())
            ->actionDisplay('dropdown')
            ->header(static::headerComponents())
            ->searchbar(static::searchbar())
            ->title(__('svarium::messages.Subscriptions'))
            ->searchable(static::searchableColumns());
    }

    protected static function query(): Builder
    {
        $modelClass = get_model('tenant_subscription');

        return $modelClass::query()
            ->withCount('items')
            ->orderByDesc('id');
    }

    protected static function columns(): array
    {
        return [
            Column::make('id')
                ->label('ID')
                ->sortable(),
            Column::make('tenant_id')
                ->label(__('svarium::messages.Tenant ID'))
                ->sortable()
                ->searchable()
                ->action(Action::edit()),
            Column::make('customer_name')
                ->label(__('svarium::messages.Customer name'))
                ->searchable(),
            Column::make('status')
                ->label(__('svarium::messages.Status'))
                ->sortable(),
            Column::make('items_count')
                ->label(__('svarium::messages.Modules')),
            Column::make('total_price')
                ->label(__('svarium::messages.Total price'))
                ->sortable(),
            Column::make('currency')
                ->label(__('svarium::messages.Currency'))
                ->sortable(),
            Column::make('billing_period')
                ->label(__('svarium::messages.Billing period'))
                ->sortable(),
            Column::make('starts_at')
                ->label(__('svarium::messages.Starts at'))
                ->dateTime()
                ->format('Y-m-d')
                ->sortable(),
            Column::make('ends_at')
                ->label(__('svarium::messages.Ends at'))
                ->dateTime()
                ->format('Y-m-d')
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
        return ['tenant_id', 'customer_name', 'customer_email', 'status', 'billing_period'];
    }
}
