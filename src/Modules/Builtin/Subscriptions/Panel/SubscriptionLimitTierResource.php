<?php

namespace Upsoftware\Svarium\Modules\Builtin\Subscriptions\Panel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Upsoftware\Svarium\Modules\Builtin\Subscriptions\Tables\SubscriptionLimitTiersTable;
use Upsoftware\Svarium\Modules\Builtin\Support\AuthorizesResourcePermissions;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Resource;
use Upsoftware\Svarium\Panel\Table\Table;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\Form\Input;
use Upsoftware\Svarium\UI\Components\Toggle;

class SubscriptionLimitTierResource extends Resource
{
    use AuthorizesResourcePermissions;

    protected static ?string $slug = 'system/subscription-limits';

    public static function model(): string
    {
        return get_model('subscription_limit_tier');
    }

    public function fields(): array
    {
        return [
            'key' => __('svarium::messages.Key'),
            'name' => __('svarium::messages.Name'),
            'description' => __('svarium::messages.Description'),
            'min_value' => __('svarium::messages.Min'),
            'max_value' => __('svarium::messages.Max'),
            'is_unlimited' => __('svarium::messages.No limit'),
            'price_delta' => __('svarium::messages.Price delta'),
            'currency' => __('svarium::messages.Currency'),
            'status' => __('svarium::messages.Status'),
            'sort' => __('svarium::messages.Sort'),
        ];
    }

    public function form(?Model $record = null): array
    {
        return [
            Input::make('name')
                ->label(__('svarium::messages.Name'))
                ->required(),
            Input::make('key')
                ->label(__('svarium::messages.Key')),
            Input::make('description')
                ->label(__('svarium::messages.Description')),
            Input::make('min_value')
                ->label(__('svarium::messages.Min'))
                ->type('number')
                ->nullable()
                ->value($record ? (string) ($record->min_value ?? '') : ''),
            Input::make('max_value')
                ->label(__('svarium::messages.Max'))
                ->type('number')
                ->nullable()
                ->value($record ? (string) ($record->max_value ?? '') : ''),
            Toggle::make('is_unlimited')
                ->label(__('svarium::messages.No limit'))
                ->value($record ? (bool) ($record->is_unlimited ?? false) : false),
            Input::make('price_delta')
                ->label(__('svarium::messages.Price delta'))
                ->type('number')
                ->step('0.01')
                ->value($record ? (string) ($record->price_delta ?? '0.00') : '0.00'),
            Input::make('currency')
                ->label(__('svarium::messages.Currency'))
                ->value($record ? (string) ($record->currency ?? 'PLN') : 'PLN'),
            Toggle::make('status')
                ->label(__('svarium::messages.Status'))
                ->value($record ? (bool) ($record->status ?? true) : true),
            Input::make('sort')
                ->label(__('svarium::messages.Sort'))
                ->type('number')
                ->value($record ? (string) ($record->sort ?? 0) : '0'),
        ];
    }

    public function createTitle(PanelContext $context): string
    {
        return __('svarium::messages.Create subscription limit');
    }

    public function editTitle(PanelContext $context, Model $record): string
    {
        return __('svarium::messages.Edit subscription limit');
    }

    public function beforeSave(Model $model, array &$data): void
    {
        $name = trim((string) ($data['name'] ?? ''));
        $key = trim((string) ($data['key'] ?? ''));

        if ($key === '') {
            $key = Str::slug($name, '_');
        }
        if ($key === '') {
            $key = 'limit_'.Str::lower((string) Str::ulid());
        }

        $isUnlimited = $this->toBool($data['is_unlimited'] ?? false);
        $minValue = $this->toNullableInt($data['min_value'] ?? null);
        $maxValue = $this->toNullableInt($data['max_value'] ?? null);

        if ($isUnlimited) {
            $maxValue = null;
        } elseif ($minValue !== null && $maxValue !== null && $minValue > $maxValue) {
            [$minValue, $maxValue] = [$maxValue, $minValue];
        }

        $currency = strtoupper(trim((string) ($data['currency'] ?? 'PLN')));
        if ($currency === '') {
            $currency = 'PLN';
        }

        $data['name'] = $name !== '' ? $name : $key;
        $data['key'] = $key;
        $data['min_value'] = $minValue;
        $data['max_value'] = $maxValue;
        $data['is_unlimited'] = $isUnlimited;
        $data['price_delta'] = $this->toPrice($data['price_delta'] ?? 0);
        $data['currency'] = substr($currency, 0, 3);
        $data['status'] = $this->toBool($data['status'] ?? true);
        $data['sort'] = max(0, (int) ($data['sort'] ?? 0));
    }

    public function table(): TableBuilder
    {
        return Table::make(SubscriptionLimitTiersTable::class);
    }

    public function canList(PanelContext $context): bool
    {
        return $this->canResourceAction($context, 'list');
    }

    public function canCreate(PanelContext $context): bool
    {
        return $this->canResourceAction($context, 'create');
    }

    public function canEdit(PanelContext $context): bool
    {
        return $this->canResourceAction($context, 'edit');
    }

    public function canDelete(PanelContext $context): bool
    {
        return $this->canResourceAction($context, 'delete');
    }

    public function canImport(PanelContext $context): bool
    {
        return false;
    }

    protected function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    protected function toNullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        return is_numeric($normalized) ? max(0, (int) $normalized) : null;
    }

    protected function toPrice(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $normalized = str_replace(',', '.', trim((string) $value));

        return is_numeric($normalized)
            ? round((float) $normalized, 2)
            : 0.0;
    }
}
