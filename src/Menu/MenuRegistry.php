<?php

namespace Upsoftware\Svarium\Menu;

use InvalidArgumentException;

class MenuRegistry
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $items = [];

    /**
     * @param array<int, mixed> $items
     * @param array<string, mixed> $defaults
     */
    public function register(array $items, array $defaults = []): void
    {
        foreach ($this->normalizeMany($items, $defaults) as $item) {
            $this->items[$item['key']] = $item;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allForNavigation(string|int|null $navigationId = null): array
    {
        $normalizedNavigationId = $this->normalizeNavigationId($navigationId);

        $filtered = array_values(array_filter(
            $this->items,
            function (array $item) use ($normalizedNavigationId): bool {
                $itemNavigationId = $item['navigation_id'] ?? null;

                if ($itemNavigationId === null) {
                    return true;
                }

                return $this->normalizeNavigationId($itemNavigationId) === $normalizedNavigationId;
            }
        ));

        usort($filtered, function (array $left, array $right): int {
            $leftOrder = (int) ($left['order'] ?? 0);
            $rightOrder = (int) ($right['order'] ?? 0);

            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            return strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return $filtered;
    }

    /**
     * @param array<int, mixed> $items
     * @param array<string, mixed> $defaults
     * @param array<int, string> $pathPrefix
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeMany(array $items, array $defaults, array $pathPrefix = []): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $normalized = [
                ...$normalized,
                ...$this->normalizeItem($item, $defaults, $pathPrefix),
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $rawItem
     * @param array<string, mixed> $defaults
     * @param array<int, string> $pathPrefix
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeItem(mixed $rawItem, array $defaults, array $pathPrefix): array
    {
        if ($rawItem instanceof MenuItem) {
            $rawItem = $rawItem->toArray();
        }

        if (! is_array($rawItem)) {
            throw new InvalidArgumentException('Menu item must be array or MenuItem instance.');
        }

        $type = strtolower(trim((string) ($rawItem['type'] ?? 'item')));
        if (! in_array($type, ['item', 'label', 'separator', 'group'], true)) {
            $type = 'item';
        }

        $label = isset($rawItem['label']) ? trim((string) $rawItem['label']) : '';
        $routeName = isset($rawItem['route_name']) ? trim((string) $rawItem['route_name']) : null;
        $url = isset($rawItem['url']) ? trim((string) $rawItem['url']) : null;
        $icon = isset($rawItem['icon']) ? trim((string) $rawItem['icon']) : null;
        $order = isset($rawItem['order']) ? (int) $rawItem['order'] : (int) ($defaults['order'] ?? 0);
        $source = isset($rawItem['source'])
            ? trim((string) $rawItem['source'])
            : (string) ($defaults['source'] ?? 'runtime');

        $navigationId = $rawItem['navigation_id']
            ?? $rawItem['navigation']
            ?? ($defaults['navigation_id'] ?? null);

        $path = [
            ...$pathPrefix,
            ...$this->normalizePath($rawItem['path'] ?? $rawItem['position'] ?? $rawItem['under'] ?? []),
        ];

        $children = isset($rawItem['children']) && is_array($rawItem['children'])
            ? $rawItem['children']
            : [];

        $results = [];

        $hasLeaf = $type === 'separator'
            || $type === 'label'
            || $type === 'item' && ($label !== '' || $routeName !== null || $url !== null);

        if ($hasLeaf && $type !== 'group') {
            $key = isset($rawItem['key']) && trim((string) $rawItem['key']) !== ''
                ? trim((string) $rawItem['key'])
                : sha1(json_encode([
                    'navigation' => $this->normalizeNavigationId($navigationId),
                    'path' => $path,
                    'type' => $type,
                    'label' => $label,
                    'route_name' => $routeName,
                    'url' => $url,
                    'source' => $source,
                ], JSON_UNESCAPED_UNICODE) ?: uniqid('menu_', true));

            $results[] = [
                'key' => $key,
                'navigation_id' => $navigationId,
                'type' => $type,
                'label' => $label,
                'icon' => $icon,
                'route_name' => $routeName !== '' ? $routeName : null,
                'url' => $url !== '' ? $url : null,
                'order' => $order,
                'path' => $path,
                'source' => $source,
            ];
        }

        $nestedPath = $path;
        if ($children !== [] && $label !== '') {
            $nestedPath[] = $label;
        }

        if ($children !== []) {
            $results = [
                ...$results,
                ...$this->normalizeMany($children, $defaults, $nestedPath),
            ];
        }

        return $results;
    }

    /**
     * @return array<int, string>
     */
    protected function normalizePath(mixed $path): array
    {
        if (is_string($path)) {
            $parts = preg_split('/\s*\/\s*/', trim($path)) ?: [];
            $path = $parts;
        }

        if (! is_array($path)) {
            return [];
        }

        $normalized = [];

        foreach ($path as $segment) {
            if (! is_scalar($segment)) {
                continue;
            }

            $value = trim((string) $segment);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return $normalized;
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
}
