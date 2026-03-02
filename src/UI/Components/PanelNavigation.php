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
            } elseif ($normalized !== '') {
                $instance->navigationId(ctype_digit($normalized) ? (int) $normalized : $name);
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

    public function vertical(string|int|null $navigationId = null): static
    {
        $this->variant('vertical');

        if ($navigationId !== null && $navigationId !== '') {
            $this->navigationId($navigationId);
        }

        return $this;
    }

    public function horizontal(string|int|null $navigationId = null): static
    {
        $this->variant('horizontal');

        if ($navigationId !== null && $navigationId !== '') {
            $this->navigationId($navigationId);
        }

        return $this;
    }

    public function navigationId(string|int $navigationId): static
    {
        return $this->prop('navigation_id', $navigationId);
    }

    public function menu(string|int $navigationId): static
    {
        return $this->navigationId($navigationId);
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
