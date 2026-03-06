<?php

namespace Upsoftware\Svarium\Widgets;

use InvalidArgumentException;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\UI\Component;

class WidgetRegistry
{
    /**
     * @var array<string, Widget>
     */
    protected array $widgets = [];

    /**
     * @param  Widget|array<int, Widget|array<string, mixed>>  $widgets
     * @param  array<string, mixed>  $defaults
     */
    public function register(Widget|array $widgets, array $defaults = []): void
    {
        foreach ($this->normalizeMany($widgets, $defaults) as $widget) {
            $this->widgets[$widget->key()] = $widget;
        }
    }

    /**
     * @return array<string, Widget>
     */
    public function all(): array
    {
        return $this->widgets;
    }

    /**
     * @param  string|array<int, string>  $contexts
     * @param  array<int, mixed>  $args
     * @return array<int, Component>
     */
    public function componentsForContexts(
        string|array $contexts,
        PanelContext $context,
        array $args = []
    ): array {
        $normalizedContexts = $this->normalizeContexts($contexts);
        if ($normalizedContexts === []) {
            return [];
        }

        $resolved = [];

        foreach ($this->widgets as $widget) {
            $matches = false;

            foreach ($normalizedContexts as $contextName) {
                if ($widget->matchesContext($contextName)) {
                    $matches = true;
                    break;
                }
            }

            if (! $matches) {
                continue;
            }

            $resolved[] = [
                'order' => $widget->getOrder(),
                'key' => $widget->key(),
                'components' => $widget->resolveComponents($context, $args),
            ];
        }

        usort($resolved, static function (array $left, array $right): int {
            $orderCompare = ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0));
            if ($orderCompare !== 0) {
                return $orderCompare;
            }

            return strcmp((string) ($left['key'] ?? ''), (string) ($right['key'] ?? ''));
        });

        $components = [];

        foreach ($resolved as $entry) {
            foreach ($entry['components'] as $component) {
                if ($component instanceof Component) {
                    $components[] = $component;
                }
            }
        }

        return $components;
    }

    /**
     * @param  Widget|array<int, Widget|array<string, mixed>>  $widgets
     * @param  array<string, mixed>  $defaults
     * @return array<int, Widget>
     */
    protected function normalizeMany(Widget|array $widgets, array $defaults = []): array
    {
        $items = $widgets instanceof Widget ? [$widgets] : $widgets;

        $normalized = [];

        foreach ($items as $item) {
            $widget = $this->normalizeItem($item);

            if (! $widget->hasExplicitContexts()) {
                $defaultContexts = $defaults['contexts'] ?? [];
                if (is_string($defaultContexts) || is_array($defaultContexts)) {
                    $widget->on($defaultContexts);
                }
            }

            $source = trim((string) ($defaults['source'] ?? ''));
            if ($source !== '') {
                $widget->source($source);
            }

            if (isset($defaults['order']) && is_numeric($defaults['order'])) {
                $widget->order((int) $defaults['order']);
            }

            $normalized[] = $widget;
        }

        return $normalized;
    }

    /**
     * @param  Widget|array<string, mixed>  $item
     */
    protected function normalizeItem(Widget|array $item): Widget
    {
        if ($item instanceof Widget) {
            return $item;
        }

        $key = trim((string) ($item['key'] ?? ''));
        if ($key === '') {
            throw new InvalidArgumentException('Widget definition must contain non-empty key.');
        }

        $widget = Widget::make($key);

        if (array_key_exists('contexts', $item)) {
            $contexts = $item['contexts'];
            if (is_string($contexts) || is_array($contexts)) {
                $widget->on($contexts);
            }
        } elseif (array_key_exists('on', $item)) {
            $contexts = $item['on'];
            if (is_string($contexts) || is_array($contexts)) {
                $widget->on($contexts);
            }
        }

        if (array_key_exists('order', $item) && is_numeric($item['order'])) {
            $widget->order((int) $item['order']);
        }

        if (array_key_exists('size', $item) && (is_int($item['size']) || is_string($item['size']) || $item['size'] === null)) {
            $widget->size($item['size']);
        }

        if (array_key_exists('card', $item) && (is_bool($item['card']) || is_string($item['card']) || $item['card'] === null)) {
            $widget->card($item['card']);
        }

        if (array_key_exists('schema', $item)) {
            $schema = $item['schema'];
            if ($schema instanceof Component || $schema instanceof \Closure || is_array($schema)) {
                $widget->schema($schema);
            }
        }

        if (array_key_exists('data', $item) && (is_array($item['data']) || $item['data'] instanceof \Closure)) {
            $widget->data($item['data']);
        }

        if (array_key_exists('visible', $item) && (is_bool($item['visible']) || $item['visible'] instanceof \Closure)) {
            $widget->visible($item['visible']);
        }

        if (array_key_exists('title', $item) && is_string($item['title'])) {
            $widget->title($item['title']);
        }

        if (array_key_exists('description', $item) && is_string($item['description'])) {
            $widget->description($item['description']);
        }

        if (array_key_exists('source', $item) && is_string($item['source'])) {
            $widget->source($item['source']);
        }

        return $widget;
    }

    /**
     * @param  string|array<int, string>  $contexts
     * @return array<int, string>
     */
    protected function normalizeContexts(string|array $contexts): array
    {
        $items = is_array($contexts) ? $contexts : [$contexts];

        $normalized = [];

        foreach ($items as $context) {
            if (! is_string($context)) {
                continue;
            }

            $value = trim($context);
            if ($value === '') {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }
}
