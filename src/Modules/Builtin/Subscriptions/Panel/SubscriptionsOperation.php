<?php

namespace Upsoftware\Svarium\Modules\Builtin\Subscriptions\Panel;

use Illuminate\Support\Str;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\Form\Input;
use Upsoftware\Svarium\UI\Components\Form\Select;
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
        return 'Subsckrypcje';
    }

    public function rules(): array
    {
        return [
            'enable_module_pricing' => ['nullable', 'boolean'],
            'enable_package_pricing' => ['nullable', 'boolean'],
            'offer_groups' => ['nullable', 'array'],
            'plans' => ['nullable', 'array'],
            'packages' => ['nullable', 'array'],
            'module_pricing' => ['nullable', 'array'],
            'package_pricing' => ['nullable', 'array'],
            'periods' => ['nullable', 'array'],
        ];
    }

    public function schema(PanelContext $context): array
    {
        $settings = $this->loadSettings();
        $pricingFlags = $this->resolvePricingFlags($settings);
        $modulePricingEnabled = $this->resolveBoolInput($context->input('enable_module_pricing'), $pricingFlags['module']);
        $packagePricingEnabled = $this->resolveBoolInput($context->input('enable_package_pricing'), $pricingFlags['package']);
        $legacyPricingRows = $this->normalizeRows((array) ($settings['pricing'] ?? []), 'pricing');
        $legacyCatalogMode = strtolower(trim((string) ($settings['catalog_mode'] ?? '')));
        $legacyPackageValues = $this->normalizeRows(
            $context->input('packages', $settings['packages'] ?? []),
            'package'
        );

        $offerGroupValues = $this->normalizeRows(
            $context->input('offer_groups', $settings['offer_groups'] ?? []),
            'offer_groups'
        );
        $offerGroupValues = $this->attachPackagesToOfferGroups($offerGroupValues, $legacyPackageValues);

        $planValues = $this->normalizeRows(
            $context->input('plans', $settings['plans'] ?? []),
            'plan'
        );

        $packageValues = $this->flattenPackagesFromOfferGroups($offerGroupValues);

        $modulePricingDefault = $settings['module_pricing'] ?? null;
        if (! is_array($modulePricingDefault)) {
            $modulePricingDefault = $this->filterPricingRowsByType($legacyPricingRows, 'module');
            if ($modulePricingDefault === [] && $legacyCatalogMode === 'plan') {
                $modulePricingDefault = $legacyPricingRows;
            }
        }

        $packagePricingDefault = $settings['package_pricing'] ?? null;
        if (! is_array($packagePricingDefault)) {
            $packagePricingDefault = $this->filterPricingRowsByType($legacyPricingRows, 'package');
            if ($packagePricingDefault === [] && $legacyCatalogMode === 'package') {
                $packagePricingDefault = $legacyPricingRows;
            }
        }

        $modulePricingValues = $this->normalizeRows(
            $context->input('module_pricing', $modulePricingDefault),
            'pricing'
        );

        $packagePricingValues = $this->normalizeRows(
            $context->input('package_pricing', $packagePricingDefault),
            'pricing'
        );

        $periodValues = $this->normalizeRows(
            $context->input('periods', $settings['periods'] ?? $this->defaultPeriods()),
            'periods'
        );

        $planOptions = $this->catalogOptions($planValues);
        $packageOptions = $this->catalogOptions($packageValues);
        $periodOptions = $this->periodOptions($periodValues);

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
                                    Text::make(__('svarium::messages.You can configure both module and package pricing in one subscription.'))
                                        ->appearance('text-sm text-muted-foreground'),
                                    Toggle::make('enable_module_pricing')
                                        ->label(__('svarium::messages.Enable module pricing'))
                                        ->hint(__('svarium::messages.Enable module catalog and module pricing list for subscriptions.'))
                                        ->value($modulePricingEnabled),
                                    Toggle::make('enable_package_pricing')
                                        ->label(__('svarium::messages.Enable package pricing'))
                                        ->hint(__('svarium::messages.Enable package catalog and package pricing list for subscriptions.'))
                                        ->value($packagePricingEnabled),
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
                    TabItem::make(__('svarium::messages.Modules'))
                        ->prop('value', 'plans')
                        ->content([
                            Repeater::make('plans')
                                ->label(__('svarium::messages.Modules'))
                                ->mode('table')
                                ->showLabels(true)
                                ->searchable()
                                ->empty(__('svarium::messages.No entries defined'))
                                ->addLabel(__('svarium::messages.Add module'))
                                ->values($planValues)
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
                    TabItem::make(__('svarium::messages.Packages'))
                        ->prop('value', 'packages')
                        ->content([
                            Repeater::make('offer_groups')
                                ->label(__('svarium::messages.Offer groups'))
                                ->mode('accordion')
                                ->searchable()
                                ->empty(__('svarium::messages.No entries defined'))
                                ->addLabel(__('svarium::messages.Add offer group'))
                                ->values($offerGroupValues)
                                ->template([
                                    Input::make('id')
                                        ->label(__('svarium::messages.Offer group ID')),
                                    Input::make('name')
                                        ->label(__('svarium::messages.Name'))
                                        ->required(),
                                    Input::make('sort')
                                        ->label(__('svarium::messages.Sort'))
                                        ->type('number')
                                        ->value('0'),
                                    Toggle::make('status')
                                        ->label(__('svarium::messages.Active'))
                                        ->value(true),
                                    Repeater::make('packages')
                                        ->label(__('svarium::messages.Packages'))
                                        ->mode('table')
                                        ->showLabels(true)
                                        ->searchable()
                                        ->empty(__('svarium::messages.No entries defined'))
                                        ->addLabel(__('svarium::messages.Add package'))
                                        ->template([
                                            Input::make('key')
                                                ->label(__('svarium::messages.Key'))
                                                ->required(),
                                            Input::make('name')
                                                ->label(__('svarium::messages.Name'))
                                                ->required(),
                                            Input::make('qty')
                                                ->label(__('svarium::messages.Quantity in package'))
                                                ->type('number')
                                                ->value('1'),
                                            Input::make('unit_label')
                                                ->label(__('svarium::messages.Unit label')),
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
                        ]),
                    TabItem::make(__('svarium::messages.Module pricing'))
                        ->prop('value', 'module_pricing')
                        ->if($modulePricingEnabled)
                        ->content([
                            Repeater::make('module_pricing')
                                ->label(__('svarium::messages.Module pricing'))
                                ->mode('table')
                                ->showLabels(true)
                                ->searchable()
                                ->empty(__('svarium::messages.No entries defined'))
                                ->addLabel(__('svarium::messages.Add price'))
                                ->values($modulePricingValues)
                                ->template([
                                    Select::make('catalog_key')
                                        ->label(__('svarium::messages.Module key'))
                                        ->options($planOptions),
                                    Select::make('period_key')
                                        ->label(__('svarium::messages.Period key'))
                                        ->options($periodOptions),
                                    Input::make('price_net')
                                        ->label(__('svarium::messages.Net price'))
                                        ->type('number')
                                        ->step('0.01')
                                        ->required(),
                                    Input::make('vat_rate')
                                        ->label(__('svarium::messages.VAT rate (%)'))
                                        ->type('number')
                                        ->step('0.01')
                                        ->value('23'),
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
                    TabItem::make(__('svarium::messages.Package pricing'))
                        ->prop('value', 'package_pricing')
                        ->if($packagePricingEnabled)
                        ->content([
                            Repeater::make('package_pricing')
                                ->label(__('svarium::messages.Package pricing'))
                                ->mode('table')
                                ->showLabels(true)
                                ->searchable()
                                ->empty(__('svarium::messages.No entries defined'))
                                ->addLabel(__('svarium::messages.Add price'))
                                ->values($packagePricingValues)
                                ->template([
                                    Select::make('catalog_key')
                                        ->label(__('svarium::messages.Package key'))
                                        ->options($packageOptions),
                                    Select::make('period_key')
                                        ->label(__('svarium::messages.Period key'))
                                        ->options($periodOptions),
                                    Input::make('price_net')
                                        ->label(__('svarium::messages.Net price'))
                                        ->type('number')
                                        ->step('0.01')
                                        ->required(),
                                    Input::make('vat_rate')
                                        ->label(__('svarium::messages.VAT rate (%)'))
                                        ->type('number')
                                        ->step('0.01')
                                        ->value('23'),
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
        $settings = $this->loadSettings();
        $pricingFlags = $this->resolvePricingFlags($settings);
        $modulePricingEnabled = $this->resolveBoolInput($context->input('enable_module_pricing'), $pricingFlags['module']);
        $packagePricingEnabled = $this->resolveBoolInput($context->input('enable_package_pricing'), $pricingFlags['package']);

        $planRows = $this->normalizeRows((array) $context->input('plans', []), 'plan');
        $legacyPackageRows = $this->normalizeRows((array) $context->input('packages', []), 'package');
        $offerGroupRows = $this->normalizeRows((array) $context->input('offer_groups', []), 'offer_groups');
        $offerGroupRows = $this->attachPackagesToOfferGroups($offerGroupRows, $legacyPackageRows);
        $packageRows = $this->flattenPackagesFromOfferGroups($offerGroupRows);
        $modulePricingRows = $this->normalizeRows((array) $context->input('module_pricing', []), 'pricing');
        $modulePricingRows = $this->tagPricingRows($modulePricingRows, 'module');
        $packagePricingRows = $this->normalizeRows((array) $context->input('package_pricing', []), 'pricing');
        $packagePricingRows = $this->tagPricingRows($packagePricingRows, 'package');
        $periodRows = $this->normalizeRows((array) $context->input('periods', []), 'periods');

        if ($periodRows === []) {
            $periodRows = $this->defaultPeriods();
        }

        $payload = [
            'catalog_mode' => $this->legacyCatalogModeFromFlags($modulePricingEnabled, $packagePricingEnabled),
            'enable_module_pricing' => $modulePricingEnabled,
            'enable_package_pricing' => $packagePricingEnabled,
            'offer_groups' => $offerGroupRows,
            'plans' => $planRows,
            'packages' => $packageRows,
            'module_pricing' => $modulePricingRows,
            'package_pricing' => $packagePricingRows,
            'pricing' => $this->legacyPricingFromFlags(
                $modulePricingRows,
                $packagePricingRows,
                $modulePricingEnabled,
                $packagePricingEnabled
            ),
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

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeRows(array $rows, string $group): array
    {
        if ($group === 'periods') {
            return $this->normalizePeriodRows($rows);
        }

        if ($group === 'offer_groups') {
            return $this->normalizeOfferGroupRows($rows);
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

            if ($group === 'package') {
                if (! array_key_exists('offer_group_id', $payload) && array_key_exists('offer_group', $payload)) {
                    $payload['offer_group_id'] = trim((string) $payload['offer_group']);
                }

                if (! array_key_exists('qty', $payload) || (int) ($payload['qty'] ?? 0) <= 0) {
                    $payload['qty'] = 1;
                }

                if (! array_key_exists('unit_label', $payload)) {
                    $payload['unit_label'] = '';
                }

                if (! array_key_exists('offer_group_id', $payload)) {
                    $payload['offer_group_id'] = '';
                }
            }

            if ($group === 'pricing') {
                $catalogKey = trim((string) ($payload['catalog_key'] ?? ''));
                $periodKey = trim((string) ($payload['period_key'] ?? ''));
                $price = trim((string) ($payload['price_net'] ?? $payload['price'] ?? ''));

                if ($catalogKey === '' && $periodKey === '' && $price === '') {
                    continue;
                }

                if (! array_key_exists('price_net', $payload) && array_key_exists('price', $payload)) {
                    $payload['price_net'] = $payload['price'];
                }

                if (! array_key_exists('vat_rate', $payload)) {
                    $payload['vat_rate'] = '23.00';
                }
            }

            $normalized[] = $payload;
        }

        return array_values($normalized);
    }

    protected function normalizeRowValue(string $key, mixed $value): mixed
    {
        if (in_array($key, ['offer_group', 'offer_group_id', 'id'], true)) {
            return trim((string) $value);
        }

        if ($key === 'catalog_type') {
            $normalized = strtolower(trim((string) $value));

            return in_array($normalized, ['module', 'package'], true) ? $normalized : '';
        }

        if (in_array($key, ['status', 'active'], true)) {
            return $this->toBool($value);
        }

        if (in_array($key, ['sort', 'months', 'limit', 'qty'], true)) {
            $intValue = is_numeric($value) ? (int) $value : 0;

            if ($key === 'qty') {
                return max(1, $intValue);
            }

            return $intValue;
        }

        if (in_array($key, ['price', 'price_net', 'vat_rate'], true)) {
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
    protected function normalizeOfferGroupRows(array $rows): array
    {
        $normalized = [];
        $usedIds = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = trim((string) ($row['id'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $rawPackages = $row['packages'] ?? [];
            $packagesInput = is_array($rawPackages) ? $rawPackages : [];

            if ($id === '' && $name === '' && $packagesInput === []) {
                continue;
            }

            if ($id === '' && $name !== '') {
                $id = (string) Str::slug($name, '_');
            }

            if ($id === '') {
                $id = 'group_'.(count($normalized) + 1);
            }

            $baseId = $id;
            $suffix = 2;
            while (in_array($id, $usedIds, true)) {
                $id = $baseId.'_'.$suffix;
                $suffix++;
            }

            $usedIds[] = $id;
            $packages = $this->normalizeRows($packagesInput, 'package');
            $packages = $this->assignOfferGroupToPackages($packages, $id);

            $normalized[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : $id,
                'sort' => is_numeric($row['sort'] ?? null) ? (int) $row['sort'] : 0,
                'status' => $this->toBool($row['status'] ?? true),
                'packages' => $packages,
            ];
        }

        return array_values($normalized);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function assignOfferGroupToPackages(array $rows, string $groupId): array
    {
        $groupId = trim($groupId);
        if ($groupId === '') {
            return array_values($rows);
        }

        $assigned = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $row['offer_group_id'] = $groupId;
            $assigned[] = $row;
        }

        return array_values($assigned);
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @param  array<int, array<string, mixed>>  $legacyPackages
     * @return array<int, array<string, mixed>>
     */
    protected function attachPackagesToOfferGroups(array $groups, array $legacyPackages): array
    {
        $normalizedGroups = [];
        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $groupId = trim((string) ($group['id'] ?? ''));
            if ($groupId === '') {
                continue;
            }

            $group['packages'] = $this->assignOfferGroupToPackages(
                $this->normalizeRows((array) ($group['packages'] ?? []), 'package'),
                $groupId
            );

            $normalizedGroups[] = $group;
        }

        if ($legacyPackages === []) {
            return array_values($normalizedGroups);
        }

        $indexById = [];
        foreach ($normalizedGroups as $index => $group) {
            $groupId = trim((string) ($group['id'] ?? ''));
            if ($groupId !== '') {
                $indexById[$groupId] = $index;
            }
        }

        foreach ($legacyPackages as $package) {
            if (! is_array($package)) {
                continue;
            }

            $groupId = trim((string) ($package['offer_group_id'] ?? ''));
            if ($groupId === '') {
                $groupId = 'default';
            }

            if (! array_key_exists($groupId, $indexById)) {
                $normalizedGroups[] = [
                    'id' => $groupId,
                    'name' => $groupId === 'default'
                        ? 'Default'
                        : ucfirst(str_replace('_', ' ', $groupId)),
                    'sort' => count($normalizedGroups),
                    'status' => true,
                    'packages' => [],
                ];
                $indexById[$groupId] = count($normalizedGroups) - 1;
            }

            $package['offer_group_id'] = $groupId;
            $normalizedGroups[$indexById[$groupId]]['packages'][] = $package;
        }

        foreach ($normalizedGroups as $index => $group) {
            $groupId = trim((string) ($group['id'] ?? ''));
            if ($groupId === '') {
                continue;
            }

            $normalizedGroups[$index]['packages'] = $this->assignOfferGroupToPackages(
                $this->normalizeRows((array) ($group['packages'] ?? []), 'package'),
                $groupId
            );
        }

        return array_values($normalizedGroups);
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    protected function flattenPackagesFromOfferGroups(array $groups): array
    {
        $flattened = [];

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $groupId = trim((string) ($group['id'] ?? ''));
            $packages = $this->normalizeRows((array) ($group['packages'] ?? []), 'package');

            foreach ($packages as $package) {
                if (! is_array($package)) {
                    continue;
                }

                if ($groupId !== '') {
                    $package['offer_group_id'] = $groupId;
                }

                $flattened[] = $package;
            }
        }

        return array_values($flattened);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{module: bool, package: bool}
     */
    protected function resolvePricingFlags(array $settings): array
    {
        $hasModuleFlag = array_key_exists('enable_module_pricing', $settings);
        $hasPackageFlag = array_key_exists('enable_package_pricing', $settings);

        if ($hasModuleFlag || $hasPackageFlag) {
            return [
                'module' => $this->toBool($settings['enable_module_pricing'] ?? false),
                'package' => $this->toBool($settings['enable_package_pricing'] ?? false),
            ];
        }

        $legacyMode = strtolower(trim((string) ($settings['catalog_mode'] ?? '')));

        return match ($legacyMode) {
            'plan' => ['module' => true, 'package' => false],
            'package' => ['module' => false, 'package' => true],
            'none' => ['module' => false, 'package' => false],
            default => ['module' => true, 'package' => true],
        };
    }

    protected function resolveBoolInput(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_string($value) && trim($value) === '') {
            return $default;
        }

        return $this->toBool($value);
    }

    protected function legacyCatalogModeFromFlags(bool $moduleEnabled, bool $packageEnabled): string
    {
        if ($moduleEnabled && $packageEnabled) {
            return 'both';
        }

        if ($moduleEnabled) {
            return 'plan';
        }

        if ($packageEnabled) {
            return 'package';
        }

        return 'none';
    }

    /**
     * @param  array<int, array<string, mixed>>  $modulePricingRows
     * @param  array<int, array<string, mixed>>  $packagePricingRows
     * @return array<int, array<string, mixed>>
     */
    protected function legacyPricingFromFlags(
        array $modulePricingRows,
        array $packagePricingRows,
        bool $moduleEnabled,
        bool $packageEnabled
    ): array {
        if ($moduleEnabled && $packageEnabled) {
            return array_values(array_merge($modulePricingRows, $packagePricingRows));
        }

        if ($moduleEnabled) {
            return array_values($modulePricingRows);
        }

        if ($packageEnabled) {
            return array_values($packagePricingRows);
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function filterPricingRowsByType(array $rows, string $type): array
    {
        $expectedType = strtolower(trim($type));
        if (! in_array($expectedType, ['module', 'package'], true)) {
            return [];
        }

        $filtered = [];
        foreach ($rows as $row) {
            $rowType = strtolower(trim((string) ($row['catalog_type'] ?? '')));
            if ($rowType !== $expectedType) {
                continue;
            }

            $filtered[] = $row;
        }

        return array_values($filtered);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function tagPricingRows(array $rows, string $type): array
    {
        $normalizedType = strtolower(trim($type));
        if (! in_array($normalizedType, ['module', 'package'], true)) {
            return $rows;
        }

        $tagged = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $row['catalog_type'] = $normalizedType;
            $tagged[] = $row;
        }

        return array_values($tagged);
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
    protected function offerGroupOptions(array $rows): array
    {
        $options = [[
            'value' => '',
            'label' => __('svarium::messages.None'),
        ]];

        foreach ($rows as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $isActive = $this->toBool($row['status'] ?? true);
            $name = trim((string) ($row['name'] ?? ''));
            $label = $name !== '' ? $name : $id;

            $options[] = [
                'value' => $id,
                'label' => $label,
                'disabled' => ! $isActive,
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
