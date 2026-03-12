<?php

namespace Upsoftware\Svarium\Panel\Resource\Operations\Concerns;

use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\Layouts\Panel\FormTabLayout;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Panel\Resource\ResourceFormTab;
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
        return array_values(array_filter(
            $tabs,
            static fn ($tab): bool => $tab instanceof ResourceFormTab && $tab->shouldRender($context, ...$args)
        ));
    }

    protected function resolveActiveFormTab(array $tabs, PanelContext $context): ?ResourceFormTab
    {
        if ($tabs === []) {
            return null;
        }

        $requested = trim((string) ($context->params['tab'] ?? $context->input('tab', '')));

        if ($requested !== '') {
            foreach ($tabs as $tab) {
                if ($tab->key() === $requested) {
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

        $tabComponent = Tab::make()
            ->position($position)
            ->variant($variant);

        $header = $this->resolveFormTabHeader($context, $tabs, $record);

        if ($header !== null) {
            $tabComponent->header($header);
        }

        if ($activeTab instanceof ResourceFormTab) {
            $tabComponent->defaultOpen($activeTab->key());
        }

        foreach ($tabs as $tab) {
            $isActive = $activeTab instanceof ResourceFormTab && $activeTab->key() === $tab->key();

            if ($tab->isRouted()) {
                $tabComponent->child(
                    $tab->toTabItem($this->resourceTabUrl($context, $tab, $record), [], $isActive)
                );

                continue;
            }

            $tabComponent->child(
                $tab->toTabItem(
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
                )
            );
        }

        if ($activeTab instanceof ResourceFormTab && $activeTab->isRouted()) {
            $tabComponent->slot('content', $this->wrapFormTabContent($activeTab, $context, $activeSchema, $record));
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
        $layoutClass = config('upsoftware.resource.form_tab_layout', FormTabLayout::class);

        if (! is_string($layoutClass) || $layoutClass === '' || ! class_exists($layoutClass)) {
            $layoutClass = FormTabLayout::class;
        }

        return (array) (new $layoutClass(
            $tab,
            $context,
            $content,
            $record
        ))->build();
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

            if ($tab->isRouted()) {
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
}
