<?php

namespace Upsoftware\Svarium\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Upsoftware\Svarium\Menu\MenuRegistry;
use Upsoftware\Svarium\Panel\OperationRegistry;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Modules\ModuleRegistry as SvariumModuleRegistry;
use Upsoftware\Svarium\Panel\Panel;
use Upsoftware\Svarium\Panel\PanelRegistry;
use Upsoftware\Svarium\Routing\SvariumHttpKernel;
use Upsoftware\Svarium\Support\PermissionMatcher;

class NavigationService
{
    protected Collection $items;
    protected array $routeAccessCache = [];
    protected ?object $resolvedUser = null;
    protected bool $resolvedUserLoaded = false;
    protected ?array $menuOverridesCache = null;
    protected ?array $menuCustomNodesCache = null;

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
        $id = $this->resolveRuntimeNavigationId($id);

        $modelClass = $this->getModelClass();
        $query = $modelClass::with('children')
            ->where('is_active', true)
            ->orderBy('order');

        if ($id === null || $id === '') {
            $query->whereNull('parent_id');

            $tree = $query->get()
                ->map(fn ($item) => $this->formatItem($item))
                ->filter(static fn (mixed $item): bool => is_array($item))
                ->values()
                ->toArray();

            return $this->pruneNavigationNodes($tree);
        }

        $item = $this->resolveItemByIdentifier($query, $id);

        if ($item) {
            $tree = $this->formatItem($item);
            if (! is_array($tree)) {
                return [];
            }

            $tree = $this->mergeRegisteredItems($tree, $id);
            $tree['children'] = $this->pruneNavigationNodes(
                is_array($tree['children'] ?? null) ? $tree['children'] : []
            );

            return $tree;
        }

        $staticChildren = $this->buildRegisteredChildren($id);

        if ($staticChildren === []) {
            return [];
        }

        $tree = [
            'id' => 'navigation-static-root:'.(string) $id,
            'label' => is_scalar($id) ? (string) $id : 'navigation',
            'children' => $staticChildren,
        ];

        $tree['children'] = $this->pruneNavigationNodes($tree['children']);

        return $tree;
    }

    public function getRegisteredTree(string|int|null $navigationId = null, bool $applyOverrides = true): array
    {
        $resolvedNavigationId = $this->resolveRuntimeNavigationId($navigationId);
        $children = $this->buildRegisteredChildren($resolvedNavigationId, $applyOverrides);

        return [
            'id' => 'navigation-runtime-root:'.(string) ($this->normalizeNavigationId($resolvedNavigationId) ?? 'default'),
            'label' => 'Panel',
            'children' => $children,
        ];
    }

    protected function resolveRuntimeNavigationId(string|int|null $navigationId): string|int|null
    {
        $normalized = $this->normalizeNavigationId($navigationId);
        if ($normalized !== null) {
            return $normalized;
        }

        if (! (bool) config('upsoftware.navigation.per_role.enabled', true)) {
            return null;
        }

        $map = (array) config('upsoftware.navigation.per_role.map', []);
        if ($map === []) {
            return null;
        }

        $activeRole = function_exists('get_role') ? get_role() : null;
        if (! is_array($activeRole) || $activeRole === []) {
            return null;
        }

        $roleId = trim((string) ($activeRole['id'] ?? ''));
        $roleKey = strtolower(trim((string) ($activeRole['role_key'] ?? '')));
        $roleNameLocale = strtolower(trim((string) ($activeRole['name_locale'] ?? '')));
        $roleName = strtolower(trim((string) ($activeRole['name'] ?? '')));

        $candidates = array_values(array_unique(array_filter([
            $roleKey,
            $roleNameLocale,
            $roleName,
            $roleId !== '' ? 'id:'.$roleId : '',
            $roleId,
        ], static fn (string $value): bool => $value !== '')));

        foreach ($candidates as $candidate) {
            foreach ($map as $mapKey => $targetNavigation) {
                $normalizedMapKey = strtolower(trim((string) $mapKey));
                if ($normalizedMapKey === '' || $normalizedMapKey !== $candidate) {
                    continue;
                }

                $resolved = $this->normalizeNavigationId(
                    is_string($targetNavigation) || is_int($targetNavigation) ? $targetNavigation : null
                );

                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }

        return null;
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
    protected function formatItem($item): ?array
    {
        $permission = trim((string) ($item->permission ?? ''));
        $routeName = trim((string) ($item->route_name ?? ''));
        $url = trim((string) ($item->url ?? ''));

        if (! $this->canViewMenuTarget($routeName, $url, $permission)) {
            return null;
        }

        if ($item->type === 'root') {
            $children = collect($item->children)
                ->map(fn ($child) => $this->formatItem($child))
                ->filter(static fn (mixed $child): bool => is_array($child))
                ->values()
                ->toArray();

            if ($children === []) {
                return null;
            }

            return [
                'id' => $item->hash,
                'label' => $item->label,
                'children' => $children,
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
                    ->map(fn ($child) => $this->formatItem($child))
                    ->filter(static fn (mixed $child): bool => is_array($child))
                    ->values()
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
        $declaredGroups = $this->collectDeclaredGroups($registered);
        $pathIdParentMap = $this->buildPathIdParentMap($registered);

        foreach ($registered as $item) {
            $source = (string) ($item['source'] ?? '');
            $rawPath = is_array($item['path'] ?? null)
                ? array_values(array_map(
                    static fn (mixed $segment): string => trim((string) $segment),
                    $item['path']
                ))
                : [];
            $path = array_values(array_map(
                fn (mixed $segment): string => $this->translateMenuLabel((string) $segment, $source),
                $rawPath
            ));
            $pathIds = is_array($item['path_ids'] ?? null)
                ? array_values(array_map(
                    static fn (mixed $segment): string => trim((string) $segment),
                    $item['path_ids']
                ))
                : [];
            $parentId = trim((string) ($item['parent_id'] ?? ''));
            $pathIds = $this->prependParentPathIds($pathIds, $parentId, $pathIdParentMap);
            $branch = &$children;

            $segmentsCount = max(count($path), count($pathIds));
            for ($index = 0; $index < $segmentsCount; $index++) {
                $segmentPathId = $pathIds[$index] ?? null;
                $segment = trim((string) ($path[$index] ?? ''));

                if ($segment === '' && is_string($segmentPathId) && trim($segmentPathId) !== '') {
                    $segment = trim((string) $segmentPathId);
                }

                $declaredGroup = is_string($segmentPathId)
                    ? ($declaredGroups[trim($segmentPathId)] ?? null)
                    : null;

                if (is_array($declaredGroup)) {
                    $declaredLabel = trim((string) ($declaredGroup['label'] ?? ''));
                    if ($declaredLabel !== '') {
                        $segment = $declaredLabel;
                    }
                }

                if ($segment === '') {
                    continue;
                }

                $branch = &$this->findOrCreateGroupNode(
                    $branch,
                    $segment,
                    array_slice($rawPath, 0, $index + 1),
                    array_slice($pathIds, 0, $index + 1),
                    $segmentPathId,
                    $navigationId
                );
            }

            $this->appendLeafNode($branch, $item, $navigationId, $rawPath);
        }

        $children = $this->sortNodesByOrder($children);

        return $children;
    }

    /**
     * @param array<int, array<string, mixed>> $registered
     * @return array<string, string>
     */
    protected function buildPathIdParentMap(array $registered): array
    {
        $map = [];

        foreach ($registered as $item) {
            $pathId = trim((string) ($item['path_id'] ?? ''));
            if ($pathId === '') {
                continue;
            }

            $parentId = trim((string) ($item['parent_id'] ?? ''));
            if ($parentId === '' || $parentId === $pathId) {
                continue;
            }

            $map[$pathId] = $parentId;
        }

        return $map;
    }

    /**
     * @param array<int, string> $pathIds
     * @param array<string, string> $pathIdParentMap
     * @return array<int, string>
     */
    protected function prependParentPathIds(array $pathIds, string $parentId, array $pathIdParentMap): array
    {
        $parentId = trim($parentId);
        if ($parentId === '') {
            return $pathIds;
        }

        $chain = [];
        $visited = [];
        $cursor = $parentId;
        $safety = 0;

        while ($cursor !== '' && $safety < 100) {
            if (isset($visited[$cursor])) {
                break;
            }

            $visited[$cursor] = true;
            array_unshift($chain, $cursor);
            $cursor = trim((string) ($pathIdParentMap[$cursor] ?? ''));
            $safety++;
        }

        if ($chain === []) {
            return $pathIds;
        }

        foreach ($pathIds as $pathId) {
            $normalizedPathId = trim((string) $pathId);
            if ($normalizedPathId === '') {
                continue;
            }

            if (! in_array($normalizedPathId, $chain, true)) {
                $chain[] = $normalizedPathId;
            }
        }

        return $chain;
    }

    /**
     * @param array<int, array<string, mixed>> $registered
     * @return array<string, array{label: string}>
     */
    protected function collectDeclaredGroups(array $registered): array
    {
        $groups = [];

        foreach ($registered as $item) {
            $type = (string) ($item['type'] ?? 'item');
            $label = trim((string) ($item['label'] ?? ''));
            $pathId = trim((string) (
                $item['path_id']
                ?? (is_array($item['path_ids'] ?? null) ? ($item['path_ids'][array_key_last($item['path_ids'])] ?? null) : null)
                ?? ''
            ));
            $routeName = trim((string) ($item['route_name'] ?? ''));
            $url = trim((string) ($item['url'] ?? ''));

            if ($type !== 'item' || $label === '' || $pathId === '') {
                continue;
            }

            if ($routeName !== '' || $url !== '') {
                continue;
            }

            $groups[$pathId] = ['label' => $this->translateMenuLabel($label, (string) ($item['source'] ?? ''))];
        }

        return $groups;
    }

    /**
     * @param array<int, array<string, mixed>> $children
     * @return array<int, array<string, mixed>>
     */
    protected function buildRegisteredChildren(string|int|null $navigationId, bool $applyOverrides = true): array
    {
        $children = $this->appendRegisteredItems([], $navigationId);
        $children = $this->ensureDashboardNode($children, $navigationId);
        $children = $this->appendCustomNodes($children, $navigationId);
        if ($applyOverrides) {
            $children = $this->applyMenuOverridesToNodes($children, $navigationId);
        }

        return $this->pruneNavigationNodes($children);
    }

    /**
     * Ensure dashboard is always present in runtime panel navigation.
     *
     * @param array<int, array<string, mixed>> $children
     * @return array<int, array<string, mixed>>
     */
    protected function ensureDashboardNode(array $children, string|int|null $navigationId = null): array
    {
        if (! $this->isMainNavigationId($navigationId)) {
            return $this->sortNodesByOrder($this->removeDashboardNodes($children));
        }

        if (! $this->isDashboardVisible()) {
            return $this->sortNodesByOrder($this->removeDashboardNodes($children));
        }

        $dashboardUrl = $this->resolveDashboardUrl();

        foreach ($children as $index => $node) {
            $url = trim((string) ($node['url'] ?? ''));
            if ($url === '' || ! is_array($node['children'] ?? null) || $node['children'] !== []) {
                continue;
            }

            if ($url === $dashboardUrl) {
                $children[$index]['__is_dashboard'] = true;

                return $this->sortNodesByOrder($children);
            }
        }

        $children[] = [
            'id' => 'navigation-static-dashboard:'.sha1($dashboardUrl),
            'label' => __('Dashboard'),
            'icon' => ['type' => 'icon', 'value' => 'lucide:layout-dashboard'],
            'url' => $dashboardUrl,
            'children' => [],
            'order' => 0,
            '__is_dashboard' => true,
        ];

        return $this->sortNodesByOrder($children);
    }

    protected function isMainNavigationId(string|int|null $navigationId): bool
    {
        $normalized = $this->normalizeNavigationId($navigationId);
        if ($normalized === null) {
            return true;
        }

        if (is_int($normalized)) {
            return false;
        }

        $value = strtolower(trim($normalized));

        return in_array($value, ['main_menu', 'main', 'default', '__default'], true);
    }

    protected function resolveDashboardUrl(): string
    {
        $panel = $this->resolveCurrentPanel();

        return svarium_panel_root_path($panel?->name);
    }

    protected function isDashboardVisible(): bool
    {
        $panel = $this->resolveCurrentPanel();

        return svarium_panel_dashboard_visible($panel?->name);
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @return array<int, array<string, mixed>>
     */
    protected function removeDashboardNodes(array $nodes): array
    {
        $dashboardUrl = $this->resolveDashboardUrl();
        $cleaned = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            if ($children !== []) {
                $node['children'] = $this->removeDashboardNodes($children);
            }

            if ($this->isDashboardNode($node, $dashboardUrl)) {
                continue;
            }

            $cleaned[] = $node;
        }

        return array_values($cleaned);
    }

    /**
     * @param array<string, mixed> $node
     */
    protected function isDashboardNode(array $node, string $dashboardUrl): bool
    {
        if ((bool) ($node['__is_dashboard'] ?? false)) {
            return true;
        }

        $id = trim((string) ($node['id'] ?? ''));
        if ($id !== '' && str_starts_with($id, 'navigation-static-dashboard:')) {
            return true;
        }

        $url = trim((string) ($node['url'] ?? ''));
        if ($url !== '' && $url === $dashboardUrl) {
            return true;
        }

        return false;
    }

    protected function resolveCurrentPanel(): ?Panel
    {
        /** @var PanelRegistry $registry */
        $registry = app(PanelRegistry::class);

        $panelName = request()->attributes->get('panel');
        if (is_string($panelName) && trim($panelName) !== '') {
            $panel = $registry->get(trim($panelName));
            if ($panel instanceof Panel) {
                return $panel;
            }
        }

        $configuredPanelName = trim((string) config('upsoftware.panel.name', ''));
        if ($configuredPanelName !== '') {
            $panel = $registry->get($configuredPanelName);
            if ($panel instanceof Panel) {
                return $panel;
            }
        }

        foreach ($registry->all() as $panel) {
            if ($panel instanceof Panel) {
                return $panel;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param array<int, string> $path
     * @return array<int, array<string, mixed>>
     */
    protected function &findOrCreateGroupNode(
        array &$nodes,
        string $label,
        array $path,
        array $pathIds,
        string|null $segmentId,
        string|int|null $navigationId
    ): array
    {
        $normalizedSegmentId = is_string($segmentId) ? trim($segmentId) : '';
        $groupSignature = $normalizedSegmentId !== ''
            ? 'pid:'.$normalizedSegmentId
            : 'path:'.sha1(implode('/', $path));
        $expectedGroupId = 'navigation-static-group:'.sha1((string) $this->normalizeNavigationId($navigationId).'|'.($pathIds !== [] ? implode('/', $pathIds) : implode('/', $path)));

        foreach ($nodes as $index => $node) {
            $nodePathId = trim((string) ($node['__path_id'] ?? ''));
            $nodeGroupSignature = trim((string) ($node['__group_signature'] ?? ''));
            $nodeId = trim((string) ($node['id'] ?? ''));

            $matchesPathId = $normalizedSegmentId !== '' && $nodePathId !== '' && $nodePathId === $normalizedSegmentId;
            $matchesGroupSignature = $nodeGroupSignature !== '' && $nodeGroupSignature === $groupSignature;
            $matchesExpectedId = $nodeId !== '' && $nodeId === $expectedGroupId;

            if (($node['type'] ?? 'item') === 'item'
                && ($matchesPathId || $matchesGroupSignature || $matchesExpectedId)
                && is_array($node['children'] ?? null)
                && (($node['url'] ?? null) === null || ($node['url'] ?? null) === '')
            ) {
                if (($matchesPathId || $matchesGroupSignature || $matchesExpectedId) && ($node['label'] ?? null) !== $label && $label !== '') {
                    $nodes[$index]['label'] = $label;
                }
                $nodes[$index]['__group_signature'] = $groupSignature;

                return $nodes[$index]['children'];
            }
        }

        $idSignature = $pathIds !== []
            ? implode('/', $pathIds)
            : implode('/', $path);

        $nodes[] = [
            'id' => 'navigation-static-group:'.sha1((string) $this->normalizeNavigationId($navigationId).'|'.$idSignature),
            'label' => $label,
            'icon' => null,
            'url' => null,
            'children' => [],
            '__path_id' => $normalizedSegmentId !== '' ? $normalizedSegmentId : null,
            '__group_signature' => $groupSignature,
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
        $source = (string) ($item['source'] ?? '');
        $label = $this->translateMenuLabel((string) ($item['label'] ?? ''), $source);
        $resolvedUrl = $this->resolveItemUrl($item);
        $permission = trim((string) ($item['permission'] ?? ''));
        $order = (int) ($item['order'] ?? 0);
        $isDashboard = $this->isDashboardItem($item, $resolvedUrl);

        $isGroupDeclaration = $type === 'item'
            && $label !== ''
            && $resolvedUrl === null;

        if (! $this->canViewMenuTarget((string) ($item['route_name'] ?? ''), (string) ($resolvedUrl ?? ''), $permission)) {
            return;
        }

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
                'label' => $label,
                'order' => $order,
                '__menu_key' => $signature,
            ];

            return;
        }

        $iconValue = (string) ($item['icon'] ?? '');
        $pathId = trim((string) ($item['path_id'] ?? ''));

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
            '__is_dashboard' => $isDashboard,
            '__path_id' => $pathId !== '' ? $pathId : null,
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
        $pathId = trim((string) (
            $item['path_id']
            ?? (is_array($item['path_ids'] ?? null) ? ($item['path_ids'][array_key_last($item['path_ids'])] ?? null) : null)
            ?? ''
        ));
        $groupSignature = $pathId !== ''
            ? 'pid:'.$pathId
            : 'path:'.sha1(implode('/', $fullPath));
        $expectedGroupId = 'navigation-static-group:'.sha1((string) $this->normalizeNavigationId($navigationId).'|'.($pathId !== '' ? $pathId : implode('/', $fullPath)));

        foreach ($nodes as $index => $node) {
            $nodePathId = trim((string) ($node['__path_id'] ?? ''));
            $nodeGroupSignature = trim((string) ($node['__group_signature'] ?? ''));
            $nodeId = trim((string) ($node['id'] ?? ''));
            $matchesPathId = $pathId !== '' && $nodePathId !== '' && $nodePathId === $pathId;
            $matchesGroupSignature = $nodeGroupSignature !== '' && $nodeGroupSignature === $groupSignature;
            $matchesExpectedId = $nodeId !== '' && $nodeId === $expectedGroupId;

            if (($node['type'] ?? 'item') === 'item'
                && ($matchesPathId || $matchesGroupSignature || $matchesExpectedId)
                && is_array($node['children'] ?? null)
                && (($node['url'] ?? null) === null || ($node['url'] ?? null) === '')
            ) {
                if (($matchesPathId || $matchesGroupSignature || $matchesExpectedId) && ($node['label'] ?? null) !== $label && $label !== '') {
                    $nodes[$index]['label'] = $label;
                }

                if ($iconValue !== '') {
                    $nodes[$index]['icon'] = [
                        'type' => $this->resolveIconType($iconValue),
                        'value' => $iconValue,
                    ];
                }

                $nodes[$index]['order'] = $order;

                $nodes[$index]['__menu_key'] = $signature;
                $nodes[$index]['__path_id'] = $pathId !== '' ? $pathId : ($node['__path_id'] ?? null);
                $nodes[$index]['__group_signature'] = $groupSignature;

                return;
            }
        }

        $idSignature = $pathId !== '' ? $pathId : implode('/', $fullPath);

        $nodes[] = [
            'id' => 'navigation-static-group:'.sha1((string) $this->normalizeNavigationId($navigationId).'|'.$idSignature),
            'label' => $label,
            'icon' => $iconValue !== ''
                ? ['type' => $this->resolveIconType($iconValue), 'value' => $iconValue]
                : null,
            'url' => null,
            'children' => [],
            'order' => $order,
            '__menu_key' => $signature,
            '__path_id' => $pathId !== '' ? $pathId : null,
            '__group_signature' => $groupSignature,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @return array<int, array<string, mixed>>
     */
    protected function applyMenuOverridesToNodes(array $nodes, string|int|null $navigationId): array
    {
        $overrides = $this->menuOverridesForNavigation($navigationId);
        if ($overrides === []) {
            return $nodes;
        }

        $resolved = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $nodeKey = $this->resolveNodeOverrideKey($node);
            $override = $nodeKey !== null ? ($overrides[$nodeKey] ?? null) : null;
            if (! is_array($override) && is_string($nodeKey) && str_starts_with($nodeKey, 'menu:')) {
                $legacyKey = substr($nodeKey, 5);
                if ($legacyKey !== '') {
                    $legacyOverride = $overrides[$legacyKey] ?? null;
                    if (is_array($legacyOverride)) {
                        $override = $legacyOverride;
                    }
                }
            }

            if (is_array($override)) {
                if (array_key_exists('visible', $override) && ! $this->toBool($override['visible'], true)) {
                    continue;
                }

                if (array_key_exists('order', $override) && is_numeric($override['order'])) {
                    $node['order'] = (int) $override['order'];
                }

                $overrideLabel = trim((string) ($override['label'] ?? ''));
                if (
                    $overrideLabel !== ''
                    && (str_starts_with($overrideLabel, 'messages.') || str_contains($overrideLabel, '::'))
                ) {
                    $translatedOverrideLabel = $this->translateMenuLabel($overrideLabel);
                    if (
                        trim($translatedOverrideLabel) !== ''
                        && (! str_starts_with($overrideLabel, 'messages.') || $translatedOverrideLabel !== $overrideLabel)
                    ) {
                        $node['label'] = $translatedOverrideLabel;
                    }
                }
            }

            if (is_array($node['children'] ?? null) && $node['children'] !== []) {
                $node['children'] = $this->applyMenuOverridesToNodes($node['children'], $navigationId);
            }

            $resolved[] = $node;
        }

        return $this->sortNodesByOrder($resolved);
    }

    /**
     * @return array<string, mixed>
     */
    protected function menuOverridesForNavigation(string|int|null $navigationId): array
    {
        $stored = $this->loadMenuOverrides();
        if ($stored === []) {
            return [];
        }

        $normalized = $this->normalizeNavigationId($navigationId);

        $candidates = [];
        if ($normalized === null) {
            $candidates = ['main_menu', 'default', '__default', ''];
        } elseif (is_int($normalized)) {
            $candidates = ['id:'.$normalized, (string) $normalized];
        } else {
            $normalizedString = strtolower(trim((string) $normalized));
            $candidates = [$normalizedString, 'str:'.$normalizedString];
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }

            $bucket = $stored[$candidate] ?? null;
            if (is_array($bucket)) {
                return $bucket;
            }
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    protected function appendCustomNodes(array $nodes, string|int|null $navigationId): array
    {
        $customNodes = $this->menuCustomNodesForNavigation($navigationId);
        if ($customNodes === []) {
            return $nodes;
        }

        foreach ($customNodes as $customNode) {
            $normalized = $this->normalizeCustomNode($customNode);
            if ($normalized === null) {
                continue;
            }

            $nodes[] = $normalized;
        }

        return $this->sortNodesByOrder($nodes);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function menuCustomNodesForNavigation(string|int|null $navigationId): array
    {
        $stored = $this->loadMenuCustomNodes();
        if ($stored === []) {
            return [];
        }

        $normalized = $this->normalizeNavigationId($navigationId);
        $candidates = [];

        if ($normalized === null) {
            $candidates = ['main_menu', 'default', '__default', ''];
        } elseif (is_int($normalized)) {
            $candidates = ['id:'.$normalized, (string) $normalized];
        } else {
            $normalizedString = strtolower(trim((string) $normalized));
            $candidates = [$normalizedString, 'str:'.$normalizedString];
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }

            $bucket = $stored[$candidate] ?? null;
            if (is_array($bucket)) {
                return array_values(array_filter($bucket, static fn (mixed $item): bool => is_array($item)));
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadMenuCustomNodes(): array
    {
        if (is_array($this->menuCustomNodesCache)) {
            return $this->menuCustomNodesCache;
        }

        $loaded = setting('menu_manager.custom_nodes', []);
        if (is_string($loaded)) {
            $decoded = json_decode($loaded, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $loaded = $decoded;
            }
        }

        $this->menuCustomNodesCache = is_array($loaded) ? $loaded : [];

        return $this->menuCustomNodesCache;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    protected function normalizeCustomNode(array $node): ?array
    {
        $value = trim((string) ($node['value'] ?? $node['id'] ?? ''));
        if ($value === '') {
            return null;
        }

        $type = strtolower(trim((string) ($node['type'] ?? 'item')));
        if (! in_array($type, ['item', 'label', 'separator'], true)) {
            $type = 'item';
        }

        $childrenRaw = $node['children'] ?? [];
        $children = [];
        if (is_array($childrenRaw)) {
            foreach ($childrenRaw as $child) {
                if (! is_array($child)) {
                    continue;
                }

                $normalizedChild = $this->normalizeCustomNode($child);
                if ($normalizedChild !== null) {
                    $children[] = $normalizedChild;
                }
            }
        }

        if ($type === 'separator') {
            return [
                'type' => 'separator',
                '__menu_key' => $value,
                'children' => [],
            ];
        }

        $label = $this->translateMenuLabel((string) ($node['label'] ?? ''));
        $order = is_numeric($node['order'] ?? null) ? (int) $node['order'] : 0;
        $url = trim((string) ($node['url'] ?? ''));
        $icon = trim((string) ($node['icon'] ?? ''));

        if ($type === 'label') {
            return [
                'type' => 'label',
                'label' => $label,
                '__menu_key' => $value,
                'order' => $order,
                'children' => [],
            ];
        }

        return [
            'id' => 'navigation-custom-item:'.sha1($value),
            'label' => $label,
            'url' => $url !== '' ? $url : null,
            'icon' => $icon !== '' ? ['type' => $this->resolveIconType($icon), 'value' => $icon] : null,
            'children' => $children,
            'order' => $order,
            '__menu_key' => $value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadMenuOverrides(): array
    {
        if (is_array($this->menuOverridesCache)) {
            return $this->menuOverridesCache;
        }

        $loaded = setting('menu_manager.overrides', []);

        if (is_string($loaded)) {
            $decoded = json_decode($loaded, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $loaded = $decoded;
            }
        }

        $this->menuOverridesCache = is_array($loaded) ? $loaded : [];

        return $this->menuOverridesCache;
    }

    protected function resolveNodeOverrideKey(array $node): ?string
    {
        $menuKey = trim((string) ($node['__menu_key'] ?? ''));
        if ($menuKey !== '') {
            return 'menu:'.$menuKey;
        }

        $id = trim((string) ($node['id'] ?? ''));
        if ($id !== '') {
            return 'id:'.$id;
        }

        return null;
    }

    protected function toBool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return $default;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $default;
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
            $leftIsDashboard = (bool) ($left['__is_dashboard'] ?? false);
            $rightIsDashboard = (bool) ($right['__is_dashboard'] ?? false);

            if ($leftIsDashboard !== $rightIsDashboard) {
                return $leftIsDashboard ? -1 : 1;
            }

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
            unset($node['__is_dashboard']);
        }

        return $indexed;
    }

    /**
     * @param array<string, mixed> $item
     */
    protected function isDashboardItem(array $item, ?string $resolvedUrl): bool
    {
        $routeName = strtolower(trim((string) ($item['route_name'] ?? '')));
        if ($routeName !== '' && preg_match('/(^|\\.)dashboard$/', $routeName) === 1) {
            return true;
        }

        $source = trim((string) ($item['source'] ?? ''));
        if ($source !== '' && str_ends_with($source, '\\DashboardOperation')) {
            return true;
        }

        $url = trim((string) ($resolvedUrl ?? ''));
        if ($url === '' || $url === '#') {
            return false;
        }

        $label = strtolower(trim((string) ($item['label'] ?? '')));
        if ($label === '') {
            $label = strtolower(trim($this->translateMenuLabel((string) ($item['label'] ?? ''), (string) ($item['source'] ?? ''))));
        }
        if ($label === '') {
            return false;
        }

        $dashboardLabels = array_filter(array_unique([
            strtolower(trim((string) __('Dashboard'))),
            'dashboard',
            'pulpit',
            'kokpit',
        ]));

        if (! in_array($label, $dashboardLabels, true)) {
            return false;
        }

        return in_array($url, ['/', 'admin', '/admin', 'panel', '/panel'], true);
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

    protected function translateMenuLabel(string $label, string $source = ''): string
    {
        $normalized = trim($label);
        if ($normalized === '') {
            return $label;
        }

        $direct = __($normalized);
        if (is_string($direct) && trim($direct) !== '' && $direct !== $normalized) {
            return $direct;
        }

        $moduleNamespace = $this->resolveModuleNamespaceFromSource($source);
        if ($moduleNamespace !== null) {
            $moduleKey = $moduleNamespace.'::module.'.$normalized;
            $moduleTranslated = __($moduleKey);

            if (is_string($moduleTranslated) && trim($moduleTranslated) !== '' && $moduleTranslated !== $moduleKey) {
                return $moduleTranslated;
            }
        }

        if (str_starts_with($normalized, 'messages.')) {
            $resolved = $this->resolveGlobalMessagesTranslation($normalized);
            if (is_string($resolved) && trim($resolved) !== '' && $resolved !== $normalized) {
                return $resolved;
            }
        }

        return $label;
    }

    protected function resolveGlobalMessagesTranslation(string $key): ?string
    {
        $normalized = trim($key);
        if (! str_starts_with($normalized, 'messages.')) {
            return null;
        }

        $path = trim(substr($normalized, strlen('messages.')));
        if ($path === '') {
            return null;
        }

        $candidateLocales = array_values(array_filter(array_unique([
            strtolower(trim((string) app()->getLocale())),
            strtolower(trim((string) config('app.locale', ''))),
            strtolower(trim((string) config('app.fallback_locale', ''))),
            'en',
            'pl',
        ])));

        foreach ($candidateLocales as $locale) {
            $messagesFile = base_path("app/Svarium/Lang/{$locale}/messages.php");
            if (! is_file($messagesFile)) {
                continue;
            }

            $loaded = include $messagesFile;
            if (! is_array($loaded)) {
                continue;
            }

            $value = Arr::get($loaded, $path);
            if (! is_scalar($value)) {
                continue;
            }

            $resolved = trim((string) $value);
            if ($resolved !== '') {
                return $resolved;
            }
        }

        return null;
    }

    protected function resolveModuleNamespaceFromSource(string $source): ?string
    {
        $trimmed = trim($source);
        if ($trimmed === '') {
            return null;
        }

        if (class_exists($trimmed)) {
            /** @var SvariumModuleRegistry $moduleRegistry */
            $moduleRegistry = app(SvariumModuleRegistry::class);
            $module = $moduleRegistry->getByClass($trimmed);

            if ($module !== null) {
                $namespace = trim((string) $module->translationNamespace());
                if ($namespace !== '') {
                    return $namespace;
                }
            }
        }

        if (! str_ends_with($trimmed, 'Module')) {
            return null;
        }

        $classBase = class_basename($trimmed);
        $moduleBase = preg_replace('/Module$/', '', $classBase) ?? $classBase;
        $moduleBase = trim($moduleBase);

        if ($moduleBase === '') {
            return null;
        }

        return Str::snake($moduleBase);
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

    protected function canViewMenuTarget(string $routeName = '', string $url = '', string $permission = ''): bool
    {
        $user = $this->resolveUser();
        if (! is_object($user)) {
            return true;
        }

        if ($this->activeRoleHasSuperAdminAccess($user)) {
            return true;
        }

        $permission = trim($permission);
        if ($permission !== '') {
            return $this->userHasPermission($user, $permission);
        }

        $routeName = trim($routeName);
        if ($routeName !== '') {
            return $this->canViewRouteName($user, $routeName);
        }

        $url = trim($url);
        if ($url !== '') {
            return $this->canViewPath($user, $url, null);
        }

        return true;
    }

    protected function activeRoleHasSuperAdminAccess(object $user): bool
    {
        if (function_exists('get_role')) {
            try {
                $activeRole = get_role($user);
                if (is_array($activeRole)) {
                    $tokens = array_filter([
                        strtolower(trim((string) ($activeRole['role_key'] ?? ''))),
                        strtolower(trim((string) ($activeRole['name'] ?? ''))),
                        strtolower(trim((string) ($activeRole['name_locale'] ?? ''))),
                    ], static fn (string $value): bool => $value !== '');

                    if (in_array('superadmin', $tokens, true) || in_array('superadministrator', $tokens, true)) {
                        return true;
                    }

                    // Active role exists and is not superadmin: block broad fallback checks by all assigned roles.
                    if ($tokens !== []) {
                        return false;
                    }
                }
            } catch (\Throwable) {
                // fallback below
            }
        }

        return $this->userHasRole($user, 'superadmin');
    }

    protected function resolveUser(): ?object
    {
        if ($this->resolvedUserLoaded) {
            return $this->resolvedUser;
        }

        $this->resolvedUserLoaded = true;
        $this->resolvedUser = Auth::user();

        return $this->resolvedUser;
    }

    protected function canViewRouteName(object $user, string $routeName): bool
    {
        $cacheKey = 'route_name:'.$routeName;
        if (array_key_exists($cacheKey, $this->routeAccessCache)) {
            return (bool) $this->routeAccessCache[$cacheKey];
        }

        $route = Route::getRoutes()->getByName($routeName);
        if ($route === null) {
            return $this->routeAccessCache[$cacheKey] = false;
        }

        $action = trim((string) $route->getActionName());
        if (! str_contains($action, SvariumHttpKernel::class)) {
            return $this->routeAccessCache[$cacheKey] = true;
        }

        $method = $this->resolveHttpMethodForRoute($route->methods());
        $uri = trim((string) $route->uri(), '/');
        $panelHint = $this->resolvePanelHintFromRouteName($routeName);

        return $this->routeAccessCache[$cacheKey] = $this->canViewPath($user, $uri, $panelHint, $method);
    }

    protected function canViewPath(object $user, string $url, ?string $panelHint = null, string $method = 'GET'): bool
    {
        $path = $this->normalizePathFromUrl($url);
        if ($path === null) {
            return true;
        }

        $cacheKey = 'path:'.$method.':'.$path.':'.($panelHint ?? '-');
        if (array_key_exists($cacheKey, $this->routeAccessCache)) {
            return (bool) $this->routeAccessCache[$cacheKey];
        }

        [$panelName, $operationPath] = $this->resolvePanelAndOperationPath($path, $panelHint);
        if ($panelName === null) {
            return $this->routeAccessCache[$cacheKey] = true;
        }

        $panel = app(PanelRegistry::class)->get($panelName);
        if (! $panel instanceof Panel) {
            return $this->routeAccessCache[$cacheKey] = true;
        }

        /** @var OperationRegistry $operationRegistry */
        $operationRegistry = app(OperationRegistry::class);
        $normalizedMethod = strtoupper(trim($method));
        if ($normalizedMethod === '') {
            $normalizedMethod = 'GET';
        }

        $resolved = $operationRegistry->resolve($panelName, $normalizedMethod, $operationPath);
        if ($resolved === null && $normalizedMethod !== 'GET') {
            $resolved = $operationRegistry->resolve($panelName, 'GET', $operationPath);
        }

        if ($resolved === null) {
            return $this->routeAccessCache[$cacheKey] = true;
        }

        $operationClass = (string) ($resolved['operation'] ?? '');
        if ($operationClass === '' || ! class_exists($operationClass)) {
            return $this->routeAccessCache[$cacheKey] = true;
        }

        $requestPath = '/'.trim(implode('/', array_filter([
            trim((string) ($panel->prefix ?? ''), '/'),
            trim($operationPath, '/'),
        ])), '/');
        if ($requestPath === '//') {
            $requestPath = '/';
        }

        $request = Request::create($requestPath === '' ? '/' : $requestPath, $normalizedMethod);
        $request->setUserResolver(static fn () => $user);

        if (request() instanceof Request) {
            $request->attributes->add(request()->attributes->all());
        }

        $context = new PanelContext($panel, $request, (array) ($resolved['params'] ?? []));

        try {
            $operation = app($operationClass);

            if (! empty($resolved['meta']['resource']) && method_exists($operation, 'setResource')) {
                $operation->setResource((string) $resolved['meta']['resource']);
            }

            return $this->routeAccessCache[$cacheKey] = (bool) $operation->authorize($context);
        } catch (\Throwable) {
            return $this->routeAccessCache[$cacheKey] = false;
        }
    }

    protected function normalizePathFromUrl(string $url): ?string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $trimmed) === 1) {
            return null;
        }

        $path = parse_url($trimmed, PHP_URL_PATH);
        if (! is_string($path)) {
            $path = $trimmed;
        }

        return trim($path, '/');
    }

    protected function resolvePanelAndOperationPath(string $path, ?string $panelHint = null): array
    {
        /** @var PanelRegistry $registry */
        $registry = app(PanelRegistry::class);
        $panels = $registry->all();

        $normalizedPath = trim($path, '/');

        if (is_string($panelHint) && trim($panelHint) !== '') {
            $hintPanel = $registry->get(trim($panelHint));
            if ($hintPanel instanceof Panel) {
                $prefix = trim((string) ($hintPanel->prefix ?? ''), '/');
                if ($prefix === '') {
                    return [trim($panelHint), $normalizedPath];
                }

                if ($normalizedPath === $prefix) {
                    return [trim($panelHint), ''];
                }

                if (str_starts_with($normalizedPath, $prefix.'/')) {
                    return [trim($panelHint), substr($normalizedPath, strlen($prefix) + 1)];
                }
            }
        }

        $prefixedPanels = [];
        $noPrefixPanels = [];

        foreach ($panels as $name => $panel) {
            if (! $panel instanceof Panel) {
                continue;
            }

            $prefix = trim((string) ($panel->prefix ?? ''), '/');
            if ($prefix === '') {
                $noPrefixPanels[(string) $name] = $panel;
            } else {
                $prefixedPanels[(string) $name] = $panel;
            }
        }

        uasort($prefixedPanels, static function (Panel $left, Panel $right): int {
            return strlen((string) ($right->prefix ?? '')) <=> strlen((string) ($left->prefix ?? ''));
        });

        foreach ($prefixedPanels as $name => $panel) {
            $prefix = trim((string) ($panel->prefix ?? ''), '/');
            if ($prefix === '') {
                continue;
            }

            if ($normalizedPath === $prefix) {
                return [$name, ''];
            }

            if (str_starts_with($normalizedPath, $prefix.'/')) {
                return [$name, substr($normalizedPath, strlen($prefix) + 1)];
            }
        }

        $currentPanel = $this->resolveCurrentPanel();
        if ($currentPanel instanceof Panel) {
            foreach ($panels as $name => $panel) {
                if ($panel === $currentPanel) {
                    $prefix = trim((string) ($currentPanel->prefix ?? ''), '/');
                    if ($prefix === '') {
                        return [(string) $name, $normalizedPath];
                    }
                }
            }
        }

        if ($noPrefixPanels !== []) {
            $name = (string) array_key_first($noPrefixPanels);

            return [$name, $normalizedPath];
        }

        if ($panels !== []) {
            $name = (string) array_key_first($panels);
            $panel = $panels[$name];
            if ($panel instanceof Panel) {
                $prefix = trim((string) ($panel->prefix ?? ''), '/');
                if ($prefix !== '') {
                    if ($normalizedPath === $prefix) {
                        return [$name, ''];
                    }

                    if (str_starts_with($normalizedPath, $prefix.'/')) {
                        return [$name, substr($normalizedPath, strlen($prefix) + 1)];
                    }
                }
            }

            return [$name, $normalizedPath];
        }

        return [null, $normalizedPath];
    }

    protected function resolvePanelHintFromRouteName(string $routeName): ?string
    {
        $name = trim($routeName);
        if ($name === '') {
            return null;
        }

        /** @var PanelRegistry $registry */
        $registry = app(PanelRegistry::class);

        if (preg_match('/^module:([^.]+)\.[^.]+(?:\..+)?$/', $name, $matches) === 1) {
            $candidate = trim((string) ($matches[1] ?? ''));
            if ($candidate !== '' && $registry->get($candidate) instanceof Panel) {
                return $candidate;
            }
        }

        if (preg_match('/^panel\.([^.]+)\./', $name, $matches) === 1) {
            $candidate = trim((string) ($matches[1] ?? ''));
            if ($candidate !== '' && $registry->get($candidate) instanceof Panel) {
                return $candidate;
            }
        }

        return null;
    }

    protected function resolveHttpMethodForRoute(array $methods): string
    {
        foreach ($methods as $method) {
            $normalized = strtoupper(trim((string) $method));
            if ($normalized === '' || $normalized === 'HEAD') {
                continue;
            }

            return $normalized;
        }

        return 'GET';
    }

    protected function userHasPermission(object $user, string $permission): bool
    {
        $activeRoleId = 0;
        if (function_exists('get_role_id')) {
            $activeRoleId = (int) get_role_id();
        } else {
            $activeRoleId = (int) session('svarium.active_role_id', session('role_id', 0));
        }

        if ($activeRoleId > 0) {
            $activeRole = $this->resolveActiveRoleForUser($user, $activeRoleId);
            if ($activeRole !== null) {
                return PermissionMatcher::hasPermission($activeRole, $permission);
            }
        }

        return PermissionMatcher::hasPermission($user, $permission);
    }

    protected function resolveActiveRoleForUser(object $user, int $activeRoleId): ?object
    {
        try {
            if (method_exists($user, 'roles')) {
                $roles = $user->roles;
                if ($roles instanceof Collection) {
                    $role = $roles->firstWhere('id', $activeRoleId);
                    if (is_object($role)) {
                        return $role;
                    }
                }
            }
        } catch (\Throwable) {
            // fallback to querying role model
        }

        $roleModelClass = trim((string) config('upsoftware.models.role', config('permission.models.role', '')));
        if ($roleModelClass === '' || ! class_exists($roleModelClass)) {
            return null;
        }

        try {
            if (! is_subclass_of($roleModelClass, \Illuminate\Database\Eloquent\Model::class)) {
                return null;
            }

            /** @var class-string<\Illuminate\Database\Eloquent\Model> $roleModelClass */
            return $roleModelClass::query()->find($activeRoleId);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function userHasRole(object $user, string $role): bool
    {
        $normalizedRole = trim($role);
        if ($normalizedRole === '') {
            return false;
        }

        if (method_exists($user, 'hasRole')) {
            try {
                if ($user->hasRole($normalizedRole)) {
                    return true;
                }
            } catch (\Throwable) {
                // continue to fallback checks
            }
        }

        if (! method_exists($user, 'roles')) {
            return false;
        }

        try {
            $roles = $user->roles;
            if (! is_object($roles) || ! method_exists($roles, 'contains')) {
                return false;
            }

            return $roles->contains('role_key', $normalizedRole)
                || $roles->contains('role_key', strtolower($normalizedRole));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @return array<int, array<string, mixed>>
     */
    protected function pruneNavigationNodes(array $nodes): array
    {
        $pruned = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (is_array($node['children'] ?? null)) {
                $node['children'] = $this->pruneNavigationNodes($node['children']);
            }

            $type = (string) ($node['type'] ?? 'item');
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $url = trim((string) ($node['url'] ?? ''));

            if ($type === 'item' && $url === '' && $children === []) {
                continue;
            }

            $pruned[] = $node;
        }

        $hasActionableAhead = [];
        $seenActionable = false;

        for ($index = count($pruned) - 1; $index >= 0; $index--) {
            $hasActionableAhead[$index] = $seenActionable;
            if ($this->isActionableNode($pruned[$index])) {
                $seenActionable = true;
            }
        }

        $normalized = [];
        $hasActionableBefore = false;

        foreach ($pruned as $index => $node) {
            $type = (string) ($node['type'] ?? 'item');

            if ($type === 'separator') {
                if (! $hasActionableBefore || ! ($hasActionableAhead[$index] ?? false)) {
                    continue;
                }

                $previous = $normalized[array_key_last($normalized)] ?? null;
                if (is_array($previous) && (($previous['type'] ?? 'item') === 'separator')) {
                    continue;
                }
            }

            if ($type === 'label') {
                if (! ($hasActionableAhead[$index] ?? false)) {
                    continue;
                }
            }

            $normalized[] = $node;

            if ($this->isActionableNode($node)) {
                $hasActionableBefore = true;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $node
     */
    protected function isActionableNode(array $node): bool
    {
        $type = (string) ($node['type'] ?? 'item');
        if ($type === 'separator' || $type === 'label') {
            return false;
        }

        $url = trim((string) ($node['url'] ?? ''));
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];

        return $url !== '' || $children !== [];
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
