<?php

namespace Upsoftware\Svarium\Panel\Form;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Components\Block;
use Upsoftware\Svarium\UI\Components\Card;
use Upsoftware\Svarium\UI\Components\FieldComponent;
use Upsoftware\Svarium\UI\Components\Flex;
use Upsoftware\Svarium\UI\Components\Grid;
use Upsoftware\Svarium\UI\Components\LocaleInline;
use Upsoftware\Svarium\UI\Components\Form\Select;

class Form
{
    public const REQUEST_SUBMIT_LABEL_KEY = '_svarium_form_submit_label';
    public const REQUEST_MODEL_KEY = '_svarium_form_model';

    public static function make(string $formClass, ?Model $record = null): array
    {
        if (! class_exists($formClass)) {
            throw new \InvalidArgumentException("Form config class [{$formClass}] does not exist.");
        }

        if (! method_exists($formClass, 'make')) {
            throw new \InvalidArgumentException("Form config class [{$formClass}] must define static make().");
        }

        self::storeResolvedSubmitLabelOnRequest($formClass, $record);
        self::storeResolvedModelOnRequest($record);

        try {
            $schema = $formClass::make($record);
        } catch (\ArgumentCountError) {
            $schema = $formClass::make();
        }

        if (! is_array($schema)) {
            throw new \InvalidArgumentException("Form config class [{$formClass}]::make() must return array.");
        }

        $languageFieldsCount = self::countLanguageFieldsInNodes($schema);
        $localeSelector = $languageFieldsCount > 0
            ? self::buildLocaleSelector($formClass, $languageFieldsCount, $record)
            : null;
        $localeInCard = self::resolveLocaleInCard($formClass, $record);

        $fieldColSpan = self::resolveFieldColSpan($formClass, $record);
        if ($fieldColSpan !== null) {
            $schema = self::applyDefaultFieldColSpanToNode($schema, $fieldColSpan);
        }

        $cardWrapperEnabled = self::usesCardWrapper($formClass, $record);

        if ($cardWrapperEnabled) {
            $resolvedAction = self::resolveCardAction($formClass, $record);
            if ($localeSelector !== null && $localeInCard) {
                $resolvedAction = self::mergeCardActionWithLocaleSelector($resolvedAction, $localeSelector);
            }

            $schema = self::wrapInCard(
                $schema,
                title: self::resolveCardTitle($formClass, $record),
                subtitle: self::resolveCardSubtitle($formClass, $record),
                icon: self::resolveCardIcon($formClass, $record),
                action: $resolvedAction,
                contentPadding: self::resolvePaddingContent($formClass, $record) ?? '4',
                contentWidth: self::resolveWidthContent($formClass, $record),
                colSpan: self::resolveCardColSpan($formClass, $record),
                gridColumns: self::resolveCardGrid($formClass, $record) ?? 12,
                contentCols: self::resolveContentCols($formClass, $record) ?? 12,
                contentGap: self::resolveContentGap($formClass, $record) ?? 4,
            );
        }

        if ($localeSelector !== null && (! $localeInCard || ! $cardWrapperEnabled)) {
            $schema = self::prependLocaleSelector($schema, $localeSelector);
        }

        return $schema;
    }

    protected static function storeResolvedSubmitLabelOnRequest(string $formClass, ?Model $record = null): void
    {
        $request = request();
        if (! $request) {
            return;
        }

        $resolved = self::resolveSubmitLabel($formClass, $record);

        if ($resolved === null || trim($resolved) === '') {
            $request->attributes->remove(self::REQUEST_SUBMIT_LABEL_KEY);
            return;
        }

        $request->attributes->set(self::REQUEST_SUBMIT_LABEL_KEY, $resolved);
    }

    protected static function storeResolvedModelOnRequest(?Model $record = null): void
    {
        $request = request();
        if (! $request) {
            return;
        }

        if (! $record instanceof Model) {
            $request->attributes->remove(self::REQUEST_MODEL_KEY);
            return;
        }

        $request->attributes->set(self::REQUEST_MODEL_KEY, $record);
    }

    protected static function resolveSubmitLabel(string $formClass, ?Model $record = null): ?string
    {
        $fromMethod = self::resolveOptionalString(
            self::invokeStaticMethod($formClass, 'submitLabel', $record)
        );
        if ($fromMethod !== null) {
            return $fromMethod;
        }

        $fromProperty = self::resolveOptionalString(
            self::readStaticProperty($formClass, 'submitLabel')
        );
        if ($fromProperty !== null) {
            return __($fromProperty);
        }

        return null;
    }

    protected static function usesCardWrapper(string $formClass, ?Model $record = null): bool
    {
        return self::resolveCardEnabled($formClass, $record);
    }

    protected static function wrapInCard(
        array $schema,
        ?string $title = null,
        ?string $subtitle = null,
        ?string $icon = null,
        Component|array|string|Closure|null $action = null,
        int|string|float $contentPadding = '4',
        int|string|float|null $contentWidth = null,
        string|int|null $colSpan = null,
        int $gridColumns = 12,
        string|int $contentCols = 12,
        int|string|float $contentGap = 4
    ): array
    {
        $card = Card::make()
            ->variant('form-tab')
            ->contentPadding($contentPadding)
            ->children([
                Grid::make()
                    ->cols($contentCols)
                    ->gap($contentGap)
                    ->children($schema),
            ]);

        if ($colSpan !== null) {
            $card->colSpan($colSpan, $gridColumns);
        }

        if ($contentWidth !== null) {
            $card->contentWidth($contentWidth);
        }

        if (is_string($title) && trim($title) !== '') {
            $card->title($title);
        }

        if (is_string($subtitle) && trim($subtitle) !== '') {
            $card->description($subtitle);
        }

        if (is_string($icon) && trim($icon) !== '') {
            $card->icon($icon);
        }

        if ($action !== null) {
            $card->action($action);
        }

        return [
            $card,
        ];
    }

    protected static function resolveCardTitle(string $formClass, ?Model $record = null): ?string
    {
        $fromMethod = self::resolveOptionalString(
            self::invokeStaticMethod($formClass, 'title', $record)
        );
        if ($fromMethod !== null) {
            return $fromMethod;
        }

        $fromProperty = self::resolveOptionalString(
            self::readStaticProperty($formClass, 'title')
        );
        if ($fromProperty !== null) {
            return __($fromProperty);
        }

        return null;
    }

    protected static function resolveLocaleInCard(string $formClass, ?Model $record = null): bool
    {
        $fromMethod = self::invokeStaticMethod($formClass, 'localeInCard', $record);
        if ($fromMethod !== null) {
            return self::normalizeBool($fromMethod, false);
        }

        $fromProperty = self::readStaticProperty($formClass, 'localeInCard');
        if ($fromProperty !== null) {
            return self::normalizeBool($fromProperty, false);
        }

        return false;
    }

    protected static function prependLocaleSelector(array $schema, Component $localeSelector): array
    {
        return [
            Flex::make()
                ->justify('end')
                ->items('center')
                ->margin('mb-3')
                ->children([
                    Block::make()->children([$localeSelector]),
                ]),
            ...$schema,
        ];
    }

    protected static function mergeCardActionWithLocaleSelector(
        Component|array|string|Closure|null $action,
        Component $localeSelector
    ): Component|array|string|Closure|null {
        $nodes = [];

        if ($action !== null) {
            $nodes = self::normalizeActionNodes($action);
        }

        $nodes[] = $localeSelector;

        return Flex::make()
            ->items('center')
            ->justify('end')
            ->gap(2)
            ->children($nodes);
    }

    protected static function normalizeActionNodes(mixed $action): array
    {
        if ($action instanceof Closure) {
            try {
                $action = $action();
            } catch (\Throwable) {
                return [];
            }
        }

        if ($action instanceof Component) {
            return [$action];
        }

        if (is_array($action)) {
            $nodes = [];

            foreach ($action as $node) {
                if ($node instanceof Component || is_array($node)) {
                    $nodes[] = $node;
                }
            }

            return $nodes;
        }

        return [];
    }

    protected static function buildLocaleSelector(
        string $formClass,
        int $languageFieldsCount,
        ?Model $record = null
    ): ?Component {
        if ($languageFieldsCount <= 0) {
            return null;
        }

        $config = self::resolveLanguageConfig($formClass, $record);
        $display = strtolower(trim((string) ($config['display'] ?? 'inline')));
        $multiple = self::normalizeBool($config['multiple'] ?? false, false);
        $showIcon = self::normalizeBool(
            $config['showIcon'] ?? $config['show_icon'] ?? false,
            false
        );
        $showLabel = self::normalizeBool(
            $config['showLabel'] ?? $config['show_label'] ?? true,
            true
        );

        if ($display === 'select') {
            return Select::make((string) $languageFieldsCount)
                ->width('160px')
                ->options(locales())
                ->languageSelector()
                ->multiple($multiple);
        }

        return LocaleInline::make((string) $languageFieldsCount)
            ->showIcon($showIcon)
            ->showLabel($showLabel)
            ->languageSelector()
            ->multiple($multiple);
    }

    protected static function resolveLanguageConfig(string $formClass, ?Model $record = null): array
    {
        $defaults = [
            'display' => 'inline',
            'multiple' => false,
            'showIcon' => false,
            'showLabel' => true,
        ];

        $configured = config('upsoftware.resource.form.language', []);
        if (is_array($configured)) {
            $defaults = array_replace($defaults, $configured);
        }

        $custom = self::invokeStaticMethod($formClass, 'languageConfig', $record);
        if (is_array($custom)) {
            $defaults = array_replace($defaults, $custom);
        }

        $customProperty = self::readStaticProperty($formClass, 'languageConfig');
        if (is_array($customProperty)) {
            $defaults = array_replace($defaults, $customProperty);
        }

        return $defaults;
    }

    protected static function countLanguageFieldsInNodes(mixed $nodes): int
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
                $count += self::countLanguageFieldsInNodes($node);
            }

            return $count;
        }

        $count = 0;

        if (($nodes['props']['language'] ?? false) === true) {
            $count++;
        }

        $count += self::countLanguageFieldsInNodes($nodes['children'] ?? []);

        foreach ((array) ($nodes['slots'] ?? []) as $slot) {
            $count += self::countLanguageFieldsInNodes($slot);
        }

        return $count;
    }

    protected static function resolveCardSubtitle(string $formClass, ?Model $record = null): ?string
    {
        $fromMethod = self::resolveOptionalString(
            self::invokeStaticMethod($formClass, 'subtitle', $record)
        );
        if ($fromMethod !== null) {
            return $fromMethod;
        }

        return self::resolveOptionalString(self::readStaticProperty($formClass, 'subtitle'));
    }

    protected static function resolveCardIcon(string $formClass, ?Model $record = null): ?string
    {
        $fromMethod = self::resolveOptionalString(
            self::invokeStaticMethod($formClass, 'icon', $record)
        );
        if ($fromMethod !== null) {
            return $fromMethod;
        }

        return self::resolveOptionalString(self::readStaticProperty($formClass, 'icon'));
    }

    protected static function resolveCardAction(
        string $formClass,
        ?Model $record = null
    ): Component|array|string|Closure|null {
        $fromMethod = self::invokeStaticMethod($formClass, 'action', $record);
        if ($fromMethod !== null) {
            return self::normalizeCardAction($fromMethod);
        }

        $fromProperty = self::readStaticProperty($formClass, 'action');
        if ($fromProperty !== null) {
            return self::normalizeCardAction($fromProperty);
        }

        return null;
    }

    protected static function resolveCardEnabled(string $formClass, ?Model $record = null): bool
    {
        $fromMethod = self::invokeStaticMethod($formClass, 'card', $record);
        if ($fromMethod !== null) {
            return self::normalizeBool($fromMethod, false);
        }

        $fromProperty = self::readStaticProperty($formClass, 'card');
        if ($fromProperty !== null) {
            return self::normalizeBool($fromProperty, false);
        }

        return false;
    }

    protected static function resolvePaddingContent(string $formClass, ?Model $record = null): int|string|float|null
    {
        $fromMethod = self::invokeStaticMethod($formClass, 'paddingContent', $record);
        if ($fromMethod !== null) {
            return self::normalizeNumberOrString($fromMethod);
        }

        $fromProperty = self::readStaticProperty($formClass, 'paddingContent');
        if ($fromProperty !== null) {
            return self::normalizeNumberOrString($fromProperty);
        }

        return null;
    }

    protected static function resolveWidthContent(string $formClass, ?Model $record = null): int|string|float|null
    {
        $fromMethod = self::invokeStaticMethod($formClass, 'widthContent', $record);
        if ($fromMethod !== null) {
            return self::normalizeNumberOrString($fromMethod);
        }

        $fromProperty = self::readStaticProperty($formClass, 'widthContent');
        if ($fromProperty !== null) {
            return self::normalizeNumberOrString($fromProperty);
        }

        return null;
    }

    protected static function resolveFieldColSpan(string $formClass, ?Model $record = null): string|int|null
    {
        $fromMethod = self::invokeStaticMethod($formClass, 'fieldColSpan', $record);
        if ($fromMethod !== null) {
            return self::normalizeSpan($fromMethod);
        }

        $fromProperty = self::readStaticProperty($formClass, 'fieldColSpan');
        if ($fromProperty !== null) {
            return self::normalizeSpan($fromProperty);
        }

        return null;
    }

    protected static function resolveCardColSpan(string $formClass, ?Model $record = null): string|int|null
    {
        $fromMethod = self::invokeStaticMethod($formClass, 'colSpan', $record);
        if ($fromMethod !== null) {
            return self::normalizeSpan($fromMethod);
        }

        $fromProperty = self::readStaticProperty($formClass, 'colSpan');
        if ($fromProperty !== null) {
            return self::normalizeSpan($fromProperty);
        }

        return null;
    }

    protected static function resolveCardGrid(string $formClass, ?Model $record = null): ?int
    {
        $fromMethod = self::invokeStaticMethod($formClass, 'grid', $record);
        if ($fromMethod !== null) {
            return self::normalizePositiveInt($fromMethod);
        }

        $fromProperty = self::readStaticProperty($formClass, 'grid');
        if ($fromProperty !== null) {
            return self::normalizePositiveInt($fromProperty);
        }

        return null;
    }

    protected static function resolveContentCols(string $formClass, ?Model $record = null): string|int|null
    {
        $fromMethod = self::invokeStaticMethod($formClass, 'contentCols', $record);
        if ($fromMethod !== null) {
            return self::normalizeSpan($fromMethod);
        }

        $fromProperty = self::readStaticProperty($formClass, 'contentCols');
        if ($fromProperty !== null) {
            return self::normalizeSpan($fromProperty);
        }

        $aliasMethod = self::invokeStaticMethod($formClass, 'cols', $record);
        if ($aliasMethod !== null) {
            return self::normalizeSpan($aliasMethod);
        }

        $aliasProperty = self::readStaticProperty($formClass, 'cols');
        if ($aliasProperty !== null) {
            return self::normalizeSpan($aliasProperty);
        }

        return null;
    }

    protected static function resolveContentGap(string $formClass, ?Model $record = null): int|string|float|null
    {
        $fromMethod = self::invokeStaticMethod($formClass, 'gap', $record);
        if ($fromMethod !== null) {
            return self::normalizeNumberOrString($fromMethod);
        }

        $fromProperty = self::readStaticProperty($formClass, 'gap');
        if ($fromProperty !== null) {
            return self::normalizeNumberOrString($fromProperty);
        }

        return null;
    }

    protected static function normalizeCardAction(
        mixed $action
    ): Component|array|string|Closure|null {
        if ($action instanceof Component || $action instanceof Closure || is_array($action)) {
            return $action;
        }

        if (is_string($action)) {
            $normalized = trim($action);

            return $normalized !== '' ? $normalized : null;
        }

        return null;
    }

    protected static function applyDefaultFieldColSpanToNode(mixed $node, string|int $fieldColSpan): mixed
    {
        if ($node instanceof FieldComponent) {
            if (! self::hasExplicitColSpan($node)) {
                $node->colSpan($fieldColSpan);
            }
        }

        if ($node instanceof Component) {
            $children = $node->getChildrenComponents();
            if ($children !== []) {
                $resolvedChildren = [];

                foreach ($children as $child) {
                    $resolvedChildren[] = self::applyDefaultFieldColSpanToNode($child, $fieldColSpan);
                }

                $node->setChildrenComponents($resolvedChildren);
            }

            return $node;
        }

        if (is_array($node)) {
            $resolvedNodes = [];
            foreach ($node as $key => $entry) {
                $resolvedNodes[$key] = self::applyDefaultFieldColSpanToNode($entry, $fieldColSpan);
            }

            return $resolvedNodes;
        }

        return $node;
    }

    protected static function hasExplicitColSpan(FieldComponent $component): bool
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

    protected static function invokeStaticMethod(
        string $class,
        string $method,
        ?Model $record = null
    ): mixed {
        if (! method_exists($class, $method)) {
            return null;
        }

        try {
            $reflection = new \ReflectionMethod($class, $method);
            $reflection->setAccessible(true);

            $parameters = $reflection->getNumberOfParameters();
            if ($parameters <= 0) {
                return $reflection->invoke(null);
            }

            return $reflection->invoke(null, $record);
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function readStaticProperty(string $class, string $property): mixed
    {
        if (! property_exists($class, $property)) {
            return null;
        }

        try {
            $reflection = new \ReflectionProperty($class, $property);
            $reflection->setAccessible(true);

            return $reflection->getValue();
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function resolveOptionalString(mixed $value): ?string
    {
        if (! is_scalar($value) || $value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    protected static function normalizeNumberOrString(mixed $value): int|string|float|null
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = trim($value);

            return $normalized !== '' ? $normalized : null;
        }

        return null;
    }

    protected static function normalizeSpan(mixed $value): string|int|null
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value)) {
            $normalized = trim($value);

            return $normalized !== '' ? $normalized : null;
        }

        return null;
    }

    protected static function normalizePositiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && is_numeric(trim($value))) {
            $normalized = (int) trim($value);

            return $normalized > 0 ? $normalized : null;
        }

        return null;
    }

    protected static function normalizeBool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
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

        return $default;
    }
}
