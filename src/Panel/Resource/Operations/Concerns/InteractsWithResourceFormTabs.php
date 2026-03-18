<?php

namespace Upsoftware\Svarium\Panel\Resource\Operations\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Upsoftware\Svarium\Layouts\Panel\FormTabLayout;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Resource\ResourceFormTab;
use Upsoftware\Svarium\Panel\Resource\ResourceFormTabDefinition;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\FieldComponent;
use Upsoftware\Svarium\UI\Components\Flex;
use Upsoftware\Svarium\UI\Components\Form\Select;
use Upsoftware\Svarium\UI\Components\LocaleInline;
use Upsoftware\Svarium\UI\Components\Tab;
use Upsoftware\Svarium\UI\Components\Text;

trait InteractsWithResourceFormTabs
{
    /**
     * @return array<int, ResourceFormTab>
     */
    protected function resolveCreateFormTabs(PanelContext $context): array
    {
        $resource = $this->resource();
        $tabs = $resource->createTabs($context);

        return $this->filterVisibleFormTabs($tabs, $context);
    }

    /**
     * @return array<int, ResourceFormTab>
     */
    protected function resolveEditFormTabs(PanelContext $context, Model $record): array
    {
        $resource = $this->resource();
        $tabs = $resource->editTabs($context, $record);

        return $this->filterVisibleFormTabs($tabs, $context, $record);
    }

    /**
     * @param  array<int, mixed>  $tabs
     * @return array<int, ResourceFormTab>
     */
    protected function filterVisibleFormTabs(array $tabs, PanelContext $context, ...$args): array
    {
        $record = null;
        foreach ($args as $arg) {
            if ($arg instanceof Model) {
                $record = $arg;
                break;
            }
        }

        $resolved = [];

        foreach ($tabs as $tab) {
            $tabObject = $this->resolveFormTabObject($tab, $context, $record);

            if (! $tabObject instanceof ResourceFormTab) {
                continue;
            }

            if (! $tabObject->shouldRender($context, ...$args)) {
                continue;
            }

            $resolved[] = $tabObject;
        }

        return array_values($resolved);
    }

    protected function resolveFormTabObject(
        mixed $tab,
        PanelContext $context,
        ?Model $record = null
    ): ?ResourceFormTab {
        if ($tab instanceof ResourceFormTab) {
            return $tab;
        }

        if ($tab instanceof ResourceFormTabDefinition) {
            return $tab::make($context, $record);
        }

        if (is_string($tab)) {
            $normalizedClass = trim($tab);

            if ($normalizedClass === '') {
                return null;
            }

            if (! class_exists($normalizedClass)) {
                return null;
            }

            if (! is_subclass_of($normalizedClass, ResourceFormTabDefinition::class)) {
                return null;
            }

            /** @var class-string<ResourceFormTabDefinition> $normalizedClass */
            return $normalizedClass::make($context, $record);
        }

        return null;
    }

    protected function resolveActiveFormTab(array $tabs, PanelContext $context): ?ResourceFormTab
    {
        if ($tabs === []) {
            return null;
        }

        $requested = trim((string) ($context->params['tab'] ?? $context->input('tab', '')));

        if ($requested !== '') {
            foreach ($tabs as $tab) {
                if ($this->matchesRequestedTabKey($tab->key(), $requested)) {
                    return $tab;
                }
            }

            abort(404);
        }

        foreach ($tabs as $tab) {
            if ($tab->isDefault()) {
                return $tab;
            }
        }

        return $tabs[0];
    }

    protected function matchesRequestedTabKey(string $tabKey, string $requested): bool
    {
        $tabKey = trim($tabKey);
        $requested = trim($requested);

        if ($tabKey === '' || $requested === '') {
            return false;
        }

        if ($tabKey === $requested) {
            return true;
        }

        $normalizedTab = (string) Str::of($tabKey)->replace('_', '-')->lower();
        $normalizedRequested = (string) Str::of($requested)->replace('_', '-')->lower();

        if ($normalizedTab === $normalizedRequested) {
            return true;
        }

        // Backward compatibility for previous auto-key style:
        // "basic-tab" should still match requested "basic".
        if (Str::endsWith($normalizedTab, '-tab')) {
            $legacyTab = (string) Str::beforeLast($normalizedTab, '-tab');
            if ($legacyTab !== '' && $legacyTab === $normalizedRequested) {
                return true;
            }
        }

        return false;
    }

    protected function resolveRoutedTabSchema(
        ResourceFormTab $tab,
        PanelContext $context,
        ...$args
    ): array {
        $operation = $tab->resolveOperation();

        if ($operation instanceof Operation) {
            if (! $operation->delegatedAuthorize($context)) {
                abort(403);
            }

            return $operation->delegatedSchema($context, ...$args);
        }

        return $tab->resolveSchema($context, ...$args);
    }

    protected function buildResourceTabComponent(
        PanelContext $context,
        array $tabs,
        ?ResourceFormTab $activeTab,
        array $activeSchema,
        ?Model $record = null
    ): Tab {
        $resource = $this->resource();
        $config = $resource->resolveFormConfig($context, $record);
        $tabConfig = (array) ($config['tab'] ?? []);
        $position = $this->normalizeFormTabPosition((string) ($tabConfig['position'] ?? 'top'));
        $variant = $this->normalizeFormTabVariant((string) ($tabConfig['variant'] ?? 'default'));
        $validationErrorIconConfig = (array) (
            $tabConfig['validation_error_icon']
            ?? $tabConfig['validationErrorIcon']
            ?? []
        );
        $showValidationErrorIcon = $this->normalizeBool(
            $validationErrorIconConfig['enabled'] ?? false,
            false
        );
        $validationErrorIcon = trim((string) ($validationErrorIconConfig['icon'] ?? 'lucide:circle-alert'));
        $errorFields = is_array($context->params['__form_tab_error_fields'] ?? null)
            ? (array) $context->params['__form_tab_error_fields']
            : [];
        $errorTabKeys = $this->resolveTabKeysForValidationErrors($context, $tabs, $errorFields, $record);
        $errorTabsLookup = array_fill_keys($errorTabKeys, true);
        $resolvedActiveTab = $this->resolveFirstErrorTab($tabs, $errorTabsLookup) ?? $activeTab;

        $tabComponent = Tab::make()
            ->position($position)
            ->variant($variant);

        $header = $this->resolveFormTabHeader($context, $tabs, $record);

        if ($header !== null) {
            $tabComponent->header($header);
        }

        if ($resolvedActiveTab instanceof ResourceFormTab) {
            $tabComponent->defaultOpen($resolvedActiveTab->key());
        }

        foreach ($tabs as $tab) {
            $isActive = $resolvedActiveTab instanceof ResourceFormTab && $resolvedActiveTab->key() === $tab->key();
            $hasValidationError = isset($errorTabsLookup[$tab->key()]);

            if ($tab->shouldNavigateWithRoute()) {
                $item = $tab->toTabItem($this->resourceTabUrl($context, $tab, $record), [], $isActive);
                if ($hasValidationError) {
                    $item->prop('hasValidationError', true);

                    if ($showValidationErrorIcon && $validationErrorIcon !== '') {
                        $item->prop('validationError', true);
                        $item->prop('validationErrorIcon', $validationErrorIcon);
                    }
                }
                $tabComponent->child($item);

                continue;
            }

            $item = $tab->toTabItem(
                null,
                $this->wrapFormTabContent(
                    $tab,
                    $context,
                    $record instanceof Model
                        ? $tab->resolveSchema($context, $record)
                        : $tab->resolveSchema($context),
                    $record
                ),
                $isActive
            );
            if ($hasValidationError) {
                $item->prop('hasValidationError', true);

                if ($showValidationErrorIcon && $validationErrorIcon !== '') {
                    $item->prop('validationError', true);
                    $item->prop('validationErrorIcon', $validationErrorIcon);
                }
            }
            $tabComponent->child($item);
        }

        if ($resolvedActiveTab instanceof ResourceFormTab && $resolvedActiveTab->shouldNavigateWithRoute()) {
            $schemaForActive = $resolvedActiveTab === $activeTab
                ? $activeSchema
                : ($record instanceof Model
                    ? $this->resolveRoutedTabSchema($resolvedActiveTab, $context, $record)
                    : $this->resolveRoutedTabSchema($resolvedActiveTab, $context));

            $tabComponent->slot('content', $this->wrapFormTabContent($resolvedActiveTab, $context, $schemaForActive, $record));
        }

        return $tabComponent;
    }

    protected function normalizeFormTabPosition(string $position): string
    {
        $normalized = strtolower(trim($position));

        $normalized = match ($normalized) {
            'vertical' => 'left',
            'horizontal' => 'top',
            default => $normalized,
        };

        return in_array($normalized, ['top', 'right', 'bottom', 'left'], true)
            ? $normalized
            : 'top';
    }

    protected function normalizeFormTabVariant(string $variant): string
    {
        $normalized = strtolower(trim($variant));

        return in_array($normalized, ['default', 'simple'], true)
            ? $normalized
            : 'default';
    }

    protected function wrapFormTabContent(
        ResourceFormTab $tab,
        PanelContext $context,
        array $content,
        ?Model $record = null
    ): array {
        $resource = $this->resource();
        $config = $resource->resolveFormConfig($context, $record);
        $tabConfig = (array) ($config['tab'] ?? []);
        $defaultCard = $this->normalizeBool($tabConfig['card'] ?? true, true);

        $resolvedCard = $record instanceof Model
            ? $tab->resolveCard($context, $record)
            : $tab->resolveCard($context);

        if ($resolvedCard === null) {
            $tab->card($defaultCard);
        }

        $this->applyResourceFormTabDefaults($resource, $tab, $context, $tabConfig, $record);

        $resolvedFieldColSpan = $record instanceof Model
            ? $tab->resolveFieldColSpan($context, $record)
            : $tab->resolveFieldColSpan($context);
        if ($resolvedFieldColSpan !== null) {
            $content = $this->applyDefaultFieldColSpanToNode($content, $resolvedFieldColSpan);
        }

        $layoutClass = config('upsoftware.resource.form_tab_layout', FormTabLayout::class);

        if (! is_string($layoutClass) || $layoutClass === '' || ! class_exists($layoutClass)) {
            $layoutClass = FormTabLayout::class;
        }

        $built = (new $layoutClass(
            $tab,
            $context,
            $content,
            $record
        ))->build();

        return $this->normalizeTabContentNodes($built);
    }

    protected function applyResourceFormTabDefaults(
        mixed $resource,
        ResourceFormTab $tab,
        PanelContext $context,
        array $tabConfig,
        ?Model $record = null
    ): void {
        $configuredDefaults = (array) ($tabConfig['defaults'] ?? []);
        $resourceDefaults = $record instanceof Model
            ? $resource->formTabDefaults($context, $record)
            : $resource->formTabDefaults($context);

        if (! is_array($resourceDefaults)) {
            $resourceDefaults = [];
        }

        $defaults = array_replace($configuredDefaults, $resourceDefaults);
        if ($defaults === []) {
            return;
        }

        $resolvedWidth = $record instanceof Model
            ? $tab->resolveWidthContent($context, $record)
            : $tab->resolveWidthContent($context);

        if ($resolvedWidth === null) {
            $defaultWidth = $this->firstValue($defaults, ['widthContent', 'width_content', 'width']);
            if ($defaultWidth !== null) {
                $tab->widthContent($defaultWidth);
            }
        }

        $resolvedPadding = $record instanceof Model
            ? $tab->resolvePaddingContent($context, $record)
            : $tab->resolvePaddingContent($context);

        if ($resolvedPadding === null) {
            $defaultPadding = $this->firstValue($defaults, ['paddingContent', 'padding_content', 'padding']);
            if ($defaultPadding !== null) {
                $tab->paddingContent($defaultPadding);
            }
        }

        $resolvedColSpan = $record instanceof Model
            ? $tab->resolveColSpan($context, $record)
            : $tab->resolveColSpan($context);

        if ($resolvedColSpan === null) {
            $defaultColSpan = $this->firstValue($defaults, ['colSpan', 'col_span', 'colspan', 'span']);
            if ($defaultColSpan !== null) {
                $tab->colSpan($defaultColSpan);
            }
        }

        $resolvedGrid = $record instanceof Model
            ? $tab->resolveGrid($context, $record)
            : $tab->resolveGrid($context);

        if ($resolvedGrid === null) {
            $defaultGrid = $this->firstValue($defaults, ['grid', 'gridColumns', 'grid_columns']);
            if (is_int($defaultGrid) && $defaultGrid > 0) {
                $tab->grid($defaultGrid);
            }
        }

        $resolvedContentCols = $record instanceof Model
            ? $tab->resolveContentCols($context, $record)
            : $tab->resolveContentCols($context);

        if ($resolvedContentCols === null) {
            $defaultContentCols = $this->firstValue($defaults, ['content', 'contentCols', 'content_cols', 'cols']);
            if ($defaultContentCols !== null) {
                $tab->contentCols($defaultContentCols);
            }
        }

        $resolvedFieldColSpan = $record instanceof Model
            ? $tab->resolveFieldColSpan($context, $record)
            : $tab->resolveFieldColSpan($context);

        if ($resolvedFieldColSpan === null) {
            $defaultFieldColSpan = $this->firstValue($defaults, ['fieldColSpan', 'field_col_span', 'field_colspan']);
            if ($defaultFieldColSpan !== null) {
                $tab->fieldColSpan($defaultFieldColSpan);
            }
        }
    }

    protected function firstValue(array $source, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') {
                return $source[$key];
            }
        }

        return null;
    }

    protected function applyDefaultFieldColSpanToNode(mixed $node, string|int $fieldColSpan): mixed
    {
        if ($node instanceof FieldComponent) {
            if (! $this->hasExplicitColSpan($node)) {
                $node->colSpan($fieldColSpan);
            }
        }

        if ($node instanceof Component) {
            $children = $node->getChildrenComponents();
            if ($children !== []) {
                $resolvedChildren = [];

                foreach ($children as $child) {
                    $resolvedChildren[] = $this->applyDefaultFieldColSpanToNode($child, $fieldColSpan);
                }

                $node->setChildrenComponents($resolvedChildren);
            }

            return $node;
        }

        if (is_array($node)) {
            $resolvedNodes = [];
            foreach ($node as $key => $entry) {
                $resolvedNodes[$key] = $this->applyDefaultFieldColSpanToNode($entry, $fieldColSpan);
            }

            return $resolvedNodes;
        }

        return $node;
    }

    protected function hasExplicitColSpan(FieldComponent $component): bool
    {
        $appearance = $component->getProp('appearance', []);
        if (! is_array($appearance)) {
            return false;
        }

        $classString = trim((string) ($appearance['class'] ?? ''));
        if (
            $classString !== ''
            && preg_match('/(?:^|\s)(?:[a-z0-9-]+:)?col-span-(?:\[[^\]]+\]|\d+|full|auto)(?=\s|$)/i', $classString) === 1
        ) {
            return true;
        }

        $style = $appearance['style'] ?? null;
        if (is_array($style) && isset($style['gridColumn'])) {
            return true;
        }

        return false;
    }

    /**
     * @return array<int, Component|array>
     */
    protected function normalizeTabContentNodes(mixed $nodes): array
    {
        if ($nodes instanceof Component) {
            return [$nodes];
        }

        if (! is_array($nodes)) {
            return [];
        }

        $normalized = [];

        foreach ($nodes as $node) {
            if ($node instanceof Component) {
                $normalized[] = $node;
                continue;
            }

            if (is_array($node) && isset($node['type']) && is_string($node['type'])) {
                $normalized[] = $node;
                continue;
            }

            if (is_array($node)) {
                $normalized = [
                    ...$normalized,
                    ...$this->normalizeTabContentNodes($node),
                ];
            }
        }

        return array_values($normalized);
    }

    /**
     * @param array<int, ResourceFormTab> $tabs
     * @param array<int, string> $errorFields
     */
    protected function resolveTabKeyForValidationErrors(
        PanelContext $context,
        array $tabs,
        array $errorFields,
        ?Model $record = null
    ): ?string {
        if ($tabs === [] || $errorFields === []) {
            return null;
        }

        $normalizedErrorFields = array_values(array_filter(array_map(
            static fn (mixed $field): string => trim((string) $field),
            $errorFields
        )));

        if ($normalizedErrorFields === []) {
            return null;
        }

        foreach ($tabs as $tab) {
            if (! $tab instanceof ResourceFormTab) {
                continue;
            }

            $schema = $record instanceof Model
                ? $tab->resolveSchema($context, $record)
                : $tab->resolveSchema($context);

            if ($tab->shouldNavigateWithRoute()) {
                $schema = $record instanceof Model
                    ? $this->resolveRoutedTabSchema($tab, $context, $record)
                    : $this->resolveRoutedTabSchema($tab, $context);
            }

            $fieldNames = array_values(array_filter(array_map(
                static fn (mixed $name): string => trim((string) $name),
                $this->collectFieldNames($schema)
            )));

            if ($fieldNames === []) {
                continue;
            }

            foreach ($normalizedErrorFields as $errorField) {
                foreach ($fieldNames as $fieldName) {
                    if ($fieldName === '') {
                        continue;
                    }

                    $normalizedErrorField = $this->normalizeValidationFieldPath($errorField);
                    $normalizedFieldName = $this->normalizeValidationFieldPath($fieldName);

                    if ($normalizedErrorField === '' || $normalizedFieldName === '') {
                        continue;
                    }

                    if (
                        $normalizedErrorField === $normalizedFieldName
                        || Str::startsWith($normalizedErrorField, $normalizedFieldName.'.')
                        || Str::startsWith($normalizedFieldName, $normalizedErrorField.'.')
                    ) {
                        $this->debugFormTabs($context, 'matched_tab_for_error', [
                            'tab_key' => $tab->key(),
                            'error_field' => $errorField,
                            'normalized_error_field' => $normalizedErrorField,
                            'field_name' => $fieldName,
                            'normalized_field_name' => $normalizedFieldName,
                        ]);
                        return $tab->key();
                    }
                }
            }

            $this->debugFormTabs($context, 'tab_fields_checked', [
                'tab_key' => $tab->key(),
                'fields' => $fieldNames,
                'error_fields' => $normalizedErrorFields,
            ]);
        }

        $this->debugFormTabs($context, 'no_tab_match_for_errors', [
            'error_fields' => $normalizedErrorFields,
        ]);

        return null;
    }

    /**
     * @param array<int, ResourceFormTab> $tabs
     * @param array<int, string> $errorFields
     * @return array<int, string>
     */
    protected function resolveTabKeysForValidationErrors(
        PanelContext $context,
        array $tabs,
        array $errorFields,
        ?Model $record = null
    ): array {
        if ($tabs === [] || $errorFields === []) {
            return [];
        }

        $keys = [];

        foreach ($errorFields as $errorField) {
            $tabKey = $this->resolveTabKeyForValidationErrors(
                $context,
                $tabs,
                [(string) $errorField],
                $record
            );

            if (is_string($tabKey) && trim($tabKey) !== '') {
                $keys[] = $tabKey;
            }
        }

        return array_values(array_unique($keys));
    }

    protected function resourceTabUrl(
        PanelContext $context,
        ResourceFormTab $tab,
        ?Model $record = null
    ): string {
        $resource = $this->resource();
        $slug = trim((string) $resource::slug(), '/');
        $panelPrefix = trim($context->panel()->prefixName(), '/');
        $base = trim(implode('/', array_filter([$panelPrefix, $slug])), '/');
        $tabKey = trim($tab->key(), '/');

        if ($record instanceof Model) {
            return "{$base}/{$record->getKey()}/edit/{$tabKey}";
        }

        return "{$base}/create/{$tabKey}";
    }

    protected function resolveFormTabHeader(
        PanelContext $context,
        array $tabs,
        ?Model $record = null
    ): Component|array|string|\Closure|null {
        $resource = $this->resource();
        $header = $resource->formTabHeader($context, $record);

        if ($header !== null) {
            return $header;
        }

        $config = $resource->resolveFormConfig($context, $record);
        $tabConfig = (array) ($config['tab'] ?? []);
        $langConfig = (array) ($config['language'] ?? []);
        $showTitle = (bool) ($tabConfig['title'] ?? true);
        $languageDisplay = strtolower(trim((string) ($langConfig['display'] ?? 'inline')));
        $languageMultiple = $this->normalizeBool($langConfig['multiple'] ?? false, false);
        $languageShowIcon = $this->normalizeBool(
            $langConfig['showIcon'] ?? $langConfig['show_icon'] ?? false,
            false
        );
        $languageShowLabel = $this->normalizeBool(
            $langConfig['showLabel'] ?? $langConfig['show_label'] ?? true,
            true
        );
        $languagesFields = $this->countLanguageFieldsInFormTabs($tabs, $context, $record);

        $title = $showTitle ? $resource->formTabHeaderTitle($context, $record) : null;
        $aside = $resource->formTabHeaderAside($context, $record) ?? [];

        if ($showTitle && $title === null) {
            $title = $record instanceof Model
                ? $resource->editTitle($context, $record)
                : $resource->createTitle($context);
        }

        $left = null;

        if ($title !== null && trim((string) (is_scalar($title) ? $title : 'component')) !== '') {
            $left = $title instanceof Component || is_array($title) || $title instanceof \Closure
                ? $title
                : Text::make((string) $title)
                    ->headline('h2')
                    ->appearance('text-base font-semibold ps-4');
        }

        if ($left === null && $aside === null) {
            return null;
        }

        if ($languagesFields > 0) {
            $aside = array_merge((array) $aside, [
                Select::make((string) $languagesFields)
                    ->width('160px')
                    ->if($languageDisplay === 'select')
                    ->options(locales())
                    ->languageSelector()
                    ->multiple($languageMultiple),
                LocaleInline::make((string) $languagesFields)
                    ->if($languageDisplay === 'inline')
                    ->showIcon($languageShowIcon)
                    ->showLabel($languageShowLabel)
                    ->languageSelector()
                    ->multiple($languageMultiple),
            ]);

        }

        return Flex::make()
            ->justify('between')
            ->items('center')
            ->gap(4)
            ->children(array_values(array_filter([
                $left !== null
                    ? Block::make()->children($left)->flex(1)
                    : null,
                $aside !== null
                    ? Block::make()->children($aside)
                    : null,
            ])));
    }

    protected function countLanguageFieldsInFormTabs(
        array $tabs,
        PanelContext $context,
        ?Model $record = null
    ): int {
        $count = 0;

        foreach ($tabs as $tab) {
            if (! $tab instanceof ResourceFormTab) {
                continue;
            }

            $schema = [];

            if ($tab->shouldNavigateWithRoute()) {
                $operation = $tab->resolveOperation();

                if ($operation instanceof Operation && ! $operation->delegatedAuthorize($context)) {
                    continue;
                }

                $schema = $record instanceof Model
                    ? $this->resolveRoutedTabSchema($tab, $context, $record)
                    : $this->resolveRoutedTabSchema($tab, $context);
            } else {
                $schema = $record instanceof Model
                    ? $tab->resolveSchema($context, $record)
                    : $tab->resolveSchema($context);
            }

            $count += $this->countLanguageFieldsInNodes($schema);
        }

        return $count;
    }

    protected function countLanguageFieldsInNodes(mixed $nodes): int
    {
        if ($nodes instanceof Component) {
            if (! $nodes->shouldRender()) {
                return 0;
            }

            if ($nodes instanceof FieldComponent && (bool) $nodes->getProp('language', false)) {
                return 1;
            }

            $nodes = $nodes->toArray();
        }

        if (! is_array($nodes)) {
            return 0;
        }

        if (array_is_list($nodes)) {
            $count = 0;

            foreach ($nodes as $node) {
                $count += $this->countLanguageFieldsInNodes($node);
            }

            return $count;
        }

        $count = 0;

        if (($nodes['props']['language'] ?? false) === true) {
            $count++;
        }

        $count += $this->countLanguageFieldsInNodes($nodes['children'] ?? []);

        foreach ((array) ($nodes['slots'] ?? []) as $slot) {
            $count += $this->countLanguageFieldsInNodes($slot);
        }

        return $count;
    }

    protected function normalizeBool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ($normalized === '') {
                return $default;
            }

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        return $default;
    }

    protected function debugFormTabs(PanelContext $context, string $stage, array $payload = []): void
    {
        if (! (bool) config('app.debug', false)) {
            return;
        }

        try {
            logger()->debug('[svarium][form-tabs]['.$stage.']', [
                'resource' => static::class,
                'request_path' => $context->request()->path(),
                ...$payload,
            ]);
        } catch (\Throwable) {
            // noop
        }
    }

    /**
     * @param array<int, ResourceFormTab> $tabs
     * @param array<string, bool> $errorTabsLookup
     */
    protected function resolveFirstErrorTab(array $tabs, array $errorTabsLookup): ?ResourceFormTab
    {
        if ($tabs === [] || $errorTabsLookup === []) {
            return null;
        }

        foreach ($tabs as $tab) {
            if (! $tab instanceof ResourceFormTab) {
                continue;
            }

            if (isset($errorTabsLookup[$tab->key()])) {
                return $tab;
            }
        }

        return null;
    }

    protected function normalizeValidationFieldPath(string $field): string
    {
        $normalized = trim($field);
        if ($normalized === '') {
            return '';
        }

        $normalized = (string) preg_replace('/\[(.*?)\]/', '.$1', $normalized);
        $normalized = str_replace(['..', '.[', '].'], ['.', '.', '.'], $normalized);
        $normalized = trim($normalized, '.');

        return strtolower($normalized);
    }
}
