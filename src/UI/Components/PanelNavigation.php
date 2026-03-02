<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\Services\NavigationService;
use Upsoftware\Svarium\UI\Component;

class PanelNavigation extends Component
{
    public static function make(?string $name = null): static
    {
        $instance = parent::make($name)
            ->variant('vertical');

        if (is_string($name)) {
            $normalized = strtolower(trim($name));

            if (in_array($normalized, ['vertical', 'horizontal'], true)) {
                $instance->variant($normalized);
            } elseif ($normalized !== '' && ctype_digit($normalized)) {
                $instance->navigationId((int) $normalized);
            }
        }

        return $instance;
    }

    public function variant(string $variant): static
    {
        $normalized = strtolower(trim($variant));

        if (! in_array($normalized, ['vertical', 'horizontal'], true)) {
            throw new \InvalidArgumentException('PanelNavigation variant must be vertical or horizontal.');
        }

        return $this->prop('variant', $normalized);
    }

    public function vertical(): static
    {
        return $this->variant('vertical');
    }

    public function horizontal(): static
    {
        return $this->variant('horizontal');
    }

    public function navigationId(string|int $navigationId): static
    {
        return $this->prop('navigation_id', $navigationId);
    }

    public function toArray(): array
    {
        $hasResolvedNavigation = is_array($this->getProp('items'))
            || is_array($this->getProp('navigations'))
            || is_array($this->getProp('navigation'));

        if (! $hasResolvedNavigation) {
            $navigationId = $this->getProp('navigation_id');
            $tree = NavigationService::make()->getRegisteredTree(
                is_string($navigationId) || is_int($navigationId) ? $navigationId : null
            );

            $items = is_array($tree['children'] ?? null)
                ? $tree['children']
                : [];

            $this->props([
                'navigation' => $tree,
                'items' => $items,
                'navigations' => $items,
            ]);
        }

        $data = parent::toArray();
        $variant = (string) $this->getProp('variant', 'vertical');
        $data['type'] = $variant === 'horizontal'
            ? 'NavigationHorizontal'
            : 'NavigationVertical';

        unset($data['props']['variant']);

        return $data;
    }
}
