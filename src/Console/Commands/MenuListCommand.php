<?php

namespace Upsoftware\Svarium\Console\Commands;

use Upsoftware\Svarium\Menu\MenuRegistry;
use Upsoftware\Svarium\Services\NavigationService;

class MenuListCommand extends CoreCommand
{
    protected $signature = 'svarium:menu.list
        {--navigation= : Filter by navigation id (e.g. sidebar_user)}
        {--json : Output as JSON instead of table}';

    protected $description = 'Shows full menu structure as table (name, id, permissions, url)';

    public function handle(): int
    {
        /** @var MenuRegistry $registry */
        $registry = app(MenuRegistry::class);
        $navigationOption = $this->option('navigation');
        $navigationIds = $this->resolveNavigationIds($registry, $navigationOption);

        if ($navigationIds === []) {
            $this->warn('Brak zarejestrowanych nawigacji.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($navigationIds as $navigationId) {
            $tree = NavigationService::make()->getRegisteredTree($navigationId);
            $children = is_array($tree['children'] ?? null) ? $tree['children'] : [];
            $itemsByKey = $this->indexItemsByKey($registry->allForNavigation($navigationId));

            $this->collectRows(
                $children,
                $this->navigationIdLabel($navigationId),
                $itemsByKey,
                $rows,
                parent: null,
                depth: 0,
                pathIds: []
            );
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if ($rows === []) {
            $this->warn('Brak pozycji menu do wyświetlenia.');

            return self::SUCCESS;
        }

        $this->table(
            [
                'navigation',
                'name',
                'type',
                'node_id',
                'id',
                'parent',
                'menu_key',
                'permission',
                'url',
                'icon',
                'path_ids',
                'source',
            ],
            $rows
        );

        $this->newLine();
        $this->line('Tip: użyj --json dla automatyzacji.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string|int|null>
     */
    protected function resolveNavigationIds(MenuRegistry $registry, mixed $option): array
    {
        if (is_string($option) && trim($option) !== '') {
            return [$this->parseNavigationId($option)];
        }

        return $registry->navigationIds();
    }

    protected function parseNavigationId(string $raw): string|int|null
    {
        $value = trim($raw);

        if ($value === '' || strtolower($value) === 'default' || strtolower($value) === 'null') {
            return null;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        return $value;
    }

    protected function navigationIdLabel(string|int|null $navigationId): string
    {
        if ($navigationId === null) {
            return 'default';
        }

        return (string) $navigationId;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, array<string, mixed>>
     */
    protected function indexItemsByKey(array $items): array
    {
        $indexed = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = trim((string) ($item['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $indexed[$key] = $item;
        }

        return $indexed;
    }

    /**
     * @param array<int, mixed> $nodes
     * @param array<string, array<string, mixed>> $itemsByKey
     * @param array<int, array<string, string>> $rows
     * @param array<int, string> $pathIds
     */
    protected function collectRows(
        array $nodes,
        string $navigationLabel,
        array $itemsByKey,
        array &$rows,
        ?string $parent,
        int $depth,
        array $pathIds
    ): void {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $id = trim((string) ($node['id'] ?? ''));
            if (str_starts_with($id, 'navigation-static-dashboard:')) {
                continue;
            }

            $label = trim((string) ($node['label'] ?? ''));
            $url = trim((string) ($node['url'] ?? ''));
            $menuKey = trim((string) ($node['__menu_key'] ?? ''));
            $pathId = trim((string) ($node['__path_id'] ?? ''));
            $icon = is_array($node['icon'] ?? null)
                ? trim((string) ($node['icon']['value'] ?? ''))
                : trim((string) ($node['icon'] ?? ''));

            $children = is_array($node['children'] ?? null) ? $node['children'] : [];

            $type = trim((string) ($node['type'] ?? ''));
            if ($type === '') {
                $type = $children !== [] ? 'group' : 'item';
            }

            $nodeId = $pathId !== '' ? $pathId : ($menuKey !== '' ? $menuKey : $id);
            $currentPathIds = $pathIds;
            if ($pathId !== '') {
                $currentPathIds[] = $pathId;
            }

            $item = $menuKey !== '' ? ($itemsByKey[$menuKey] ?? []) : [];
            $permission = trim((string) ($item['permission'] ?? ''));
            $source = trim((string) ($item['source'] ?? ''));

            $rows[] = [
                'navigation' => $navigationLabel,
                'name' => str_repeat('  ', max(0, $depth)).($label !== '' ? $label : '(empty)'),
                'type' => $type,
                'node_id' => $nodeId,
                'id' => $id,
                'parent' => $parent ?? '',
                'menu_key' => $menuKey,
                'permission' => $permission,
                'url' => $url,
                'icon' => $icon,
                'path_ids' => implode('/', $currentPathIds),
                'source' => $source,
            ];

            if ($children !== []) {
                $this->collectRows(
                    $children,
                    $navigationLabel,
                    $itemsByKey,
                    $rows,
                    parent: $nodeId,
                    depth: $depth + 1,
                    pathIds: $currentPathIds
                );
            }
        }
    }
}

