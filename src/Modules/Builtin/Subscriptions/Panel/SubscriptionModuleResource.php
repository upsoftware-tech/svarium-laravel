<?php

namespace Upsoftware\Svarium\Modules\Builtin\Subscriptions\Panel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Upsoftware\Svarium\Modules\Builtin\Subscriptions\Tables\SubscriptionModulesTable;
use Upsoftware\Svarium\Modules\Builtin\Support\AuthorizesResourcePermissions;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Resource;
use Upsoftware\Svarium\Panel\Table\Table;
use Upsoftware\Svarium\Panel\Table\TableBuilder;
use Upsoftware\Svarium\UI\Components\Form\Input;
use Upsoftware\Svarium\UI\Components\Form\Select;
use Upsoftware\Svarium\UI\Components\Toggle;

class SubscriptionModuleResource extends Resource
{
    use AuthorizesResourcePermissions;

    protected static ?string $slug = 'system/subscription-modules';

    public static function model(): string
    {
        return get_model('subscription_module');
    }

    public function fields(): array
    {
        return [
            'key' => __('svarium::messages.Key'),
            'name' => __('svarium::messages.Name'),
            'description' => __('svarium::messages.Description'),
            'base_price' => __('svarium::messages.Base price'),
            'currency' => __('svarium::messages.Currency'),
            'billing_period' => __('svarium::messages.Billing period'),
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
            Input::make('base_price')
                ->label(__('svarium::messages.Base price'))
                ->type('number')
                ->step('0.01')
                ->value($record ? (string) ($record->base_price ?? '0.00') : '0.00'),
            Input::make('currency')
                ->label(__('svarium::messages.Currency'))
                ->value($record ? (string) ($record->currency ?? 'PLN') : 'PLN'),
            Select::make('billing_period')
                ->label(__('svarium::messages.Billing period'))
                ->options([
                    ['value' => 'month', 'label' => __('svarium::messages.Monthly')],
                    ['value' => 'year', 'label' => __('svarium::messages.Yearly')],
                    ['value' => 'one_time', 'label' => __('svarium::messages.One-time')],
                ])
                ->value($record ? (string) ($record->billing_period ?? 'month') : 'month'),
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
        return __('svarium::messages.Create subscription module');
    }

    public function editTitle(PanelContext $context, Model $record): string
    {
        return __('svarium::messages.Edit subscription module');
    }

    public function beforeSave(Model $model, array &$data): void
    {
        $name = trim((string) ($data['name'] ?? ''));
        $key = trim((string) ($data['key'] ?? ''));

        if ($key === '') {
            $key = Str::slug($name, '_');
        }
        if ($key === '') {
            $key = 'module_'.Str::lower((string) Str::ulid());
        }

        $currency = strtoupper(trim((string) ($data['currency'] ?? 'PLN')));
        if ($currency === '') {
            $currency = 'PLN';
        }

        $billingPeriod = trim((string) ($data['billing_period'] ?? 'month'));
        if (! in_array($billingPeriod, ['month', 'year', 'one_time'], true)) {
            $billingPeriod = 'month';
        }

        $data['name'] = $name !== '' ? $name : $key;
        $data['key'] = $key;
        $data['status'] = $this->toBool($data['status'] ?? true);
        $data['base_price'] = $this->toPrice($data['base_price'] ?? 0);
        $data['currency'] = substr($currency, 0, 3);
        $data['billing_period'] = $billingPeriod;
        $data['sort'] = max(0, (int) ($data['sort'] ?? 0));
    }

    public function table(): TableBuilder
    {
        return Table::make(SubscriptionModulesTable::class);
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
