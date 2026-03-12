<?php

namespace Upsoftware\Svarium\Console\Commands;

use Upsoftware\Svarium\Menu\MenuRegistry;
use Upsoftware\Svarium\Services\NavigationService;

class MenuMapCommand extends CoreCommand
{
    protected $signature = 'svarium:menu.map
        {--navigation= : Filter by navigation id (e.g. sidebar_user)}
        {--json : Output as JSON}';

    protected $description = 'Shows registered menu structure with IDs and path IDs';

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

        $map = [];
        foreach ($navigationIds as $navigationId) {
            $tree = NavigationService::make()->getRegisteredTree($navigationId);
            $children = is_array($tree['children'] ?? null) ? $tree['children'] : [];
            $map[$this->navigationIdLabel($navigationId)] = $this->normalizeNodes($children, []);
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        foreach ($map as $navigationLabel => $nodes) {
            $this->line('');
            $this->info("Navigation: {$navigationLabel}");

            if ($nodes === []) {
                $this->line('  (pusto)');
                continue;
            }

            $this->renderNodes($nodes, 0);
        }

        $this->line('');
        $this->line('Tip: użyj --json dla łatwego kopiowania i automatyzacji.');

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
     * @param array<int, mixed> $nodes
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeNodes(array $nodes, array $pathIdPrefix): array
    {
        $normalized = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $id = trim((string) ($node['id'] ?? ''));
            if (str_starts_with($id, 'navigation-static-dashboard:')) {
                continue;
            }

            $currentPathId = isset($node['__path_id']) ? trim((string) $node['__path_id']) : '';
            $currentPathIds = $pathIdPrefix;
            if ($currentPathId !== '') {
                $currentPathIds[] = $currentPathId;
            }

            $children = is_array($node['children'] ?? null)
                ? $this->normalizeNodes($node['children'], $currentPathIds)
                : [];

            $normalized[] = [
                'id' => $id,
                'type' => (string) ($node['type'] ?? (($children !== [] || (($node['url'] ?? null) === null)) ? 'group' : 'item')),
                'label' => (string) ($node['label'] ?? ''),
                'url' => isset($node['url']) ? (string) $node['url'] : null,
                'path_id' => $currentPathId !== '' ? $currentPathId : null,
                'path_ids' => $currentPathIds,
                'menu_key' => isset($node['__menu_key']) ? (string) $node['__menu_key'] : null,
                'icon' => is_array($node['icon'] ?? null)
                    ? (string) ($node['icon']['value'] ?? '')
                    : null,
                'children' => $children,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     */
    protected function renderNodes(array $nodes, int $depth): void
    {
        $indent = str_repeat('  ', $depth);

        foreach ($nodes as $node) {
            $type = (string) ($node['type'] ?? 'item');
            $label = trim((string) ($node['label'] ?? ''));
            $id = trim((string) ($node['id'] ?? ''));
            $menuKey = trim((string) ($node['menu_key'] ?? ''));
            $pathId = trim((string) ($node['path_id'] ?? ''));
            $pathIds = is_array($node['path_ids'] ?? null) ? $node['path_ids'] : [];
            $url = $node['url'] ?? null;
            $icon = trim((string) ($node['icon'] ?? ''));

            $meta = [];
            $nodeId = $pathId !== '' ? $pathId : ($menuKey !== '' ? $menuKey : '');
            if ($nodeId !== '') {
                $meta[] = "node_id: {$nodeId}";
            }
            if ($id !== '') {
                $meta[] = "id: {$id}";
            }
            if ($menuKey !== '') {
                $meta[] = "menu_key: {$menuKey}";
            }
            if ($pathId !== '') {
                $meta[] = "path_id: {$pathId}";
            }
            if ($pathIds !== []) {
                $meta[] = 'path_ids: '.implode('/', array_map(static fn (mixed $segment): string => trim((string) $segment), $pathIds));
            }
            if (is_string($url) && trim($url) !== '') {
                $meta[] = 'url: '.trim($url);
            }
            if ($icon !== '') {
                $meta[] = "icon: {$icon}";
            }

            $metaLabel = $meta !== [] ? ' ['.implode(' | ', $meta).']' : '';

            if ($type === 'separator') {
                $this->line($indent.'- [separator]'.$metaLabel);
            } elseif ($type === 'label') {
                $this->line($indent.'- [label] '.($label !== '' ? $label : '(empty)').$metaLabel);
            } elseif (($node['children'] ?? []) !== []) {
                $this->line($indent.'- [group] '.($label !== '' ? $label : '(empty)').$metaLabel);
                $this->renderNodes((array) $node['children'], $depth + 1);
            } else {
                $this->line($indent.'- [item] '.($label !== '' ? $label : '(empty)').$metaLabel);
            }
        }
    }
}
