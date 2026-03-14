<?php

namespace Upsoftware\Svarium\Modules\Builtin\Translation\Tables;

use Illuminate\Database\Eloquent\Builder;
use Upsoftware\Svarium\Models\TranslationKey;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\ColumnVisibility;
use Upsoftware\Svarium\UI\Components\Search\InputSearch;
use Upsoftware\Svarium\UI\Components\Table\Action;
use Upsoftware\Svarium\UI\Components\Table\Column;

class TranslationKeyTable
{
    public static function make(?Builder $query = null): TableBuilder
    {
        return TableBuilder::make($query ?? static::query())
            ->columns(static::columns())
            ->actions(static::rowActions())
            ->actionDisplay('dropdown')
            ->header(static::headerComponents())
            ->searchbar(static::searchbar())
            ->title(__('Translation keys'))
            ->searchable(static::searchableColumns());
    }

    protected static function query(): Builder
    {
        $modelClass = static::modelClass();

        return $modelClass::query()
            ->with('keyset:id,code,name')
            ->withCount([
                'values as values_total_count',
                'values as values_filled_count' => static fn (Builder $query): Builder => $query
                    ->whereNotNull('value')
                    ->where('value', '<>', ''),
            ])
            ->orderByDesc('status')
            ->orderBy('key');
    }

    protected static function columns(): array
    {
        return [
            Column::make('id')
                ->label('ID')
                ->sortable(),
            Column::make('translation_keyset_id')
                ->label(__('Keyset'))
                ->state(static function (array $row): string {
                    $keyset = (array) ($row['keyset'] ?? []);
                    $code = trim((string) ($keyset['code'] ?? ''));

                    if ($code !== '') {
                        return $code;
                    }

                    return trim((string) ($keyset['name'] ?? '-')) ?: '-';
                }),
            Column::make('key')
                ->label(__('Key'))
                ->sortable()
                ->action(Action::edit()),
            Column::make('type')
                ->label(__('Type'))
                ->sortable(),
            Column::make('category')
                ->label(__('Category'))
                ->state(static fn (array $row): string => trim((string) ($row['category'] ?? '')) ?: '-'),
            Column::make('coverage')
                ->label(__('Coverage'))
                ->state(static fn (array $row): string => sprintf(
                    '%d/%d',
                    (int) ($row['values_filled_count'] ?? 0),
                    (int) ($row['values_total_count'] ?? 0),
                )),
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
        return ['key', 'type', 'category', 'description', 'context'];
    }

    protected static function modelClass(): string
    {
        $configured = config('upsoftware.models.translation_key');

        if (is_string($configured) && $configured !== '' && class_exists($configured)) {
            return $configured;
        }

        return TranslationKey::class;
    }
}

