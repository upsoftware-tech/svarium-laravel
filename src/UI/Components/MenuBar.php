<?php

namespace Upsoftware\Svarium\UI\Components;

use Illuminate\Contracts\Support\Arrayable;
use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class MenuBar extends Component
{
    use HasChildren;

    public static function make(array|string|null $name = null): static
    {
        $instance = new static;

        if (is_string($name) && trim($name) !== '') {
            $instance->name($name);
            $instance->prop('name', trim($name));
        }

        return $instance;
    }

    public function items(array|Arrayable $items): static
    {
        if ($items instanceof Arrayable) {
            $items = $items->toArray();
        }

        return $this->prop('items', $items);
    }

    public function menus(array|Arrayable $items): static
    {
        return $this->items($items);
    }

    public function menu(string $label, array $children = [], array $props = []): static
    {
        $menu = [
            'label' => $label,
            'children' => $children,
        ];

        foreach ($props as $key => $value) {
            $menu[$key] = $value;
        }

        $items = $this->getProp('items', []);
        if (! is_array($items)) {
            $items = [];
        }

        $items[] = $menu;

        return $this->prop('items', $items);
    }

    public function fromSidebar(bool $applyOverrides = true): static
    {
        return $this->fromMenu('main_menu', $applyOverrides);
    }

    public function fromMenu(string|int|null $navigationId = 'main_menu', bool $applyOverrides = true): static
    {
        if (! function_exists('menu_children')) {
            return $this->items([]);
        }

        $nodes = menu_children($navigationId, $applyOverrides);
        if (! is_array($nodes)) {
            return $this->items([]);
        }

        $items = array_values(array_filter(array_map(
            static fn (mixed $node): ?array => self::mapNavigationNodeToMenuBarItem($node),
            $nodes
        )));

        return $this->items($items);
    }

    public function align(string $align): static
    {
        $normalized = strtolower(trim($align));
        if (! in_array($normalized, ['start', 'center', 'end'], true)) {
            $normalized = 'start';
        }

        return $this->prop('align', $normalized);
    }

    public function default(mixed $value): static
    {
        return $this->prop('defaultValue', $value);
    }

    public function value(mixed $value): static
    {
        return $this->prop('value', $value);
    }

    public function modelValue(mixed $value): static
    {
        return $this->prop('modelValue', $value);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function mapNavigationNodeToMenuBarItem(mixed $node): ?array
    {
        if (! is_array($node)) {
            return null;
        }

        $type = self::normalizeString($node['type'] ?? 'item');
        if ($type === '') {
            $type = 'item';
        }

        $label = self::normalizeString($node['label'] ?? '');
        $icon = self::normalizeIcon($node['icon'] ?? null);
        $url = self::normalizeString($node['url'] ?? '');

        $childrenRaw = $node['children'] ?? [];
        $children = [];
        if (is_array($childrenRaw)) {
            $children = array_values(array_filter(array_map(
                static fn (mixed $child): ?array => self::mapNavigationNodeToMenuBarItem($child),
                $childrenRaw
            )));
        }

        if ($type === 'separator') {
            return ['type' => 'separator'];
        }

        $item = [];

        if ($type !== '') {
            $item['type'] = $type;
        }

        if ($label !== '') {
            $item['label'] = $label;
        }

        if ($icon !== '') {
            $item['icon'] = $icon;
        }

        if ($url !== '') {
            $item['href'] = $url;
        }

        if ($children !== []) {
            $item['children'] = $children;
            if (($item['type'] ?? 'item') === 'item') {
                $item['type'] = 'submenu';
            }
        }

        if ($item === []) {
            return null;
        }

        return $item;
    }

    protected static function normalizeString(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return trim((string) $value);
        }

        return '';
    }

    protected static function normalizeIcon(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return trim((string) $value);
        }

        if (! is_array($value)) {
            return '';
        }

        $candidates = [
            $value['value'] ?? null,
            $value['icon'] ?? null,
            $value['default'] ?? null,
            $value['light'] ?? null,
            $value['dark'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $normalized = self::normalizeString($candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }
}
