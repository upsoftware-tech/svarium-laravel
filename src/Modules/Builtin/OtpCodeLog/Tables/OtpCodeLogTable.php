<?php

namespace Upsoftware\Svarium\Modules\Builtin\OtpCodeLog\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\ColumnVisibility;
use Upsoftware\Svarium\UI\Components\Search\InputSearch;
use Upsoftware\Svarium\UI\Components\Table\Column;

class OtpCodeLogTable
{
    public static function make(?Builder $query = null): TableBuilder
    {
        return TableBuilder::make($query ?? static::query())
            ->title((string) svarium_label('modules.otp_code_logs.plural', __('OTP code logs')))
            ->columns(static::columns())
            ->actions(false)
            ->header(static::headerComponents())
            ->searchbar(static::searchbar())
            ->searchable(static::searchableColumns())
            ->actionDisplay('dropdown');
    }

    protected static function query(): Builder
    {
        $modelClass = static::modelClass();

        return $modelClass::query()
            ->with([
                'userAuth',
                'userAuth.user',
            ])
            ->orderByDesc('id');
    }

    protected static function columns(): array
    {
        $columns = [
            Column::make('id')
                ->label('ID')
                ->sortable(),
            Column::make('type')
                ->label(__('Type'))
                ->state(static fn (array $row): string => static::rowString($row, 'user_auth.type', '-')),
            Column::make('user')
                ->label(__('User'))
                ->state(static fn (array $row): string => static::resolveUserLabel($row)),
            Column::make('method')
                ->label(__('Method'))
                ->state(static fn (array $row): string => strtoupper(static::rowString($row, 'method', '-')))
                ->sortable(),
            Column::make('code')
                ->label(__('Code'))
                ->sortable(),
            Column::make('created_at')
                ->label(__('Sent at'))
                ->dateTime()
                ->format('Y-m-d H:i:s')
                ->sortable(),
            Column::make('is_used')
                ->label(__('Used'))
                ->state(static fn (array $row): string => ((bool) ($row['is_used'] ?? false)) ? __('Yes') : __('No')),
        ];

        if (static::hasColumn('used_at')) {
            $columns[] = Column::make('used_at')
                ->label(__('Used at'))
                ->dateTime()
                ->format('Y-m-d H:i:s')
                ->sortable();
        }

        if (static::hasAnyColumn([
            'sent_device_type',
            'sent_platform',
            'sent_platform_ver',
            'sent_browser',
            'sent_browser_ver',
            'sent_user_agent',
        ])) {
            $columns[] = Column::make('sent_device')
                ->label(__('Sent device'))
                ->state(static fn (array $row): string => static::formatDevice($row, 'sent'));
        }

        if (static::hasAnyColumn([
            'used_device_type',
            'used_platform',
            'used_platform_ver',
            'used_browser',
            'used_browser_ver',
            'used_user_agent',
        ])) {
            $columns[] = Column::make('used_device')
                ->label(__('Used device'))
                ->state(static fn (array $row): string => static::formatDevice($row, 'used'));
        }

        if (static::hasColumn('sent_ip')) {
            $columns[] = Column::make('sent_ip')
                ->label(__('Sent IP'));
        }

        if (static::hasColumn('used_ip')) {
            $columns[] = Column::make('used_ip')
                ->label(__('Used IP'));
        }

        return $columns;
    }

    protected static function headerComponents(): array
    {
        return [
            ColumnVisibility::make()->variant('outline')->size('sm'),
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
        $columns = [
            'code',
            'method',
        ];

        if (static::hasColumn('sent_ip')) {
            $columns[] = 'sent_ip';
        }

        if (static::hasColumn('used_ip')) {
            $columns[] = 'used_ip';
        }

        return $columns;
    }

    protected static function formatDevice(array $row, string $prefix): string
    {
        $prefix = strtolower(trim($prefix));
        if (! in_array($prefix, ['sent', 'used'], true)) {
            return '-';
        }

        $deviceType = static::rowString($row, "{$prefix}_device_type");
        $platform = static::rowString($row, "{$prefix}_platform");
        $platformVer = static::rowString($row, "{$prefix}_platform_ver");
        $browser = static::rowString($row, "{$prefix}_browser");
        $browserVer = static::rowString($row, "{$prefix}_browser_ver");
        $userAgent = static::rowString($row, "{$prefix}_user_agent");

        $parts = [];

        if ($deviceType !== '') {
            $parts[] = $deviceType;
        }

        $platformPart = trim($platform.' '.$platformVer);
        if ($platformPart !== '') {
            $parts[] = $platformPart;
        }

        $browserPart = trim($browser.' '.$browserVer);
        if ($browserPart !== '') {
            $parts[] = $browserPart;
        }

        if ($parts !== []) {
            return implode(' | ', $parts);
        }

        if ($userAgent !== '') {
            return $userAgent;
        }

        return '-';
    }

    protected static function resolveUserLabel(array $row): string
    {
        $email = static::rowString($row, 'user_auth.user.email');
        $name = static::rowString($row, 'user_auth.user.name');
        $userId = static::rowString($row, 'user_auth.user.id');

        if ($email !== '') {
            return $userId !== '' ? "{$email} (#{$userId})" : $email;
        }

        if ($name !== '') {
            return $userId !== '' ? "{$name} (#{$userId})" : $name;
        }

        return $userId !== '' ? "#{$userId}" : '-';
    }

    protected static function rowString(array $row, string $key, string $default = ''): string
    {
        $value = data_get($row, $key);

        if ($value === null) {
            return $default;
        }

        $resolved = trim((string) $value);

        return $resolved !== '' ? $resolved : $default;
    }

    protected static function modelClass(): string
    {
        return get_model('user_auth_code');
    }

    protected static function hasAnyColumn(array $columns): bool
    {
        foreach ($columns as $column) {
            if (static::hasColumn((string) $column)) {
                return true;
            }
        }

        return false;
    }

    protected static function hasColumn(string $column): bool
    {
        static $cache = [];

        $column = trim($column);
        if ($column === '') {
            return false;
        }

        $cacheKey = static::modelClass().':'.$column;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        try {
            $model = static::newModel();

            return $cache[$cacheKey] = Schema::connection($model->getConnectionName())
                ->hasColumn($model->getTable(), $column);
        } catch (\Throwable) {
            return $cache[$cacheKey] = false;
        }
    }

    protected static function newModel(): Model
    {
        $class = static::modelClass();

        return new $class;
    }
}
