<?php

namespace Upsoftware\Svarium\Modules\Builtin\Subscriptions\Panel;

use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\Form\Input;
use Upsoftware\Svarium\UI\Components\Form\Select;
use Upsoftware\Svarium\UI\Components\RadioGroup;
use Upsoftware\Svarium\UI\Components\Repeater;
use Upsoftware\Svarium\UI\Components\Tab;
use Upsoftware\Svarium\UI\Components\TabItem;
use Upsoftware\Svarium\UI\Components\Text;
use Upsoftware\Svarium\UI\Components\Toggle;

class SubscriptionsOperation extends Operation
{
    public static string|array $panels = '*';

    public static function uri(): string
    {
        return 'system/subscriptions';
    }

    public static function methods(): array
    {
        return ['GET', 'POST'];
    }

    public function execution(): ExecutionMode
    {
        return ExecutionMode::FORM;
    }

    protected function submitLabel(): string
    {
        return __('svarium::messages.Save subscription configuration');
    }

    public function title(): string
    {
        return (string) svarium_label('modules.subscriptions.plural', __('svarium::messages.Subscriptions'));
    }

    public function rules(): array
    {
        return [
            'catalog_mode' => ['required', 'in:plan,package'],
            'catalog' => ['nullable', 'array'],
            'pricing' => ['nullable', 'array'],
            'periods' => ['nullable', 'array'],
        ];
    }

    public function schema(PanelContext $context): array
    {
        $settings = $this->loadSettings();

        $mode = $this->resolveCatalogMode(
            $context->input('catalog_mode', $settings['catalog_mode'] ?? 'plan')
        );

        $catalogValues = $this->normalizeRows(
            $context->input('catalog', $settings[$mode === 'package' ? 'packages' : 'plans'] ?? []),
            $mode
        );

        $pricingValues = $this->normalizeRows(
            $context->input('pricing', $settings['pricing'] ?? []),
            'pricing'
        );

        $periodValues = $this->normalizeRows(
            $context->input('periods', $settings['periods'] ?? $this->defaultPeriods()),
            'periods'
        );

        $catalogOptions = $this->catalogOptions($catalogValues);
        $periodOptions = $this->periodOptions($periodValues);
        $catalogTabLabel = $mode === 'package'
            ? __('svarium::messages.Packages')
            : __('svarium::messages.Plans');

        return [
            Tab::make('subscriptions_tabs')
                ->prop('defaultValue', 'configuration')
                ->children([
                    TabItem::make(__('svarium::messages.Configuration'))
                        ->prop('value', 'configuration')
                        ->content([
                            Block::make()
                                ->appearance('space-y-3')
                                ->children([
                                    Text::make(__('svarium::messages.Choose how subscription pricing is built.'))
                                        ->appearance('text-sm text-muted-foreground'),
                                    RadioGroup::make('catalog_mode')
                                        ->label(__('svarium::messages.Subscription pricing model'))
                                        ->defaultValue($mode)
                                        ->options([
                                            ['value' => 'plan', 'label' => __('svarium::messages.Plan pricing')],
                                            ['value' => 'package', 'label' => __('svarium::messages.Package pricing')],
                                        ]),
                                    Repeater::make('periods')
                                        ->label(__('svarium::messages.Period'), __('svarium::messages.Period unit'), __('svarium::messages.Active'))
                                        ->mode('table')
                                        ->showLabels(true)
                                        ->searchable()
                                        ->empty(__('svarium::messages.No entries defined'))
                                        ->addLabel(__('svarium::messages.Add period'))
                                        ->values($periodValues)
                                        ->template([
                                            Input::make('period_value')
                                                ->type('number')
                                                ->required(),
                                            Select::make('period_unit')
                                                ->options([
                                                    ['value' => 'day', 'label' => __('svarium::messages.Day')],
                                                    ['value' => 'month', 'label' => __('svarium::messages.Month')],
                                                    ['value' => 'quarter', 'label' => __('svarium::messages.Quarter')],
                                                    ['value' => 'year', 'label' => __('svarium::messages.Year')],
                                                ]),
                                            Toggle::make('status')
                                                ->value(true),
                                        ]),
                                ]),
                        ]),
                    TabItem::make($catalogTabLabel)
                        ->prop('value', 'catalog')
                        ->content([
                            Repeater::make('catalog')
                                ->label($catalogTabLabel)
                                ->mode('table')
                                ->showLabels(true)
                                ->searchable()
                                ->empty(__('svarium::messages.No entries defined'))
                                ->addLabel($mode === 'package'
                                    ? __('svarium::messages.Add package')
                                    : __('svarium::messages.Add plan'))
                                ->values($catalogValues)
                                ->template([
                                    Input::make('key')
                                        ->label(__('svarium::messages.Key'))
                                        ->required(),
                                    Input::make('name')
                                        ->label(__('svarium::messages.Name'))
                                        ->required(),
                                    Input::make('description')
                                        ->label(__('svarium::messages.Description')),
                                    Input::make('limit')
                                        ->label(__('svarium::messages.Limit'))
                                        ->type('number'),
                                    Input::make('sort')
                                        ->label(__('svarium::messages.Sort'))
                                        ->type('number')
                                        ->value('0'),
                                    Toggle::make('status')
                                        ->label(__('svarium::messages.Active'))
                                        ->value(true),
                                ]),
                        ]),
                    TabItem::make(__('svarium::messages.Pricing'))
                        ->prop('value', 'pricing')
                        ->content([
                            Repeater::make('pricing')
                                ->label(__('svarium::messages.Pricing'))
                                ->mode('table')
                                ->showLabels(true)
                                ->searchable()
                                ->empty(__('svarium::messages.No entries defined'))
                                ->addLabel(__('svarium::messages.Add price'))
                                ->values($pricingValues)
                                ->template([
                                    Select::make('catalog_key')
                                        ->label($mode === 'package'
                                            ? __('svarium::messages.Package key')
                                            : __('svarium::messages.Plan key'))
                                        ->options($catalogOptions),
                                    Select::make('period_key')
                                        ->label(__('svarium::messages.Period key'))
                                        ->options($periodOptions),
                                    Input::make('price')
                                        ->label(__('svarium::messages.Price'))
                                        ->type('number')
                                        ->step('0.01')
                                        ->required(),
                                    Input::make('currency')
                                        ->label(__('svarium::messages.Currency'))
                                        ->value((string) ($settings['currency'] ?? 'PLN')),
                                    Input::make('sort')
                                        ->label(__('svarium::messages.Sort'))
                                        ->type('number')
                                        ->value('0'),
                                    Toggle::make('status')
                                        ->label(__('svarium::messages.Active'))
                                        ->value(true),
                                ]),
                        ]),
                ]),
        ];
    }

    protected function save(PanelContext $context): RedirectResult
    {
        $currentSettings = $this->loadSettings();
        $mode = $this->resolveCatalogMode($context->validated()['catalog_mode'] ?? 'plan');

        $catalogRows = $this->normalizeRows((array) $context->input('catalog', []), $mode);
        $pricingRows = $this->normalizeRows((array) $context->input('pricing', []), 'pricing');
        $periodRows = $this->normalizeRows((array) $context->input('periods', []), 'periods');

        if ($periodRows === []) {
            $periodRows = $this->defaultPeriods();
        }

        $payload = [
            'catalog_mode' => $mode,
            'plans' => $mode === 'plan'
                ? $catalogRows
                : $this->normalizeRows((array) ($currentSettings['plans'] ?? []), 'plan'),
            'packages' => $mode === 'package'
                ? $catalogRows
                : $this->normalizeRows((array) ($currentSettings['packages'] ?? []), 'package'),
            'pricing' => $pricingRows,
            'periods' => $periodRows,
        ];

        $settingModel = $this->settingModelClass();
        if (is_string($settingModel) && class_exists($settingModel) && method_exists($settingModel, 'setSettingGlobal')) {
            $settingModel::setSettingGlobal('subscriptions.config', $payload, true);
        }

        return RedirectResult::to(panel_href('system/subscriptions', $context->panel()->name))
            ->success(__('svarium::messages.Subscription configuration has been saved.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadSettings(): array
    {
        $settingModel = $this->settingModelClass();
        if (! is_string($settingModel) || ! class_exists($settingModel) || ! method_exists($settingModel, 'getSettingGlobal')) {
            return [];
        }

        $value = $settingModel::getSettingGlobal('subscriptions.config', []);

        return is_array($value) ? $value : [];
    }

    protected function settingModelClass(): string
    {
        return get_model('setting');
    }

    protected function resolveCatalogMode(mixed $mode): string
    {
        $normalized = strtolower(trim((string) $mode));

        return in_array($normalized, ['plan', 'package'], true) ? $normalized : 'plan';
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeRows(array $rows, string $group): array
    {
        if ($group === 'periods') {
            return $this->normalizePeriodRows($rows);
        }

        $normalized = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $payload = [];
            foreach ($row as $key => $value) {
                if (! is_string($key) || trim($key) === '') {
                    continue;
                }

                $payload[$key] = $this->normalizeRowValue($key, $value);
            }

            if ($payload === []) {
                continue;
            }

            $key = trim((string) ($payload['key'] ?? ''));
            $name = trim((string) ($payload['name'] ?? ''));

            if (($group === 'plan' || $group === 'package') && $key === '' && $name === '') {
                continue;
            }

            if ($group === 'pricing') {
                $catalogKey = trim((string) ($payload['catalog_key'] ?? ''));
                $periodKey = trim((string) ($payload['period_key'] ?? ''));
                $price = trim((string) ($payload['price'] ?? ''));

                if ($catalogKey === '' && $periodKey === '' && $price === '') {
                    continue;
                }
            }

            $normalized[] = $payload;
        }

        return array_values($normalized);
    }

    protected function normalizeRowValue(string $key, mixed $value): mixed
    {
        if (in_array($key, ['status', 'active'], true)) {
            return $this->toBool($value);
        }

        if (in_array($key, ['sort', 'months', 'limit'], true)) {
            return is_numeric($value) ? (int) $value : 0;
        }

        if ($key === 'price') {
            $normalized = str_replace(',', '.', trim((string) $value));

            return is_numeric($normalized) ? number_format((float) $normalized, 2, '.', '') : '0.00';
        }

        if (is_array($value)) {
            return $value;
        }

        return trim((string) $value);
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function normalizePeriodRows(array $rows): array
    {
        $normalized = [];
        $usedKeys = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $periodRaw = $row['period_value'] ?? $row['period'] ?? $row['value'] ?? $row['months'] ?? null;
            $periodValue = is_numeric($periodRaw) ? (int) $periodRaw : 0;
            if ($periodValue <= 0) {
                continue;
            }

            $unit = strtolower(trim((string) ($row['period_unit'] ?? $row['unit'] ?? (isset($row['months']) ? 'month' : ''))));
            if (! in_array($unit, ['day', 'month', 'quarter', 'year'], true)) {
                $unit = 'month';
            }

            $status = $this->toBool($row['status'] ?? true);
            $key = trim((string) ($row['key'] ?? ''));

            if ($key === '') {
                $key = $periodValue.'_'.$unit;
            }

            $baseKey = $key;
            $suffix = 2;
            while (in_array($key, $usedKeys, true)) {
                $key = $baseKey.'_'.$suffix;
                $suffix++;
            }

            $usedKeys[] = $key;

            $normalized[] = [
                'key' => $key,
                'period_value' => $periodValue,
                'period_unit' => $unit,
                'status' => $status,
            ];
        }

        return array_values($normalized);
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

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{value:string,label:string}>
     */
    protected function catalogOptions(array $rows): array
    {
        $options = [];

        foreach ($rows as $row) {
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $options[] = [
                'value' => $key,
                'label' => $name !== '' ? $name : $key,
            ];
        }

        return $options;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{value:string,label:string}>
     */
    protected function periodOptions(array $rows): array
    {
        $rows = $this->normalizePeriodRows($rows);
        $options = [];

        foreach ($rows as $row) {
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $periodValue = (int) ($row['period_value'] ?? 0);
            $unit = trim((string) ($row['period_unit'] ?? 'month'));
            $label = $periodValue.' '.$this->periodUnitLabel($unit, $periodValue);
            $isActive = $this->toBool($row['status'] ?? true);

            $options[] = [
                'value' => $key,
                'label' => $label,
                'disabled' => ! $isActive,
            ];
        }

        if ($options !== []) {
            return $options;
        }

        return $this->periodOptions($this->defaultPeriods());
    }

    protected function periodUnitLabel(string $unit, int $value): string
    {
        $single = $value === 1;

        return match (strtolower(trim($unit))) {
            'day' => $single ? __('svarium::messages.Day') : __('svarium::messages.Days'),
            'quarter' => $single ? __('svarium::messages.Quarter') : __('svarium::messages.Quarters'),
            'year' => $single ? __('svarium::messages.Year') : __('svarium::messages.Years'),
            default => $single ? __('svarium::messages.Month') : __('svarium::messages.Months'),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function defaultPeriods(): array
    {
        return [
            ['key' => '1_month', 'period_value' => 1, 'period_unit' => 'month', 'status' => true],
            ['key' => '3_month', 'period_value' => 3, 'period_unit' => 'month', 'status' => true],
            ['key' => '6_month', 'period_value' => 6, 'period_unit' => 'month', 'status' => true],
            ['key' => '12_month', 'period_value' => 12, 'period_unit' => 'month', 'status' => true],
        ];
    }
}
