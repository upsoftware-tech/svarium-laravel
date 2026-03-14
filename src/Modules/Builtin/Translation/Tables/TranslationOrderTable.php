<?php

namespace Upsoftware\Svarium\Modules\Builtin\Translation\Tables;

use Illuminate\Database\Eloquent\Builder;
use Upsoftware\Svarium\Models\TranslationOrder;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\ColumnVisibility;
use Upsoftware\Svarium\UI\Components\Search\InputSearch;
use Upsoftware\Svarium\UI\Components\Table\Action;
use Upsoftware\Svarium\UI\Components\Table\Column;

class TranslationOrderTable
{
    public static function make(?Builder $query = null): TableBuilder
    {
        return TableBuilder::make($query ?? static::query())
            ->columns(static::columns())
            ->actions(static::rowActions())
            ->actionDisplay('dropdown')
            ->header(static::headerComponents())
            ->searchbar(static::searchbar())
            ->title(__('Translation orders'))
            ->searchable(static::searchableColumns());
    }

    protected static function query(): Builder
    {
        $modelClass = static::modelClass();

        return $modelClass::query()
            ->withCount('items')
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderBy('due_at')
            ->orderByDesc('updated_at');
    }

    protected static function columns(): array
    {
        return [
            Column::make('id')
                ->label('ID')
                ->sortable(),
            Column::make('code')
                ->label(__('Code'))
                ->sortable()
                ->action(Action::edit()),
            Column::make('title')
                ->label(__('Title'))
                ->sortable(),
            Column::make('status')
                ->label(__('Status'))
                ->sortable(),
            Column::make('priority')
                ->label(__('Priority'))
                ->sortable(),
            Column::make('source_locale')
                ->label(__('Source locale'))
                ->state(static fn (array $row): string => strtoupper(trim((string) ($row['source_locale'] ?? '-')))),
            Column::make('items_count')
                ->label(__('Items'))
                ->state(static fn (array $row): string => (string) ((int) ($row['items_count'] ?? 0))),
            Column::make('due_at')
                ->label(__('Due at'))
                ->dateTime()
                ->format('Y-m-d H:i'),
            Column::make('updated_at')
                ->label(__('Updated at'))
                ->dateTime()
                ->format('Y-m-d H:i'),
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
        return ['code', 'title', 'description', 'status', 'priority'];
    }

    protected static function modelClass(): string
    {
        $configured = config('upsoftware.models.translation_order');

        if (is_string($configured) && $configured !== '' && class_exists($configured)) {
            return $configured;
        }

        return TranslationOrder::class;
    }
}

