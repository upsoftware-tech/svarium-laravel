<?php

namespace Upsoftware\Svarium\Widgets;

use Closure;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\UI\Component;

class Widget
{
    protected string $key;

    /**
     * @var array<int, string>
     */
    protected array $contexts = [];

    protected int $order = 0;

    /**
     * Supported values:
     * - int/string numeric (1..12)
     * - fractions: 1/2, 1/3, 2/3, 1/4, 3/4
     * - full
     */
    protected int|string|null $size = null;

    /**
     * Card wrapper mode for dashboard rendering.
     * Supported values: true, false, dashed, dotted, double.
     */
    protected bool|string $card = true;

    protected string $source = 'runtime';

    /**
     * @var array<string, mixed>
     */
    protected array $meta = [];

    /**
     * @var array<string, mixed>|Closure
     */
    protected array|Closure $data = [];

    /**
     * @var Component|array<int, Component>|Closure|null
     */
    protected Component|array|Closure|null $schema = null;

    /**
     * @var bool|Closure
     */
    protected bool|Closure $visible = true;

    /**
     * @param  Component|array<int, Component>|Closure|null  $schema
     */
    public function __construct(string $key, Component|array|Closure|null $schema = null)
    {
        $this->key = trim($key);
        $this->schema = $schema;
    }

    /**
     * @param  Component|array<int, Component>|Closure|null  $schema
     */
    public static function make(string $key, Component|array|Closure|null $schema = null): static
    {
        return new static($key, $schema);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function source(string $source): static
    {
        $normalized = trim($source);
        if ($normalized !== '') {
            $this->source = $normalized;
        }

        return $this;
    }

    public function order(int $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function size(int|string|null $size): static
    {
        if (is_string($size)) {
            $size = trim(strtolower($size));
            if ($size === '') {
                $size = null;
            }
        }

        $this->size = $size;

        return $this;
    }

    public function card(bool|string|null $card = true): static
    {
        if ($card === null) {
            $this->card = true;

            return $this;
        }

        if (is_bool($card)) {
            $this->card = $card;

            return $this;
        }

        $normalized = strtolower(trim($card));

        if ($normalized === '' || in_array($normalized, ['true', '1', 'yes', 'on', 'solid', 'default'], true)) {
            $this->card = true;

            return $this;
        }

        if (in_array($normalized, ['false', '0', 'no', 'off', 'none'], true)) {
            $this->card = false;

            return $this;
        }

        if (in_array($normalized, ['dashed', 'dotted', 'double'], true)) {
            $this->card = $normalized;

            return $this;
        }

        $this->card = true;

        return $this;
    }

    /**
     * @param  string|array<int, string>  $contexts
     */
    public function on(string|array $contexts): static
    {
        foreach ($this->normalizeContexts($contexts) as $context) {
            if (! in_array($context, $this->contexts, true)) {
                $this->contexts[] = $context;
            }
        }

        return $this;
    }

    /**
     * @param  string|array<int, string>  $contexts
     */
    public function context(string|array $contexts): static
    {
        return $this->on($contexts);
    }

    /**
     * @return array<int, string>
     */
    public function contexts(): array
    {
        return $this->contexts !== [] ? $this->contexts : ['dashboard'];
    }

    public function hasExplicitContexts(): bool
    {
        return $this->contexts !== [];
    }

    /**
     * @param  array<string, mixed>|Closure  $data
     */
    public function data(array|Closure $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @param  Component|array<int, Component>|Closure  $schema
     */
    public function schema(Component|array|Closure $schema): static
    {
        $this->schema = $schema;

        return $this;
    }

    /**
     * Alias for schema() for API consistency with UI components.
     *
     * @param  Component|array<int, Component>|Closure  $schema
     */
    public function children(Component|array|Closure $schema): static
    {
        return $this->schema($schema);
    }

    /**
     * Alias for schema().
     *
     * @param  Component|array<int, Component>|Closure  $schema
     */
    public function content(Component|array|Closure $schema): static
    {
        return $this->schema($schema);
    }

    /**
     * @param  bool|Closure  $visible
     */
    public function visible(bool|Closure $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Alias for visible().
     *
     * @param  bool|Closure  $visible
     */
    public function canView(bool|Closure $visible): static
    {
        return $this->visible($visible);
    }

    public function title(string $title): static
    {
        $this->meta['title'] = $title;

        return $this;
    }

    public function description(string $description): static
    {
        $this->meta['description'] = $description;

        return $this;
    }

    public function prop(string $key, mixed $value): static
    {
        $normalizedKey = trim($key);
        if ($normalizedKey === '') {
            return $this;
        }

        $this->meta[$normalizedKey] = $value;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    public function matchesContext(string $context): bool
    {
        $needle = trim($context);
        if ($needle === '') {
            return false;
        }

        foreach ($this->contexts() as $pattern) {
            if ($pattern === '*') {
                return true;
            }

            if (Str::is($pattern, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $args
     */
    public function resolveData(PanelContext $context, array $args = []): array
    {
        $data = $this->data;

        if ($data instanceof Closure) {
            $data = $data($context, ...$args);
        }

        if (! is_array($data)) {
            return [];
        }

        return $data;
    }

    /**
     * @param  array<int, mixed>  $args
     */
    public function isVisible(PanelContext $context, array $data = [], array $args = []): bool
    {
        $visible = $this->visible;

        if ($visible instanceof Closure) {
            return (bool) $visible($context, $data, ...$args);
        }

        return (bool) $visible;
    }

    /**
     * @param  array<int, mixed>  $args
     * @return array<int, Component>
     */
    public function resolveComponents(PanelContext $context, array $args = []): array
    {
        $data = $this->resolveData($context, $args);

        if (! $this->isVisible($context, $data, $args)) {
            return [];
        }

        $schema = $this->schema;

        if ($schema instanceof Closure) {
            $schema = $schema($data, $context, ...$args);
        }

        if ($schema === null) {
            return [];
        }

        $components = [];

        if ($schema instanceof Component) {
            $components[] = $schema;
        } elseif (is_array($schema)) {
            foreach ($schema as $component) {
                if (! $component instanceof Component) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Widget [%s] schema array may contain only Svarium components.',
                            $this->key
                        )
                    );
                }

                $components[] = $component;
            }
        } else {
            throw new InvalidArgumentException(
                sprintf(
                    'Widget [%s] schema must return Component or array<Component>.',
                    $this->key
                )
            );
        }

        $meta = [
            ...$this->meta(),
            'key' => $this->key(),
            'source' => $this->source,
            'contexts' => $this->contexts(),
            'order' => $this->getOrder(),
            'size' => $this->size,
            'span' => $this->resolveSpan(),
            'card' => $this->card,
            'data' => $data,
        ];

        foreach ($components as $component) {
            $component->prop('widget', $meta);
        }

        return $components;
    }

    /**
     * @param  string|array<int, string>  $contexts
     * @return array<int, string>
     */
    protected function normalizeContexts(string|array $contexts): array
    {
        $items = is_array($contexts) ? $contexts : [$contexts];

        $normalized = [];

        foreach ($items as $item) {
            if (! is_string($item)) {
                continue;
            }

            $value = trim($item);
            if ($value === '') {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }

    protected function resolveSpan(): int
    {
        $size = $this->size;

        if (is_int($size)) {
            return $this->clampSpan($size);
        }

        if (is_string($size) && is_numeric($size)) {
            return $this->clampSpan((int) $size);
        }

        if (! is_string($size)) {
            return 4;
        }

        return match (trim(strtolower($size))) {
            'full', '1/1' => 12,
            '1/2', 'half' => 6,
            '1/3', 'third' => 4,
            '2/3' => 8,
            '1/4', 'quarter' => 3,
            '3/4' => 9,
            default => 4,
        };
    }

    protected function clampSpan(int $span): int
    {
        if ($span < 1 || $span > 12) {
            return 4;
        }

        return $span;
    }
}
