<?php

namespace Upsoftware\Svarium\Services;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Collection;
use Upsoftware\Svarium\Menu\MenuRegistry;

class NavigationService
{
    protected Collection $items;

    public function __construct()
    {
        $this->items = collect();
    }

    public static function make(): self
    {
        return new static();
    }

    protected function getModelClass(): string
    {
        return config('svarium.models.navigation', \Upsoftware\Svarium\Models\Navigation::class);
    }

    /**
     * Pobiera drzewo z bazy danych i opcjonalnie łączy je z elementami statycznymi.
     */
    public function getTree(string|int|null $id = null): array
    {
        $modelClass = $this->getModelClass();
        $query = $modelClass::with('children')
            ->where('is_active', true)
            ->orderBy('order');

        if ($id === null || $id === '') {
            $query->whereNull('parent_id');

            return $query->get()
                ->map(fn ($item) => $this->formatItem($item))
                ->toArray();
        }

        $item = $this->resolveItemByIdentifier($query, $id);

        if ($item) {
            $tree = $this->formatItem($item);

            return $this->mergeRegisteredItems($tree, $id);
        }

        $staticChildren = $this->buildRegisteredChildren($id);

        if ($staticChildren === []) {
            return [];
        }

        return [
            'id' => 'navigation-static-root:'.(string) $id,
            'label' => is_scalar($id) ? (string) $id : 'navigation',
            'children' => $staticChildren,
        ];
    }

    public function getRegisteredTree(string|int|null $navigationId = null): array
    {
        $children = $this->buildRegisteredChildren($navigationId);

        return [
            'id' => 'navigation-runtime-root:'.(string) ($this->normalizeNavigationId($navigationId) ?? 'default'),
            'label' => 'Panel',
            'children' => $children,
        ];
    }

    protected function resolveItemByIdentifier($query, string|int $id): mixed
    {
        if (is_int($id)) {
            return $query->where('id', $id)->first();
        }

        $trimmed = trim($id);
        if ($trimmed === '') {
            return null;
        }

        if (ctype_digit($trimmed)) {
            return $query->where('id', (int) $trimmed)->first();
        }

        return $query->where('label', $trimmed)->first();
    }

    /**
     * Formatuje pojedynczy element (rekurencyjnie dla dzieci)
     */
    protected function formatItem($item): array
    {
        if ($item->type === 'root') {
            return [
                'id' => $item->hash,
                'label' => $item->label,
                'children' => collect($item->children)
                    ->map(fn($child) => $this->formatItem($child))
                    ->toArray(),
            ];
        } else if ($item->type === 'label') {
            return [
                'type' => 'label',
                'label' => $item->label,
            ];
        } else if ($item->type === 'separator') {
            return [
                'type' => 'separator',
            ];
        } else {
            return [
                'id' => $item->hash,
                'label' => $item->label,
                'icon' => $item->icon ? ['type' => $this->resolveIconType($item->icon), 'value' => $item->icon] : null,
                'url' => $item->route_name ? route($item->route_name, [], false) : $item->url,
                'children' => collect($item->children)
                    ->map(fn($child) => $this->formatItem($child))
                    ->toArray(),
            ];
        }
    }

    protected function mergeRegisteredItems(array $tree, string|int|null $navigationId): array
    {
        $children = is_array($tree['children'] ?? null)
            ? $tree['children']
            : [];

        $children = $this->appendRegisteredItems($children, $navigationId);

        $tree['children'] = $children;

        return $tree;
    }

    /**
     * @param array<int, array<string, mixed>> $children
     * @return array<int, array<string, mixed>>
     */
    protected function appendRegisteredItems(array $children, string|int|null $navigationId): array
    {
        $registered = app(MenuRegistry::class)->allForNavigation($navigationId);

        foreach ($registered as $item) {
            $path = is_array($item['path'] ?? null) ? $item['path'] : [];
            $branch = &$children;

            foreach ($path as $index => $segment) {
                $branch = &$this->findOrCreateGroupNode(
                    $branch,
                    (string) $segment,
                    array_slice($path, 0, $index + 1),
                    $navigationId
                );
            }

            $this->appendLeafNode($branch, $item, $navigationId, $path);
        }

        $children = $this->sortNodesByOrder($children);

        return $children;
    }

    /**
     * @param array<int, array<string, mixed>> $children
     * @return array<int, array<string, mixed>>
     */
    protected function buildRegisteredChildren(string|int|null $navigationId): array
    {
        return $this->appendRegisteredItems([], $navigationId);
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param array<int, string> $path
     * @return array<int, array<string, mixed>>
     */
    protected function &findOrCreateGroupNode(array &$nodes, string $label, array $path, string|int|null $navigationId): array
    {
        foreach ($nodes as $index => $node) {
            if (($node['type'] ?? 'item') === 'item'
                && ($node['label'] ?? null) === $label
                && is_array($node['children'] ?? null)
                && (($node['url'] ?? null) === null || ($node['url'] ?? null) === '')
            ) {
                return $nodes[$index]['children'];
            }
        }

        $nodes[] = [
            'id' => 'navigation-static-group:'.sha1((string) $this->normalizeNavigationId($navigationId).'|'.implode('/', $path)),
            'label' => $label,
            'icon' => null,
            'url' => null,
            'children' => [],
        ];

        $lastIndex = array_key_last($nodes);

        return $nodes[$lastIndex]['children'];
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param array<string, mixed> $item
     * @param array<int, string> $path
     */
    protected function appendLeafNode(array &$nodes, array $item, string|int|null $navigationId, array $path = []): void
    {
        $type = (string) ($item['type'] ?? 'item');
        $signature = (string) ($item['key'] ?? '');
        $label = (string) ($item['label'] ?? '');
        $resolvedUrl = $this->resolveItemUrl($item);
        $order = (int) ($item['order'] ?? 0);

        $isGroupDeclaration = $type === 'item'
            && $label !== ''
            && $resolvedUrl === null;

        foreach ($nodes as $node) {
            if (($node['__menu_key'] ?? null) === $signature) {
                return;
            }
        }

        if ($isGroupDeclaration) {
            $this->upsertGroupDeclarationNode(
                $nodes,
                $label,
                $item,
                $navigationId,
                [...$path, $label],
                $signature
            );

            return;
        }

        if ($type === 'separator') {
            $nodes[] = [
                'type' => 'separator',
                'order' => $order,
                '__menu_key' => $signature,
            ];

            return;
        }

        if ($type === 'label') {
            $nodes[] = [
                'type' => 'label',
                'label' => (string) ($item['label'] ?? ''),
                'order' => $order,
                '__menu_key' => $signature,
            ];

            return;
        }

        $iconValue = (string) ($item['icon'] ?? '');

        $nodes[] = [
            'id' => 'navigation-static-item:'.sha1((string) $this->normalizeNavigationId($navigationId).'|'.$signature),
            'label' => $label,
            'icon' => $iconValue !== ''
                ? ['type' => $this->resolveIconType($iconValue), 'value' => $iconValue]
                : null,
            'url' => $resolvedUrl,
            'children' => [],
            'order' => $order,
            '__menu_key' => $signature,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param array<string, mixed> $item
     * @param array<int, string> $fullPath
     */
    protected function upsertGroupDeclarationNode(
        array &$nodes,
        string $label,
        array $item,
        string|int|null $navigationId,
        array $fullPath,
        string $signature
    ): void {
        $iconValue = isset($item['icon']) ? trim((string) $item['icon']) : '';
        $order = isset($item['order']) ? (int) $item['order'] : 0;

        foreach ($nodes as $index => $node) {
            if (($node['type'] ?? 'item') === 'item'
                && ($node['label'] ?? null) === $label
                && is_array($node['children'] ?? null)
                && (($node['url'] ?? null) === null || ($node['url'] ?? null) === '')
            ) {
                if ($iconValue !== '') {
                    $nodes[$index]['icon'] = [
                        'type' => $this->resolveIconType($iconValue),
                        'value' => $iconValue,
                    ];
                }

                $nodes[$index]['order'] = $order;

                $nodes[$index]['__menu_key'] = $signature;

                return;
            }
        }

        $nodes[] = [
            'id' => 'navigation-static-group:'.sha1((string) $this->normalizeNavigationId($navigationId).'|'.implode('/', $fullPath)),
            'label' => $label,
            'icon' => $iconValue !== ''
                ? ['type' => $this->resolveIconType($iconValue), 'value' => $iconValue]
                : null,
            'url' => null,
            'children' => [],
            'order' => $order,
            '__menu_key' => $signature,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @return array<int, array<string, mixed>>
     */
    protected function sortNodesByOrder(array $nodes): array
    {
        $indexed = [];

        foreach ($nodes as $index => $node) {
            $node['__index'] = $index;

            if (is_array($node['children'] ?? null)) {
                $node['children'] = $this->sortNodesByOrder($node['children']);
            }

            $indexed[] = $node;
        }

        usort($indexed, function (array $left, array $right): int {
            $leftHasOrder = array_key_exists('order', $left);
            $rightHasOrder = array_key_exists('order', $right);

            if ($leftHasOrder && $rightHasOrder) {
                $leftOrder = (int) $left['order'];
                $rightOrder = (int) $right['order'];

                if ($leftOrder !== $rightOrder) {
                    return $leftOrder <=> $rightOrder;
                }

                $leftLabel = (string) ($left['label'] ?? '');
                $rightLabel = (string) ($right['label'] ?? '');
                $labelComparison = strcmp($leftLabel, $rightLabel);

                if ($labelComparison !== 0) {
                    return $labelComparison;
                }
            } elseif ($leftHasOrder !== $rightHasOrder) {
                return $leftHasOrder ? -1 : 1;
            }

            return ((int) ($left['__index'] ?? 0)) <=> ((int) ($right['__index'] ?? 0));
        });

        foreach ($indexed as &$node) {
            unset($node['__index']);
        }

        return $indexed;
    }

    /**
     * @param array<string, mixed> $item
     */
    protected function resolveItemUrl(array $item): ?string
    {
        $routeName = isset($item['route_name']) ? trim((string) $item['route_name']) : '';

        if ($routeName !== '') {
            if (Route::has($routeName)) {
                return route($routeName, [], false);
            }

            return null;
        }

        $url = isset($item['url']) ? trim((string) $item['url']) : '';

        return $url !== '' ? $url : null;
    }

    protected function normalizeNavigationId(string|int|null $navigationId): string|int|null
    {
        if ($navigationId === null) {
            return null;
        }

        if (is_int($navigationId)) {
            return $navigationId;
        }

        $trimmed = trim((string) $navigationId);
        if ($trimmed === '') {
            return null;
        }

        if (ctype_digit($trimmed)) {
            return (int) $trimmed;
        }

        return $trimmed;
    }

    public function root() {
        return [

        ];
    }

    public function resolveIconType(string $value): string
    {
        if (preg_match('~^lucide:[a-z0-9-]+$~i', $value)) {
            return 'icon';
        }

        if (preg_match('~^(?:[a-z0-9_-]+/)*[a-z0-9_-]+\.(png|svg|jpg|jpeg|webp)$~i', $value)) {
            return 'path';
        }

        return 'invalid';
    }

    public function addItem(string $label, string $route, ?string $icon = null): self
    {
        $this->items->push([
            'label' => $label,
            'route' => $route,
            'icon'  => $icon,
            'children' => []
        ]);

        return $this;
    }
}
