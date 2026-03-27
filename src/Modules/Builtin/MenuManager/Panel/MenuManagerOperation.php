<?php

namespace Upsoftware\Svarium\Modules\Builtin\MenuManager\Panel;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Menu\MenuRegistry;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\UI\Components\DropdownAction;
use Upsoftware\Svarium\UI\Components\Flex;
use Upsoftware\Svarium\UI\Components\Form\Select;
use Upsoftware\Svarium\UI\Components\TreeSortable;

class MenuManagerOperation extends Operation
{
    public static string|array $panels = '*';

    public static function uri(): string
    {
        return 'system/menu-manager';
    }

    public static function methods(): array
    {
        return ['GET', 'POST'];
    }

    public function execution(): ExecutionMode
    {
        return ExecutionMode::TREE;
    }

    protected function submitLabel(): string
    {
        return __('svarium::messages.Save menu settings');
    }

    protected function hasSubmit(): bool
    {
        return false;
    }

    public function title(): string
    {
        return (string) svarium_label('modules.menu_manager.plural', __('Menu manager'));
    }

    public function authorize(PanelContext $context): bool
    {
        return $this->isSuperadmin($context->request()->user() ?? auth()->user());
    }

    public function schema(PanelContext $context): array
    {
        $selectedNavigation = $this->resolveSelectedNavigationToken($context);
        $navigationOptions = $this->resolveNavigationOptions();

        $treeItems = collect(menu_children($selectedNavigation))
            ->filter(fn (mixed $node) => is_array($node))
            ->map(fn (array $node) => $this->mapMenuNodeForTreeSortable($node, 0, null, $selectedNavigation))
            ->filter(fn (array $node) => ($node['value'] ?? '') !== '')
            ->values()
            ->all();

        $treeItems = $this->removeDuplicatedDescendantsFromRoot($treeItems);

        $schema = [];

        $headerChildren = [];

        if (count($navigationOptions) > 1) {
            $headerChildren[] = Select::make('_menu_manager_navigation')
                ->width(240)
                ->label(false)
                ->options($navigationOptions)
                ->value($selectedNavigation)
                ->clear(false)
                ->class('w-[240px]')
                ->prop('navigateOnChange', true)
                ->prop('navigateQueryParam', 'menu')
                ->prop('navigateUrl', panel_href(static::uri()))
                ->prop('navigatePreserveQuery', false);
        }

        $headerChildren[] = DropdownAction::make()
            ->name('_menu_action')
            ->label(__('Add'))
            ->variant('outline')
            ->icon('lucide:plus')
            ->options([
                ['value' => 'label', 'label' => __('Dodaj etykietę')],
                ['value' => 'separator', 'label' => __('Dodaj separator')],
            ])
            ->prop('submitOnChange', true)
            ->prop('submitUrl', $this->menuManagerUrl($selectedNavigation))
            ->default(null);

        $schema[] = Flex::make()
            ->justify('end')
            ->gap(2)
            ->children($headerChildren);

        $schema[] = TreeSortable::make('menu')
            ->columns([
                ['key' => 'label', 'label' => __('Label')],
                ['key' => 'value', 'label' => __('Key')],
            ])
            ->autosave(true, 400)
            ->items($treeItems);

        return $schema;
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    protected function resolveNavigationOptions(): array
    {
        /** @var MenuRegistry $registry */
        $registry = app(MenuRegistry::class);
        $ids = $registry->navigationIds();

        $items = [];
        $seen = [];

        foreach ($ids as $id) {
            $token = $this->navigationToken($id);
            if ($token === '' || isset($seen[$token])) {
                continue;
            }

            $seen[$token] = true;
            $items[] = [
                'value' => $token,
                'label' => $this->resolveNavigationLabel($registry, $id),
            ];
        }

        if ($items === []) {
            return [
                [
                    'value' => 'main_menu',
                    'label' => __('Main menu'),
                ],
            ];
        }

        usort($items, static function (array $left, array $right): int {
            $leftValue = (string) ($left['value'] ?? '');
            $rightValue = (string) ($right['value'] ?? '');

            if ($leftValue === 'main_menu' && $rightValue !== 'main_menu') {
                return -1;
            }

            if ($rightValue === 'main_menu' && $leftValue !== 'main_menu') {
                return 1;
            }

            return strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return array_values($items);
    }

    protected function resolveSelectedNavigationToken(PanelContext $context): string
    {
        $request = $context->request();
        $requested = trim((string) $request->input(
            '_menu_manager_navigation',
            $request->query('menu', $request->query('navigation', ''))
        ));

        if ($requested === '') {
            $requested = $this->extractNavigationFromReferer($request->headers->get('referer'));
        }

        if ($requested === '') {
            $requested = 'main_menu';
        }

        $available = collect($this->resolveNavigationOptions())
            ->map(static fn (array $item): string => (string) ($item['value'] ?? ''))
            ->filter(static fn (string $value): bool => $value !== '')
            ->values()
            ->all();

        if ($available === []) {
            return 'main_menu';
        }

        if (! in_array($requested, $available, true)) {
            return (string) ($available[0] ?? 'main_menu');
        }

        return $requested;
    }

    protected function extractNavigationFromReferer(?string $referer): string
    {
        if (! is_string($referer) || trim($referer) === '') {
            return '';
        }

        $query = parse_url($referer, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return '';
        }

        parse_str($query, $params);
        if (! is_array($params)) {
            return '';
        }

        return trim((string) ($params['menu'] ?? $params['navigation'] ?? ''));
    }

    protected function resolveNavigationLabel(MenuRegistry $registry, mixed $navigationId): string
    {
        if ($navigationId === null) {
            return __('Main menu');
        }

        if (is_string($navigationId) || is_int($navigationId)) {
            $label = $registry->navigationLabel($navigationId);
            if (is_string($label) && trim($label) !== '') {
                return trim($label);
            }

            $raw = trim((string) $navigationId);
            if ($raw !== '') {
                return ucfirst(str_replace(['_', '-'], ' ', $raw));
            }
        }

        return __('Menu');
    }

    protected function navigationToken(mixed $navigationId): string
    {
        if ($navigationId === null) {
            return 'main_menu';
        }

        if (is_int($navigationId)) {
            return (string) $navigationId;
        }

        $token = trim((string) $navigationId);
        if ($token === '') {
            return 'main_menu';
        }

        return $token;
    }

    protected function menuManagerUrl(string $navigationToken = 'main_menu'): string
    {
        $base = panel_href(static::uri());

        return $base.'?menu='.rawurlencode($navigationToken);
    }

    protected function mapMenuNodeForTreeSortable(
        array $node,
        int $depth = 0,
        ?string $parent = null,
        string $selectedNavigation = 'main_menu'
    ): array {
        $value = (string) ($node['__menu_key'] ?? $node['id'] ?? '');
        $isDashboard = str_starts_with($value, 'navigation-static-dashboard:');

        $children = $node['children'] ?? null;
        $hasChildren = is_array($children) && $children !== [];

        $mapped = [
            'value' => $value,
            'label' => (string) ($node['label'] ?? ''),
            'url' => trim((string) ($node['url'] ?? '')),
            'edit_url' => $value !== '' && (string) ($node['type'] ?? 'item') !== 'separator'
                ? panel_href('system/menu-manager/edit/'.rawurlencode($value)).'?menu='.rawurlencode($selectedNavigation)
                : '',
            'type' => (string) ($node['type'] ?? 'item'),
            'parent' => $parent,
            'lock' => $depth === 0 && ($hasChildren || $isDashboard),
            'fixed' => $isDashboard,
            'children' => [],
        ];

        if (is_array($children) && $children !== []) {
            $mappedChildren = collect($children)
                ->filter(fn (mixed $child) => is_array($child))
                ->map(fn (array $child) => $this->mapMenuNodeForTreeSortable(
                    $child,
                    $depth + 1,
                    $value !== '' ? $value : null,
                    $selectedNavigation
                ))
                ->filter(fn (array $child) => ($child['value'] ?? '') !== '')
                ->values()
                ->all();

            $mapped['children'] = $mappedChildren;
        }

        return $mapped;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    protected function removeDuplicatedDescendantsFromRoot(array $nodes): array
    {
        $descendantValues = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $this->collectDescendantValues($children, $descendantValues);
        }

        $descendantLookup = array_fill_keys(array_values(array_unique($descendantValues)), true);

        $filtered = array_values(array_filter($nodes, static function (mixed $node) use ($descendantLookup): bool {
            if (! is_array($node)) {
                return false;
            }

            $value = trim((string) ($node['value'] ?? ''));
            if ($value === '') {
                return false;
            }

            return ! isset($descendantLookup[$value]);
        }));

        return array_map(function (array $node): array {
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $node['children'] = $this->normalizeUniqueChildren($children);

            return $node;
        }, $filtered);
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @param  array<int, string>  $values
     */
    protected function collectDescendantValues(array $children, array &$values): void
    {
        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }

            $value = trim((string) ($child['value'] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }

            $nested = is_array($child['children'] ?? null) ? $child['children'] : [];
            if ($nested !== []) {
                $this->collectDescendantValues($nested, $values);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeUniqueChildren(array $children): array
    {
        $seen = [];
        $normalized = [];

        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }

            $value = trim((string) ($child['value'] ?? ''));
            if ($value === '' || isset($seen[$value])) {
                continue;
            }

            $seen[$value] = true;
            $nested = is_array($child['children'] ?? null) ? $child['children'] : [];
            $child['children'] = $this->normalizeUniqueChildren($nested);
            $normalized[] = $child;
        }

        return $normalized;
    }

    protected function save(PanelContext $context): RedirectResult
    {
        $selectedNavigation = $this->resolveSelectedNavigationToken($context);
        $menuAction = strtolower(trim((string) $context->input('_menu_action', '')));
        if (in_array($menuAction, ['separator', 'label'], true)) {
            $this->appendCustomNodeFromAction($menuAction, $selectedNavigation);

            return RedirectResult::to($this->menuManagerUrl($selectedNavigation))
                ->success($menuAction === 'separator'
                    ? __('Separator has been added.')
                    : __('Label has been added.'));
        }

        $treePayload = $this->decodeTreeSortablePayload($context->input('menu'));
        if ($treePayload !== []) {
            $this->persistOrderOverridesFromTree($treePayload, $selectedNavigation);

            return RedirectResult::to($this->menuManagerUrl($selectedNavigation))
                ->success(__('svarium::messages.Menu settings have been saved.'));
        }

        $items = $context->input('menu_manager.items', []);
        if (! is_array($items)) {
            $items = [];
        }

        if ($items === []) {
            return RedirectResult::to($this->menuManagerUrl($selectedNavigation));
        }

        $stored = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $navigation = $this->normalizeNavigationBucket($item['navigation'] ?? 'main_menu');
            $nodeKey = trim((string) ($item['node_key'] ?? ''));

            if ($navigation === '' || $nodeKey === '') {
                continue;
            }

            $visible = $this->toBool($item['visible'] ?? 1, true);
            $defaultOrder = $this->toInt($item['default_order'] ?? 0);
            $resolvedOrder = $this->toInt($item['order'] ?? $defaultOrder);

            $entry = [];
            if (! $visible) {
                $entry['visible'] = false;
            }

            if ($resolvedOrder !== $defaultOrder) {
                $entry['order'] = $resolvedOrder;
            }

            if ($entry === []) {
                continue;
            }

            $stored[$navigation][$nodeKey] = $entry;
        }

        $settingModel = (string) config('upsoftware.models.setting', \Upsoftware\Svarium\Models\Setting::class);
        if (class_exists($settingModel) && method_exists($settingModel, 'setSettingGlobal')) {
            $settingModel::setSettingGlobal('menu_manager.overrides', $stored, true);
        }

        return RedirectResult::to($this->menuManagerUrl($selectedNavigation))
            ->success(__('svarium::messages.Menu settings have been saved.'));
    }

    protected function appendCustomNodeFromAction(string $action, string $navigationBucket = 'main_menu'): void
    {
        $normalizedBucket = $this->normalizeNavigationBucket($navigationBucket);
        $stored = setting('menu_manager.custom_nodes', []);
        if (! is_array($stored)) {
            $stored = [];
        }

        $bucketNodes = $stored[$normalizedBucket] ?? [];
        if (! is_array($bucketNodes)) {
            $bucketNodes = [];
        }

        $keyPrefix = $action === 'separator' ? 'separator' : 'label';
        $nodeKey = 'menu-manager-custom:'.$keyPrefix.':'.(string) Str::uuid();

        $bucketNodes[] = [
            'value' => $nodeKey,
            'type' => $action,
            'label' => $action === 'label' ? (string) __('New label') : '',
            'children' => [],
        ];

        $stored[$normalizedBucket] = array_values($bucketNodes);

        $settingModel = (string) config('upsoftware.models.setting', \Upsoftware\Svarium\Models\Setting::class);
        if (class_exists($settingModel) && method_exists($settingModel, 'setSettingGlobal')) {
            $settingModel::setSettingGlobal('menu_manager.custom_nodes', $stored, true);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function decodeTreeSortablePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (! is_string($payload)) {
            return [];
        }

        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tree
     */
    protected function persistOrderOverridesFromTree(array $tree, string $navigationBucket = 'main_menu'): void
    {
        $orderOverrides = [];
        $this->collectOrderOverridesFromTree($tree, $orderOverrides);

        if ($orderOverrides === []) {
            return;
        }

        $existing = setting('menu_manager.overrides', []);
        if (! is_array($existing)) {
            $existing = [];
        }

        $bucket = $this->normalizeNavigationBucket($navigationBucket);
        $currentBucket = $existing[$bucket] ?? [];
        if (! is_array($currentBucket)) {
            $currentBucket = [];
        }

        foreach ($orderOverrides as $nodeKey => $entry) {
            $normalizedKey = $this->normalizeOverrideNodeKey($nodeKey);
            if ($normalizedKey === '') {
                continue;
            }

            $currentEntry = $currentBucket[$normalizedKey] ?? [];
            if (! is_array($currentEntry)) {
                $currentEntry = [];
            }

            $currentEntry['order'] = (int) ($entry['order'] ?? 0);
            $currentBucket[$normalizedKey] = $currentEntry;
        }

        $existing[$bucket] = $currentBucket;

        $settingModel = (string) config('upsoftware.models.setting', \Upsoftware\Svarium\Models\Setting::class);
        if (class_exists($settingModel) && method_exists($settingModel, 'setSettingGlobal')) {
            $settingModel::setSettingGlobal('menu_manager.overrides', $existing, true);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, array{order: int}>  $overrides
     */
    protected function collectOrderOverridesFromTree(array $nodes, array &$overrides): void
    {
        $order = 1;

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $nodeKey = trim((string) ($node['value'] ?? $node['__menu_key'] ?? $node['id'] ?? ''));
            if ($nodeKey !== '') {
                $overrides[$nodeKey] = ['order' => $order];
                $order++;
            }

            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            if ($children !== []) {
                $this->collectOrderOverridesFromTree($children, $overrides);
            }
        }
    }

    protected function normalizeOverrideNodeKey(string $nodeKey): string
    {
        $nodeKey = trim($nodeKey);
        if ($nodeKey === '') {
            return '';
        }

        if (str_starts_with($nodeKey, 'menu:') || str_starts_with($nodeKey, 'id:')) {
            return $nodeKey;
        }

        // Runtime/static navigation nodes (groups/dashboard/static leaves) are
        // resolved in NavigationService by "id:*" keys, not "menu:*".
        if (str_starts_with($nodeKey, 'navigation-static-')) {
            return 'id:'.$nodeKey;
        }

        return 'menu:'.$nodeKey;
    }

    protected function normalizeNavigationBucket(mixed $bucket): string
    {
        $normalized = strtolower(trim((string) $bucket));

        if ($normalized === '' || in_array($normalized, ['main', 'main_menu', 'default', '__default'], true)) {
            return 'main_menu';
        }

        return $normalized;
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

    protected function toInt(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    protected function isSuperadmin(?object $user): bool
    {
        if (! is_object($user)) {
            return false;
        }

        if (function_exists('get_roles')) {
            try {
                foreach (get_roles($user) as $role) {
                    if (! is_array($role)) {
                        continue;
                    }

                    $roleKey = strtolower(trim((string) ($role['role_key'] ?? '')));
                    $roleName = strtolower(trim((string) ($role['name'] ?? '')));
                    $roleNameLocale = strtolower(trim((string) ($role['name_locale'] ?? '')));

                    if (
                        in_array($roleKey, ['superadmin', 'superadministrator'], true)
                        || in_array($roleName, ['superadmin', 'superadministrator'], true)
                        || in_array($roleNameLocale, ['superadmin', 'superadministrator'], true)
                    ) {
                        return true;
                    }
                }
            } catch (Throwable) {
                // fallback below
            }
        }

        if (method_exists($user, 'hasRole')) {
            try {
                return (bool) $user->hasRole('superadmin');
            } catch (Throwable) {
                return false;
            }
        }

        $roles = [];
        try {
            if (method_exists($user, 'roles')) {
                $roles = $user->roles;
            }
        } catch (Throwable) {
            return false;
        }

        if ($roles instanceof Collection) {
            return $roles->contains(static function (mixed $role): bool {
                if (! is_object($role)) {
                    return false;
                }

                $roleKey = strtolower(trim((string) ($role->role_key ?? '')));
                $roleName = strtolower(trim((string) ($role->name ?? '')));

                return in_array($roleKey, ['superadmin', 'superadministrator'], true)
                    || in_array($roleName, ['superadmin', 'superadministrator'], true);
            });
        }

        return false;
    }
}
