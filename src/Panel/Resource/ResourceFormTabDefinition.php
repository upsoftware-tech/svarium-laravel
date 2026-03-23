<?php

namespace Upsoftware\Svarium\Panel\Resource;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Upsoftware\Svarium\Layouts\Panel\FormTabCardLayout;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Components\Button;
use Upsoftware\Svarium\UI\Components\FieldComponent;
use Upsoftware\Svarium\UI\Components\Grid;

abstract class ResourceFormTabDefinition
{
    protected static string $key = '';
    protected static ?string $label = null;
    protected static ?string $view = null;
    protected static ?string $title = null;
    protected static ?string $subtitle = null;
    protected static ?string $icon = null;
    protected static string|int|null $badge = null;
    protected static bool $default = false;
    protected static ?bool $card = null;
    protected static string|int|float|null $widthContent = null;
    protected static string|int|float|null $paddingContent = null;
    protected static string|int|null $tabColSpan = null;
    protected static ?int $tabGrid = null;
    protected static string|int|null $tabContent = null;
    protected static string|int|null $fieldColSpan = null;
    protected static int $grid = 1;
    protected static int|string|float $gap = 4;

    public static function make(PanelContext $context, ?Model $record = null): ResourceFormTab
    {
        $tab = ResourceFormTab::make(static::resolveKey($context, $record));
        $tab->label(static::resolveLabel($context, $record));
        $tab->routed(static::routed($context, $record));

        $title = static::resolveTitle($context, $record);
        if ($title !== null) {
            $tab->title($title);
        }

        $subtitle = static::resolveSubtitle($context, $record);
        if ($subtitle !== null) {
            $tab->subtitle($subtitle);
        }

        $icon = static::resolveIcon($context, $record);
        if ($icon !== null) {
            $tab->icon($icon);
        }

        $badge = static::resolveBadge($context, $record);
        if ($badge !== null) {
            $tab->badge($badge);
        }

        if (static::isDefault($context, $record)) {
            $tab->default();
        }

        $view = static::resolveView($context, $record);
        if ($view !== null) {
            $tab->view($view);
        }

        $schema = static::schema($context, $record);
        $cards = static::resolveCards($context, $record);
        if ($cards !== [] && ($view === null || $view === 'cards')) {
            $schema = static::mergeCardsIntoSchema($schema, $cards);
        }

        $fieldColSpan = static::resolveFieldColSpan($context, $record);
        if ($fieldColSpan !== null) {
            $tab->fieldColSpan($fieldColSpan);
            $schema = static::applyDefaultFieldColSpan($schema, $fieldColSpan);
        }

        $tab->schema($schema);

        $action = static::action($context, $record);
        if ($action !== null) {
            $tab->action($action);
        }

        $operation = static::resolveOptionalString(static::operation($context, $record));
        if ($operation !== null) {
            $tab->operation($operation, static::routed($context, $record));
        }

        $url = static::resolveOptionalString(static::url($context, $record));
        if ($url !== null) {
            $tab->url($url);
        }

        $tab->visible(static::visible($context, $record));

        if ($view === null) {
            $card = static::resolveCard($context, $record);
            if ($card !== null) {
                $tab->card($card);
            }
        }

        $widthContent = static::resolveWidthContent($context, $record);
        if ($widthContent !== null) {
            $tab->widthContent($widthContent);
        }

        $paddingContent = static::resolvePaddingContent($context, $record);
        if ($paddingContent !== null) {
            $tab->paddingContent($paddingContent);
        }

        $tabColSpan = static::resolveTabColSpan($context, $record);
        if ($tabColSpan !== null) {
            $tab->colSpan($tabColSpan);
        }

        $tabGrid = static::resolveTabGrid($context, $record);
        if ($tabGrid !== null) {
            $tab->grid($tabGrid);
        }

        $tabContent = static::resolveTabContent($context, $record);
        if ($tabContent !== null) {
            $tab->contentCols($tabContent);
        }

        return $tab;
    }

    protected static function resolveKey(PanelContext $context, ?Model $record = null): string
    {
        $key = static::resolveOptionalString(static::key($context, $record));

        if ($key === null) {
            $key = static::resolveOptionalString(static::$key);
        }

        if ($key !== null) {
            return $key;
        }

        $fallback = (string) Str::of(class_basename(static::class))
            ->kebab()
            ->toString();

        return trim($fallback) !== '' ? $fallback : 'tab';
    }

    protected static function resolveLabel(PanelContext $context, ?Model $record = null): string
    {
        $label = static::resolveOptionalString(static::label($context, $record));

        if ($label !== null) {
            return $label;
        }

        $label = static::resolveOptionalString(static::$label);
        if ($label !== null) {
            return $label;
        }

        $title = static::resolveOptionalString(static::title($context, $record));
        if ($title !== null) {
            return $title;
        }

        $title = static::resolveOptionalString(static::$title);
        if ($title !== null) {
            return $title;
        }

        return static::resolveKey($context, $record);
    }

    protected static function resolveTitle(PanelContext $context, ?Model $record = null): ?string
    {
        $title = static::resolveOptionalString(static::title($context, $record));
        if ($title !== null) {
            return $title;
        }

        $title = static::resolveOptionalString(static::$title);
        if ($title !== null) {
            return $title;
        }

        $label = static::resolveOptionalString(static::label($context, $record));
        if ($label !== null) {
            return $label;
        }

        return static::resolveOptionalString(static::$label);
    }

    protected static function resolveSubtitle(PanelContext $context, ?Model $record = null): ?string
    {
        $subtitle = static::resolveOptionalString(static::subtitle($context, $record));
        if ($subtitle !== null) {
            return $subtitle;
        }

        return static::resolveOptionalString(static::$subtitle);
    }

    protected static function resolveIcon(PanelContext $context, ?Model $record = null): ?string
    {
        $icon = static::resolveOptionalString(static::icon($context, $record));
        if ($icon !== null) {
            return $icon;
        }

        return static::resolveOptionalString(static::$icon);
    }

    protected static function resolveBadge(PanelContext $context, ?Model $record = null): string|int|null
    {
        $badge = static::badge($context, $record);

        return $badge ?? static::$badge;
    }

    protected static function resolveCard(PanelContext $context, ?Model $record = null): ?bool
    {
        $card = static::card($context, $record);

        if ($card !== null) {
            return (bool) $card;
        }

        return static::$card;
    }

    protected static function resolveView(PanelContext $context, ?Model $record = null): ?string
    {
        $view = static::normalizeView(static::view($context, $record));
        if ($view !== null) {
            return $view;
        }

        return static::normalizeView(static::$view);
    }

    protected static function resolveWidthContent(PanelContext $context, ?Model $record = null): string|int|float|null
    {
        $width = static::widthContent($context, $record);

        if (is_int($width) || is_float($width)) {
            return $width;
        }

        if (is_string($width)) {
            $normalized = trim($width);

            return $normalized !== '' ? $normalized : null;
        }

        return null;
    }

    protected static function resolvePaddingContent(PanelContext $context, ?Model $record = null): string|int|float|null
    {
        $padding = static::paddingContent($context, $record);

        if (is_int($padding) || is_float($padding)) {
            return $padding;
        }

        if (is_string($padding)) {
            $normalized = trim($padding);

            return $normalized !== '' ? $normalized : null;
        }

        return null;
    }

    protected static function resolveTabColSpan(PanelContext $context, ?Model $record = null): string|int|null
    {
        $value = static::tabColSpan($context, $record);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = trim($value);

            return $normalized !== '' ? $normalized : null;
        }

        return null;
    }

    protected static function resolveTabGrid(PanelContext $context, ?Model $record = null): ?int
    {
        $value = static::tabGrid($context, $record);

        if (! is_int($value)) {
            return null;
        }

        return $value > 0 ? $value : null;
    }

    protected static function resolveTabContent(PanelContext $context, ?Model $record = null): string|int|null
    {
        $value = static::tabContent($context, $record);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = trim($value);

            return $normalized !== '' ? $normalized : null;
        }

        return null;
    }

    protected static function resolveOptionalString(?string $value): ?string
    {
        $normalized = is_string($value) ? trim($value) : '';

        return $normalized !== '' ? $normalized : null;
    }

    protected static function key(PanelContext $context, ?Model $record = null): string
    {
        return static::$key;
    }

    protected static function label(PanelContext $context, ?Model $record = null): ?string
    {
        return static::$label;
    }

    protected static function view(PanelContext $context, ?Model $record = null): ?string
    {
        return static::$view;
    }

    protected static function title(PanelContext $context, ?Model $record = null): ?string
    {
        return static::$title;
    }

    protected static function subtitle(PanelContext $context, ?Model $record = null): ?string
    {
        return static::$subtitle;
    }

    protected static function icon(PanelContext $context, ?Model $record = null): ?string
    {
        return static::$icon;
    }

    protected static function badge(PanelContext $context, ?Model $record = null): string|int|null
    {
        return static::$badge;
    }

    protected static function card(PanelContext $context, ?Model $record = null): ?bool
    {
        return static::$card;
    }

    protected static function widthContent(PanelContext $context, ?Model $record = null): string|int|float|null
    {
        return static::$widthContent;
    }

    protected static function paddingContent(PanelContext $context, ?Model $record = null): string|int|float|null
    {
        return static::$paddingContent;
    }

    protected static function tabColSpan(PanelContext $context, ?Model $record = null): string|int|null
    {
        return static::$tabColSpan;
    }

    protected static function tabGrid(PanelContext $context, ?Model $record = null): ?int
    {
        return static::$tabGrid;
    }

    protected static function tabContent(PanelContext $context, ?Model $record = null): string|int|null
    {
        return static::$tabContent;
    }

    protected static function fieldColSpan(PanelContext $context, ?Model $record = null): string|int|null
    {
        return static::$fieldColSpan;
    }

    protected static function isDefault(PanelContext $context, ?Model $record = null): bool
    {
        return static::$default;
    }

    protected static function schema(PanelContext $context, ?Model $record = null): array|Component|Closure
    {
        return [];
    }

    protected static function cards(PanelContext $context, ?Model $record = null): array|Closure
    {
        return [];
    }

    protected static function grid(PanelContext $context, ?Model $record = null): int
    {
        return static::$grid;
    }

    protected static function gap(PanelContext $context, ?Model $record = null): int|string|float
    {
        return static::$gap;
    }

    protected static function action(PanelContext $context, ?Model $record = null): Component|array|string|Closure|null
    {
        return null;
    }

    protected static function operation(PanelContext $context, ?Model $record = null): ?string
    {
        return null;
    }

    protected static function routed(PanelContext $context, ?Model $record = null): bool
    {
        return false;
    }

    protected static function url(PanelContext $context, ?Model $record = null): ?string
    {
        return null;
    }

    protected static function visible(PanelContext $context, ?Model $record = null): bool|Closure
    {
        return true;
    }

    /**
     * @return array<int, Component|array>
     */
    protected static function resolveCards(PanelContext $context, ?Model $record = null): array
    {
        $cards = static::cards($context, $record);

        if ($cards instanceof Closure) {
            $cards = static::invokeWithContext($cards, $context, $record);
        }

        if ($cards instanceof Component) {
            return [$cards];
        }

        if (! is_array($cards)) {
            return [];
        }

        $cardsGrid = static::normalizeCardsGrid(static::grid($context, $record), 1);
        $cardsGap = static::normalizeCardsGap(static::gap($context, $record), 4);
        $defaultCardContentWidth = static::resolveWidthContent($context, $record);

        $resolved = [];

        foreach ($cards as $card) {
            $resolved = [
                ...$resolved,
                ...static::normalizeCardDefinitionToNodes(
                    $card,
                    $context,
                    $record,
                    $cardsGrid,
                    $defaultCardContentWidth
                ),
            ];
        }

        if ($resolved === []) {
            return [];
        }

        return [
            Grid::make()
                ->cols($cardsGrid)
                ->gap($cardsGap)
                ->children(array_values($resolved)),
        ];
    }

    protected static function mergeCardsIntoSchema(
        array|Component|Closure $schema,
        array $cards
    ): array|Component|Closure {
        if ($schema instanceof Closure) {
            return function (...$args) use ($schema, $cards) {
                $resolved = $schema(...$args);

                if ($resolved instanceof Component) {
                    $resolved = [$resolved];
                }

                if (! is_array($resolved)) {
                    $resolved = [];
                }

                return array_values([
                    ...$cards,
                    ...$resolved,
                ]);
            };
        }

        if ($schema instanceof Component) {
            $schema = [$schema];
        }

        if (! is_array($schema)) {
            $schema = [];
        }

        return array_values([
            ...$cards,
            ...$schema,
        ]);
    }

    /**
     * @return array<int, Component|array>
     */
    protected static function normalizeCardDefinitionToNodes(
        mixed $card,
        PanelContext $context,
        ?Model $record = null,
        int $cardsGrid = 1,
        int|string|float|null $defaultContentWidth = null
    ): array {
        if ($card instanceof Component) {
            return [$card];
        }

        if ($card instanceof Closure) {
            return static::normalizeCardDefinitionToNodes(
                static::invokeWithContext($card, $context, $record),
                $context,
                $record,
                $cardsGrid,
                $defaultContentWidth
            );
        }

        if (! is_array($card)) {
            return [];
        }

        // List notation: the list itself is the card body.
        if (array_is_list($card)) {
            $children = static::normalizeCardChildren($card);
            if ($children === []) {
                return [];
            }

            return static::normalizeCardBlockNode(
                static::buildCardBlock(
                    title: null,
                    subtitle: null,
                    icon: null,
                    action: [],
                    children: $children,
                    colSpan: 1,
                    gridColumns: $cardsGrid,
                    contentCols: 12,
                    contentPadding: '4',
                    contentWidth: $defaultContentWidth
                )
            );
        }

        $title = static::resolveCardTextValue($card['title'] ?? null, $context, $record);
        $subtitle = static::resolveCardTextValue(
            $card['subtitle'] ?? $card['description'] ?? null,
            $context,
            $record
        );
        $icon = static::resolveCardTextValue($card['icon'] ?? null, $context, $record);
        $contentCols = static::normalizeCardCols($card['cols'] ?? 12, 12);
        $contentPadding = static::normalizeCardPadding($card['paddingContent'] ?? ($card['padding'] ?? '4'), '4');
        $contentWidth = static::normalizeCardWidth(
            $card['widthContent'] ?? $defaultContentWidth,
            $defaultContentWidth
        );
        $colSpan = static::normalizeCardCols(
            $card['colSpan'] ?? $card['colspan'] ?? $card['span'] ?? 1,
            1
        );
        $cardEnabled = static::normalizeCardBool($card['card'] ?? true, true);

        $childrenSource = $card['schema'] ?? $card['children'] ?? $card['content'] ?? [];
        if ($childrenSource instanceof Closure) {
            $childrenSource = static::invokeWithContext($childrenSource, $context, $record);
        }

        $children = static::normalizeCardChildren($childrenSource);
        if ($children === []) {
            return [];
        }

        if (! $cardEnabled) {
            return $children;
        }

        $actionSource = $card['action']
            ?? $card['actions']
            ?? $card['headerComponents']
            ?? $card['header_components']
            ?? null;
        if ($actionSource instanceof Closure) {
            $actionSource = static::invokeWithContext($actionSource, $context, $record);
        }

        $action = static::normalizeCardAction($actionSource);

        return static::normalizeCardBlockNode(
            static::buildCardBlock(
                title: $title,
                subtitle: $subtitle,
                icon: $icon,
                action: $action,
                children: $children,
                colSpan: $colSpan,
                gridColumns: $cardsGrid,
                contentCols: $contentCols,
                contentPadding: $contentPadding,
                contentWidth: $contentWidth
            )
        );
    }

    /**
     * @return array<int, Component|array>
     */
    protected static function normalizeCardChildren(mixed $children): array
    {
        if ($children instanceof Component) {
            return [$children];
        }

        if (! is_array($children)) {
            return [];
        }

        $resolved = [];

        foreach ($children as $child) {
            if ($child instanceof Component) {
                $resolved[] = $child;
                continue;
            }

            if (is_array($child) && array_key_exists('type', $child)) {
                $resolved[] = $child;
            }
        }

        return array_values($resolved);
    }

    /**
     * @return array<int, Component>
     */
    protected static function normalizeCardAction(mixed $action): array
    {
        if ($action instanceof Component) {
            return [$action];
        }

        if (is_string($action)) {
            $normalized = trim($action);
            if ($normalized !== '') {
                return [Button::make($normalized)];
            }

            return [];
        }

        if (! is_array($action)) {
            return [];
        }

        $resolved = [];

        foreach ($action as $item) {
            if ($item instanceof Component) {
                $resolved[] = $item;
                continue;
            }

            if (is_string($item) && trim($item) !== '') {
                $resolved[] = Button::make(trim($item));
            }
        }

        return array_values($resolved);
    }

    /**
     * @param array<int, Component|array> $children
     * @param array<int, Component> $action
     */
    protected static function buildCardBlock(
        ?string $title,
        ?string $subtitle,
        ?string $icon,
        array $action,
        array $children,
        string|int $colSpan = 1,
        int $gridColumns = 1,
        string|int $contentCols = 12,
        string|int|float $contentPadding = '4',
        string|int|float|null $contentWidth = null
    ): Component|array|null {
        return (new FormTabCardLayout(
            content: $children,
            card: true,
            title: $title !== null ? __($title) : null,
            subtitle: $subtitle !== null ? __($subtitle) : null,
            icon: $icon,
            action: $action,
            cols: $colSpan,
            gridColumns: $gridColumns,
            contentCols: $contentCols,
            contentPadding: $contentPadding,
            contentWidth: $contentWidth,
        ))->build();
    }

    /**
     * @return array<int, Component|array>
     */
    protected static function normalizeCardBlockNode(Component|array|null $node): array
    {
        if ($node instanceof Component) {
            return [$node];
        }

        if (is_array($node)) {
            return array_values($node);
        }

        return [];
    }

    protected static function normalizeCardBool(mixed $value, bool $default = false): bool
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

    protected static function normalizeCardCols(mixed $value, string|int $default = 12): string|int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : $default;
        }

        if (is_string($value)) {
            $normalized = trim($value);

            if ($normalized === '') {
                return $default;
            }

            return $normalized;
        }

        return $default;
    }

    protected static function resolveCardTextValue(
        mixed $value,
        PanelContext $context,
        ?Model $record = null
    ): ?string {
        if ($value instanceof Closure) {
            $value = static::invokeWithContext($value, $context, $record);
        }

        if (! is_scalar($value) && $value !== null) {
            return null;
        }

        return static::resolveOptionalString($value !== null ? (string) $value : null);
    }

    protected static function normalizeCardsGrid(mixed $value, int $default = 1): int
    {
        if (is_int($value)) {
            return max(1, min(12, $value));
        }

        if (is_string($value)) {
            $normalized = trim($value);
            if ($normalized === '' || ! is_numeric($normalized)) {
                return $default;
            }

            return max(1, min(12, (int) $normalized));
        }

        return $default;
    }

    protected static function normalizeCardsGap(mixed $value, int|string|float $default = 4): int|string|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = trim($value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return $default;
    }

    protected static function normalizeCardPadding(
        mixed $value,
        int|string|float $default = '4'
    ): int|string|float {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = trim($value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return $default;
    }

    protected static function normalizeCardWidth(
        mixed $value,
        int|string|float|null $default = null
    ): int|string|float|null {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = trim($value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return $default;
    }

    protected static function normalizeView(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return null;
        }

        if ($normalized === 'normal') {
            return 'default';
        }

        return in_array($normalized, ['default', 'card', 'cards', 'tabs'], true)
            ? $normalized
            : null;
    }

    protected static function resolveFieldColSpan(
        PanelContext $context,
        ?Model $record = null
    ): string|int|null {
        $value = static::fieldColSpan($context, $record);

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value)) {
            $normalized = trim($value);

            return $normalized !== '' ? $normalized : null;
        }

        return null;
    }

    protected static function applyDefaultFieldColSpan(
        array|Component|Closure $schema,
        string|int $fieldColSpan
    ): array|Component|Closure {
        if ($schema instanceof Closure) {
            return function (...$args) use ($schema, $fieldColSpan) {
                $resolved = $schema(...$args);

                return static::applyDefaultFieldColSpanToNode($resolved, $fieldColSpan);
            };
        }

        return static::applyDefaultFieldColSpanToNode($schema, $fieldColSpan);
    }

    protected static function applyDefaultFieldColSpanToNode(mixed $node, string|int $fieldColSpan): mixed
    {
        if ($node instanceof FieldComponent) {
            if (! static::hasExplicitColSpan($node)) {
                $node->colSpan($fieldColSpan);
            }
        }

        if ($node instanceof Component) {
            $children = $node->getChildrenComponents();
            if ($children !== []) {
                $resolvedChildren = [];

                foreach ($children as $child) {
                    $resolvedChildren[] = static::applyDefaultFieldColSpanToNode($child, $fieldColSpan);
                }

                $node->setChildrenComponents($resolvedChildren);
            }

            return $node;
        }

        if (is_array($node)) {
            $resolvedNodes = [];
            foreach ($node as $key => $entry) {
                $resolvedNodes[$key] = static::applyDefaultFieldColSpanToNode($entry, $fieldColSpan);
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

    protected static function invokeWithContext(Closure $closure, PanelContext $context, ?Model $record = null): mixed
    {
        $reflection = new \ReflectionFunction($closure);
        $parameterCount = $reflection->getNumberOfParameters();

        if ($parameterCount <= 0) {
            return $closure();
        }

        if ($parameterCount === 1) {
            return $closure($context);
        }

        return $closure($context, $record);
    }
}
