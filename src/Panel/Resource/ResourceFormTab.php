<?php

namespace Upsoftware\Svarium\Panel\Resource;

use Closure;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Components\TabItem;

class ResourceFormTab
{
    protected string $key;
    protected string $label;
    protected ?string $title = null;
    protected ?string $subtitle = null;
    protected ?string $icon = null;
    protected string|int|null $badge = null;
    protected array|Component|Closure|null $schema = null;
    protected Component|array|string|Closure|null $action = null;
    protected ?string $operation = null;
    protected ?string $url = null;
    protected bool $default = false;
    protected bool $routed = false;
    protected bool|Closure $visible = true;
    protected bool|Closure|null $card = null;
    protected string|int|float|Closure|null $widthContent = null;
    protected string|int|float|Closure|null $paddingContent = null;
    protected string|int|Closure|null $colSpan = null;
    protected int|Closure|null $grid = null;
    protected string|int|Closure|null $contentCols = null;
    protected string|int|Closure|null $fieldColSpan = null;

    public function __construct(string $key)
    {
        $this->key = trim($key);
        $this->label = $this->key !== '' ? $this->key : 'tab';
    }

    public static function make(string $key): static
    {
        return new static($key);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = trim($icon) !== '' ? trim($icon) : null;

        return $this;
    }

    public function title(?string $title): static
    {
        $title = is_string($title) ? trim($title) : null;
        $this->title = $title !== '' ? $title : null;

        return $this;
    }

    public function subtitle(?string $subtitle): static
    {
        $subtitle = is_string($subtitle) ? trim($subtitle) : null;
        $this->subtitle = $subtitle !== '' ? $subtitle : null;

        return $this;
    }

    public function badge(string|int|null $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    public function schema(array|Component|Closure $schema): static
    {
        $this->schema = $schema;

        return $this;
    }

    public function content(array|Component|Closure $schema): static
    {
        return $this->schema($schema);
    }

    public function action(Component|array|string|Closure|null $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function operation(string $operation, bool $routed = true): static
    {
        $this->operation = $operation;
        $this->routed = $routed;

        return $this;
    }

    public function url(string $url): static
    {
        $this->url = trim($url);
        $this->routed = true;

        return $this;
    }

    public function default(bool $state = true): static
    {
        $this->default = $state;

        return $this;
    }

    public function routed(bool $state = true): static
    {
        $this->routed = $state;

        return $this;
    }

    public function visible(bool|Closure $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    public function card(bool|Closure|null $card = true): static
    {
        $this->card = $card;

        return $this;
    }

    public function widthContent(string|int|float|Closure|null $width = null): static
    {
        $this->widthContent = $width;

        return $this;
    }

    public function paddingContent(string|int|float|Closure|null $padding = null): static
    {
        $this->paddingContent = $padding;

        return $this;
    }

    public function colSpan(string|int|Closure|null $span = null): static
    {
        $this->colSpan = $span;

        return $this;
    }

    public function grid(int|Closure|null $columns = null): static
    {
        $this->grid = $columns;

        return $this;
    }

    public function contentCols(string|int|Closure|null $cols = null): static
    {
        $this->contentCols = $cols;

        return $this;
    }

    public function fieldColSpan(string|int|Closure|null $span = null): static
    {
        $this->fieldColSpan = $span;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->default;
    }

    public function isRouted(): bool
    {
        return $this->routed || $this->operation !== null || ($this->url !== null && $this->url !== '');
    }

    public function hasRouteTarget(): bool
    {
        if (is_string($this->operation) && trim($this->operation) !== '') {
            return true;
        }

        return $this->resolveUrl() !== null;
    }

    /**
     * A tab should navigate via URL only when it has an explicit route target
     * (`operation` or `url`) or when it was explicitly configured as routed
     * and has no inline schema.
     *
     * This protects local schema tabs from being accidentally treated as routed.
     */
    public function shouldNavigateWithRoute(): bool
    {
        if ($this->hasRouteTarget()) {
            return true;
        }

        if (! $this->routed) {
            return false;
        }

        return $this->schema === null;
    }

    public function shouldRender(PanelContext $context, ...$args): bool
    {
        if ($this->visible instanceof Closure) {
            return (bool) ($this->visible)($context, ...$args);
        }

        return (bool) $this->visible;
    }

    public function resolveCard(PanelContext $context, ...$args): ?bool
    {
        if ($this->card instanceof Closure) {
            return (bool) ($this->card)($context, ...$args);
        }

        if ($this->card === null) {
            return null;
        }

        return (bool) $this->card;
    }

    public function resolveWidthContent(PanelContext $context, ...$args): string|int|float|null
    {
        $value = $this->widthContent;

        if ($value instanceof Closure) {
            $value = $value($context, ...$args);
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = trim($value);

            return $normalized !== '' ? $normalized : null;
        }

        return null;
    }

    public function resolvePaddingContent(PanelContext $context, ...$args): string|int|float|null
    {
        $value = $this->paddingContent;

        if ($value instanceof Closure) {
            $value = $value($context, ...$args);
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = trim($value);

            return $normalized !== '' ? $normalized : null;
        }

        return null;
    }

    public function resolveColSpan(PanelContext $context, ...$args): string|int|null
    {
        $value = $this->colSpan;

        if ($value instanceof Closure) {
            $value = $value($context, ...$args);
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = trim($value);

            return $normalized !== '' ? $normalized : null;
        }

        return null;
    }

    public function resolveGrid(PanelContext $context, ...$args): ?int
    {
        $value = $this->grid;

        if ($value instanceof Closure) {
            $value = $value($context, ...$args);
        }

        if (! is_int($value)) {
            return null;
        }

        return $value > 0 ? $value : null;
    }

    public function resolveContentCols(PanelContext $context, ...$args): string|int|null
    {
        $value = $this->contentCols;

        if ($value instanceof Closure) {
            $value = $value($context, ...$args);
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = trim($value);

            return $normalized !== '' ? $normalized : null;
        }

        return null;
    }

    public function resolveFieldColSpan(PanelContext $context, ...$args): string|int|null
    {
        $value = $this->fieldColSpan;

        if ($value instanceof Closure) {
            $value = $value($context, ...$args);
        }

        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value)) {
            $normalized = trim($value);

            return $normalized !== '' ? $normalized : null;
        }

        return null;
    }

    public function resolveSchema(PanelContext $context, ...$args): array
    {
        $schema = $this->schema;

        if ($schema instanceof Closure) {
            $schema = $schema($context, ...$args);
        }

        if ($schema === null) {
            return [];
        }

        if ($schema instanceof Component) {
            return [$schema];
        }

        return is_array($schema) ? $schema : [];
    }

    public function resolveAction(PanelContext $context, ...$args): array
    {
        $action = $this->action;

        if ($action instanceof Closure) {
            $action = $action($context, ...$args);
        }

        if ($action === null) {
            return [];
        }

        if ($action instanceof Component) {
            return [$action];
        }

        if (is_string($action)) {
            $action = trim($action);

            if ($action === '') {
                return [];
            }

            return [\Upsoftware\Svarium\UI\Components\Button::make($action)];
        }

        return is_array($action) ? $action : [];
    }

    public function resolveOperation(): ?Operation
    {
        if (! is_string($this->operation) || $this->operation === '' || ! class_exists($this->operation)) {
            return null;
        }

        $operation = app($this->operation);

        return $operation instanceof Operation ? $operation : null;
    }

    public function resolveUrl(): ?string
    {
        if (! is_string($this->url)) {
            return null;
        }

        $url = trim($this->url);

        return $url !== '' ? $url : null;
    }

    public function resolveTitle(): ?string
    {
        if (is_string($this->title) && trim($this->title) !== '') {
            return __($this->title);
        }

        if (is_string($this->label) && trim($this->label) !== '') {
            return __($this->label);
        }

        return null;
    }

    public function resolveLabel(): string
    {
        $label = is_string($this->label) ? trim($this->label) : '';
        $title = is_string($this->title) ? trim($this->title) : '';

        if (($label === '' || $label === trim($this->key)) && $title !== '') {
            return __($title);
        }

        if ($label !== '') {
            return __($label);
        }

        if ($title !== '') {
            return __($title);
        }

        return __('tab');
    }

    public function resolveIcon(): ?string
    {
        return $this->icon;
    }

    public function resolveSubtitle(): ?string
    {
        if (! is_string($this->subtitle) || trim($this->subtitle) === '') {
            return null;
        }

        return __($this->subtitle);
    }

    public function toTabItem(?string $url = null, array $content = [], bool $active = false): TabItem
    {
        $item = TabItem::make($this->resolveLabel())
            ->prop('value', $this->key)
            ->prop('active', $active);

        if ($this->icon !== null) {
            $item->icon($this->icon);
        }

        if ($this->badge !== null) {
            $item->badge($this->badge);
        }

        $resolvedUrl = $url ?? $this->resolveUrl();
        if ($resolvedUrl !== null && $resolvedUrl !== '') {
            $item->url($resolvedUrl);
        } elseif ($content !== []) {
            $item->children($content);
        }

        return $item;
    }
}
