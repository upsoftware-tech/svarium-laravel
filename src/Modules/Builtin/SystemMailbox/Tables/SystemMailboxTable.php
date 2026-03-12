<?php

namespace Upsoftware\Svarium\Modules\Builtin\SystemMailbox\Tables;

use Illuminate\Database\Eloquent\Builder;
use Upsoftware\Svarium\Models\SystemMailbox;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\ColumnVisibility;
use Upsoftware\Svarium\UI\Components\Search\InputSearch;
use Upsoftware\Svarium\UI\Components\Table\Action;
use Upsoftware\Svarium\UI\Components\Table\Column;

class SystemMailboxTable
{
    public static function make(?Builder $query = null): TableBuilder
    {
        return TableBuilder::make($query ?? static::query())
            ->columns(static::columns())
            ->actions(static::rowActions())
            ->actionDisplay('dropdown')
            ->header(static::headerComponents())
            ->searchbar(static::searchbar())
            ->title(svarium_label('modules.system_mailboxes.plural', __('Skrzynki nadawcze')))
            ->searchable(static::searchableColumns());
    }

    protected static function query(): Builder
    {
        $modelClass = static::modelClass();

        return $modelClass::query()
            ->orderByDesc('is_default')
            ->orderByDesc('status')
            ->orderBy('name');
    }

    protected static function columns(): array
    {
        return [
            Column::make('id')
                ->label('ID')
                ->sortable(),
            Column::make('name')
                ->label(__('Name'))
                ->sortable()
                ->action(Action::edit()),
            Column::make('driver')
                ->label(__('Driver'))
                ->sortable(),
            Column::make('scope_type')
                ->label(__('Scope'))
                ->state(static fn (array $row): string => ucfirst(trim((string) ($row['scope_type'] ?? 'global')))),
            Column::make('scope_id')
                ->label(__('Scope ID'))
                ->state(static fn (array $row): string => trim((string) ($row['scope_id'] ?? '')) ?: '-'),
            Column::make('from_email')
                ->label(__('From email')),
            Column::make('is_default')
                ->label(__('Default'))
                ->state(static fn (array $row): string => ((bool) ($row['is_default'] ?? false)) ? __('Yes') : __('No')),
            Column::make('status')
                ->label(__('Status'))
                ->state(static fn (array $row): string => ((bool) ($row['status'] ?? false)) ? __('Active') : __('Inactive')),
            Column::make('updated_at')
                ->label(__('Updated at'))
                ->dateTime()
                ->format('Y-m-d H:i'),
        ];
    }

    protected static function rowActions(): array
    {
        return [
            Action::edit('{id}/test-connection')
                ->type('test_connection')
                ->label(__('Test connection'))
                ->icon('lucide:plug-zap'),
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
        return ['name', 'driver', 'host', 'from_email', 'username'];
    }

    protected static function modelClass(): string
    {
        $configured = config('upsoftware.models.system_mailbox');

        if (is_string($configured) && $configured !== '' && class_exists($configured)) {
            return $configured;
        }

        return SystemMailbox::class;
    }
}
