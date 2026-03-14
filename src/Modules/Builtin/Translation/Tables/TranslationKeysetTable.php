<?php

namespace Upsoftware\Svarium\Modules\Builtin\Translation\Tables;

use Illuminate\Database\Eloquent\Builder;
use Upsoftware\Svarium\Models\TranslationKeyset;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\ColumnVisibility;
use Upsoftware\Svarium\UI\Components\Search\InputSearch;
use Upsoftware\Svarium\UI\Components\Table\Action;
use Upsoftware\Svarium\UI\Components\Table\Column;

class TranslationKeysetTable
{
    public static function make(?Builder $query = null): TableBuilder
    {
        return TableBuilder::make($query ?? static::query())
            ->columns(static::columns())
            ->actions(static::rowActions())
            ->actionDisplay('dropdown')
            ->header(static::headerComponents())
            ->searchbar(static::searchbar())
            ->title(__('Translation keysets'))
            ->searchable(static::searchableColumns());
    }

    protected static function query(): Builder
    {
        $modelClass = static::modelClass();

        return $modelClass::query()
            ->withCount('keys')
            ->orderByDesc('status')
            ->orderBy('scope')
            ->orderBy('code');
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
            Column::make('scope')
                ->label(__('Scope'))
                ->sortable(),
            Column::make('scope_key')
                ->label(__('Scope key'))
                ->state(static fn (array $row): string => trim((string) ($row['scope_key'] ?? '')) ?: '-'),
            Column::make('name')
                ->label(__('Name'))
                ->sortable(),
            Column::make('source_locale')
                ->label(__('Source locale'))
                ->state(static fn (array $row): string => strtoupper((string) ($row['source_locale'] ?? ''))),
            Column::make('keys_count')
                ->label(__('Keys'))
                ->state(static fn (array $row): string => (string) ((int) ($row['keys_count'] ?? 0))),
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
        return ['code', 'name', 'scope', 'scope_key', 'source_locale'];
    }

    protected static function modelClass(): string
    {
        $configured = config('upsoftware.models.translation_keyset');

        if (is_string($configured) && $configured !== '' && class_exists($configured)) {
            return $configured;
        }

        return TranslationKeyset::class;
    }
}

