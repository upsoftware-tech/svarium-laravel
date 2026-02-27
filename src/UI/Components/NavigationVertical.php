<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\Services\NavigationService;
use Upsoftware\Svarium\UI\Component;

class NavigationVertical extends Component
{
    public static function make(string|int|null $navigationId = null): static
    {
        $instance = parent::make();

        if ($navigationId !== null) {
            $instance->navigationId($navigationId);
        }

        return $instance;
    }

    public function navigationId(string|int $navigationId): static
    {
        return $this->props([
            'navigation_id' => $navigationId,
            ...$this->resolveNavigationProps($navigationId),
        ]);
    }

    public function toArray(): array
    {
        $navigationId = $this->getProp('navigation_id');
        $hasResolvedNavigation = is_array($this->getProp('items'))
            || is_array($this->getProp('navigations'))
            || is_array($this->getProp('navigation'));

        if (! $hasResolvedNavigation && $navigationId !== null && $navigationId !== '') {
            $this->props($this->resolveNavigationProps($navigationId));
        }

        return parent::toArray();
    }

    protected function resolveNavigationProps(string|int $navigationId): array
    {
        $tree = NavigationService::make()->getTree($navigationId);
        $items = is_array($tree)
            ? ($tree['children'] ?? [])
            : [];

        return [
            'navigation' => $tree,
            'items' => $items,
            'navigations' => $items,
        ];
    }
}
