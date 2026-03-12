<?php

namespace Upsoftware\Svarium\Modules\Builtin\Subscriptions\Panel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Upsoftware\Svarium\Modules\Builtin\Subscriptions\Tables\TenantSubscriptionsTable;
use Upsoftware\Svarium\Modules\Builtin\Support\AuthorizesResourcePermissions;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Resource;
use Upsoftware\Svarium\Panel\Table\Table;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\Form\Input;
use Upsoftware\Svarium\UI\Components\Form\Select;
use Upsoftware\Svarium\UI\Components\Repeater;

class TenantSubscriptionResource extends Resource
{
    use AuthorizesResourcePermissions;

    protected static ?string $slug = 'system/subscriptions';

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $pendingItems = [];

    public static function model(): string
    {
        return get_model('tenant_subscription');
    }

    public function fields(): array
    {
        return [
            'tenant_id' => __('svarium::messages.Tenant ID'),
            'customer_name' => __('svarium::messages.Customer name'),
            'customer_email' => __('svarium::messages.Customer email'),
            'status' => __('svarium::messages.Status'),
            'starts_at' => __('svarium::messages.Starts at'),
            'ends_at' => __('svarium::messages.Ends at'),
            'billing_period' => __('svarium::messages.Billing period'),
            'currency' => __('svarium::messages.Currency'),
            'total_price' => __('svarium::messages.Total price'),
            'items' => __('svarium::messages.Modules in subscription'),
        ];
    }

    public function form(?Model $record = null): array
    {
        return [
            Input::make('tenant_id')
                ->label(__('svarium::messages.Tenant ID'))
                ->value($record ? (string) ($record->tenant_id ?? '') : ''),
            Input::make('customer_name')
                ->label(__('svarium::messages.Customer name'))
                ->required()
                ->value($record ? (string) ($record->customer_name ?? '') : ''),
            Input::make('customer_email')
                ->label(__('svarium::messages.Customer email'))
                ->type('email')
                ->email()
                ->nullable()
                ->value($record ? (string) ($record->customer_email ?? '') : ''),
            Select::make('status')
                ->label(__('svarium::messages.Status'))
                ->options([
                    ['value' => 'trialing', 'label' => __('svarium::messages.Trialing')],
                    ['value' => 'active', 'label' => __('svarium::messages.Active')],
                    ['value' => 'paused', 'label' => __('svarium::messages.Paused')],
                    ['value' => 'canceled', 'label' => __('svarium::messages.Canceled')],
                    ['value' => 'expired', 'label' => __('svarium::messages.Expired')],
                ])
                ->value($record ? (string) ($record->status ?? 'active') : 'active'),
            Input::make('starts_at')
                ->label(__('svarium::messages.Starts at'))
                ->type('date')
                ->nullable()
                ->value($record ? $this->formatDateValue($record->getAttribute('starts_at')) : ''),
            Input::make('ends_at')
                ->label(__('svarium::messages.Ends at'))
                ->type('date')
                ->nullable()
                ->value($record ? $this->formatDateValue($record->getAttribute('ends_at')) : ''),
            Select::make('billing_period')
                ->label(__('svarium::messages.Billing period'))
                ->options([
                    ['value' => 'month', 'label' => __('svarium::messages.Monthly')],
                    ['value' => 'year', 'label' => __('svarium::messages.Yearly')],
                    ['value' => 'one_time', 'label' => __('svarium::messages.One-time')],
                ])
                ->value($record ? (string) ($record->billing_period ?? 'month') : 'month'),
            Input::make('currency')
                ->label(__('svarium::messages.Currency'))
                ->value($record ? (string) ($record->currency ?? 'PLN') : 'PLN'),
            Repeater::make('items')
                ->label(__('svarium::messages.Modules in subscription'))
                ->mode('table')
                ->searchable()
                ->showLabels(true)
                ->empty(__('svarium::messages.No modules selected'))
                ->addLabel(__('svarium::messages.Add module'))
                ->values($record ? $this->formatItemsForRepeater($record) : [])
                ->template([
                    Select::make('subscription_module_id')
                        ->label(__('svarium::messages.Module'))
                        ->options($this->moduleOptions())
                        ->required(),
                    Select::make('subscription_limit_tier_id')
                        ->label(__('svarium::messages.Limit profile'))
                        ->options($this->limitTierOptions()),
                    Input::make('module_limit')
                        ->label(__('svarium::messages.Custom limit'))
                        ->type('number')
                        ->nullable(),
                    Input::make('quantity')
                        ->label(__('svarium::messages.Qty'))
                        ->type('number')
                        ->min(1)
                        ->value('1'),
                    Input::make('unit_price')
                        ->label(__('svarium::messages.Unit price'))
                        ->type('number')
                        ->step('0.01')
                        ->nullable(),
                ]),
        ];
    }

    public function createTitle(PanelContext $context): string
    {
        return __('svarium::messages.Create subscription');
    }

    public function editTitle(PanelContext $context, Model $record): string
    {
        return __('svarium::messages.Edit subscription');
    }

    public function beforeSave(Model $model, array &$data): void
    {
        $tenantId = trim((string) ($data['tenant_id'] ?? ''));
        $customerName = trim((string) ($data['customer_name'] ?? ''));
        $customerEmail = trim((string) ($data['customer_email'] ?? ''));
        $status = trim((string) ($data['status'] ?? 'active'));
        $billingPeriod = trim((string) ($data['billing_period'] ?? 'month'));
        $currency = strtoupper(trim((string) ($data['currency'] ?? 'PLN')));

        if ($currency === '') {
            $currency = 'PLN';
        }

        if (! in_array($status, ['trialing', 'active', 'paused', 'canceled', 'expired'], true)) {
            $status = 'active';
        }

        if (! in_array($billingPeriod, ['month', 'year', 'one_time'], true)) {
            $billingPeriod = 'month';
        }

        $startsAt = $this->normalizeDate($data['starts_at'] ?? null);
        $endsAt = $this->normalizeDate($data['ends_at'] ?? null);

        $normalizedItems = $this->normalizeSubmittedItems($data['items'] ?? []);
        $this->pendingItems = $normalizedItems;

        unset($data['items']);

        $totalPrice = array_reduce($normalizedItems, static function (float $carry, array $row): float {
            return $carry + (float) ($row['total_price'] ?? 0);
        }, 0.0);

        $data['tenant_id'] = $tenantId !== '' ? $tenantId : null;
        $data['customer_name'] = $customerName;
        $data['customer_email'] = $customerEmail !== '' ? $customerEmail : null;
        $data['status'] = $status;
        $data['starts_at'] = $startsAt;
        $data['ends_at'] = $endsAt;
        $data['billing_period'] = $billingPeriod;
        $data['currency'] = substr($currency, 0, 3);
        $data['total_price'] = round($totalPrice, 2);
    }

    public function afterSave(Model $model): void
    {
        if (! method_exists($model, 'items')) {
            return;
        }

        $model->items()->delete();

        if ($this->pendingItems === []) {
            return;
        }

        $model->items()->createMany($this->pendingItems);
    }

    public function table(): TableBuilder
    {
        return Table::make(TenantSubscriptionsTable::class);
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

    protected function formatDateValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $string = trim((string) $value);
        if ($string === '') {
            return '';
        }

        $timestamp = strtotime($string);

        return $timestamp === false
            ? ''
            : date('Y-m-d', $timestamp);
    }

    /**
     * @return array<int, array{value: string|int, label: string}>
     */
    protected function moduleOptions(): array
    {
        $modelClass = get_model('subscription_module');
        $rows = $modelClass::query()
            ->where('status', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->get(['id', 'name', 'key']);

        return $rows->map(static function (Model $row): array {
            $name = trim((string) ($row->getAttribute('name') ?? ''));
            $key = trim((string) ($row->getAttribute('key') ?? ''));
            $label = $name !== '' ? $name : $key;

            if ($key !== '') {
                $label .= " ({$key})";
            }

            return [
                'value' => (string) $row->getKey(),
                'label' => $label,
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array{value: string|int, label: string}>
     */
    protected function limitTierOptions(): array
    {
        $modelClass = get_model('subscription_limit_tier');
        $rows = $modelClass::query()
            ->where('status', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->get(['id', 'name', 'max_value', 'is_unlimited']);

        $options = [[
            'value' => '',
            'label' => __('svarium::messages.None'),
        ]];

        foreach ($rows as $row) {
            $name = trim((string) ($row->getAttribute('name') ?? ''));
            $isUnlimited = (bool) ($row->getAttribute('is_unlimited') ?? false);
            $maxValue = $row->getAttribute('max_value');

            if ($isUnlimited) {
                $label = $name !== '' ? "{$name} (∞)" : '∞';
            } elseif ($maxValue !== null && $maxValue !== '') {
                $label = $name !== '' ? "{$name} ({$maxValue})" : (string) $maxValue;
            } else {
                $label = $name !== '' ? $name : '-';
            }

            $options[] = [
                'value' => (string) $row->getKey(),
                'label' => $label,
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function formatItemsForRepeater(Model $record): array
    {
        if (! method_exists($record, 'items')) {
            return [];
        }

        $items = $record->items()
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return $items->map(static function (Model $item): array {
            return [
                'subscription_module_id' => (string) ($item->getAttribute('subscription_module_id') ?? ''),
                'subscription_limit_tier_id' => (string) ($item->getAttribute('subscription_limit_tier_id') ?? ''),
                'module_limit' => $item->getAttribute('module_limit'),
                'quantity' => (int) ($item->getAttribute('quantity') ?? 1),
                'unit_price' => $item->getAttribute('unit_price'),
            ];
        })->values()->all();
    }

    /**
     * @param mixed $rawItems
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeSubmittedItems(mixed $rawItems): array
    {
        if ($rawItems instanceof Collection) {
            $rawItems = $rawItems->toArray();
        }

        if (! is_array($rawItems)) {
            return [];
        }

        $modules = $this->subscriptionModulesIndex();
        $limits = $this->subscriptionLimitTiersIndex();

        $normalized = [];

        foreach (array_values($rawItems) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $moduleId = $this->toNullableInt($item['subscription_module_id'] ?? null);
            if ($moduleId === null || ! isset($modules[$moduleId])) {
                continue;
            }

            $limitTierId = $this->toNullableInt($item['subscription_limit_tier_id'] ?? null);
            $limitTier = $limitTierId !== null && isset($limits[$limitTierId])
                ? $limits[$limitTierId]
                : null;

            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $basePrice = (float) ($modules[$moduleId]['base_price'] ?? 0);
            $priceDelta = (float) ($limitTier['price_delta'] ?? 0);
            $computedUnitPrice = round($basePrice + $priceDelta, 2);
            $submittedUnitPrice = $this->toNullablePrice($item['unit_price'] ?? null);
            $unitPrice = $submittedUnitPrice ?? $computedUnitPrice;

            $moduleLimit = $this->toNullableInt($item['module_limit'] ?? null);
            if ($moduleLimit === null && is_array($limitTier)) {
                if ((bool) ($limitTier['is_unlimited'] ?? false)) {
                    $moduleLimit = null;
                } else {
                    $moduleLimit = $this->toNullableInt($limitTier['max_value'] ?? null);
                }
            }

            $rowTotal = round($unitPrice * $quantity, 2);

            $normalized[] = [
                'subscription_module_id' => $moduleId,
                'subscription_limit_tier_id' => $limitTierId,
                'module_limit' => $moduleLimit,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $rowTotal,
                'status' => true,
                'sort' => $index,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function subscriptionModulesIndex(): array
    {
        $modelClass = get_model('subscription_module');

        return $modelClass::query()
            ->get(['id', 'base_price'])
            ->mapWithKeys(static function (Model $row): array {
                return [(int) $row->getKey() => [
                    'base_price' => (float) ($row->getAttribute('base_price') ?? 0),
                ]];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function subscriptionLimitTiersIndex(): array
    {
        $modelClass = get_model('subscription_limit_tier');

        return $modelClass::query()
            ->get(['id', 'price_delta', 'is_unlimited', 'max_value'])
            ->mapWithKeys(static function (Model $row): array {
                return [(int) $row->getKey() => [
                    'price_delta' => (float) ($row->getAttribute('price_delta') ?? 0),
                    'is_unlimited' => (bool) ($row->getAttribute('is_unlimited') ?? false),
                    'max_value' => $row->getAttribute('max_value'),
                ]];
            })
            ->all();
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d 00:00:00');
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        $timestamp = strtotime($normalized);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d 00:00:00', $timestamp);
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

        return is_numeric($normalized) ? (int) $normalized : null;
    }

    protected function toNullablePrice(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = str_replace(',', '.', trim((string) $value));
        if ($normalized === '') {
            return null;
        }

        return is_numeric($normalized)
            ? round((float) $normalized, 2)
            : null;
    }
}
