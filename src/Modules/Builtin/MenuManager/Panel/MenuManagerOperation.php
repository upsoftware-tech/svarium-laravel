<?php

namespace Upsoftware\Svarium\Modules\Builtin\MenuManager\Panel;

use Illuminate\Support\Collection;
use Throwable;
use Upsoftware\Svarium\Enums\ExecutionMode;
use Upsoftware\Svarium\Http\RedirectResult;
use Upsoftware\Svarium\Menu\MenuRegistry;
use Upsoftware\Svarium\Panel\Operation;
use Upsoftware\Svarium\Panel\PanelContext;
use Upsoftware\Svarium\Services\NavigationService;
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
        return ExecutionMode::FORM;
    }

    protected function submitLabel(): string
    {
        return __('svarium::messages.Save menu settings');
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
        $treeItems = collect(menu_children())
            ->filter(fn (mixed $node) => is_array($node))
            ->map(fn (array $node) => $this->mapMenuNodeForTreeSortable($node, 0, null))
            ->filter(fn (array $node) => ($node['value'] ?? '') !== '')
            ->values()
            ->all();

        $treeItems = $this->removeDuplicatedDescendantsFromRoot($treeItems);

        return [
            TreeSortable::make('menu')
                ->columns([
                    ['key' => 'label', 'label' => __('Label')],
                    ['key' => 'value', 'label' => __('Key')],
                ])
                ->autosave(true, 400)
                ->items($treeItems),
        ];
    }

    protected function mapMenuNodeForTreeSortable(array $node, int $depth = 0, ?string $parent = null): array
    {
        $value = (string) ($node['__menu_key'] ?? $node['id'] ?? '');

        $mapped = [
            'value' => $value,
            'label' => (string) ($node['label'] ?? ''),
            'parent' => $parent,
            'lock' => $parent !== null,
            'children' => [],
        ];

        $children = $node['children'] ?? null;
        if (is_array($children) && $children !== []) {
            $mappedChildren = collect($children)
                ->filter(fn (mixed $child) => is_array($child))
                ->map(fn (array $child) => $this->mapMenuNodeForTreeSortable($child, $depth + 1, $value !== '' ? $value : null))
                ->filter(fn (array $child) => ($child['value'] ?? '') !== '')
                ->values()
                ->all();

            $mapped['children'] = $mappedChildren;
        }

        return $mapped;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
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
     * @param array<int, array<string, mixed>> $children
     * @param array<int, string> $values
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
     * @param array<int, array<string, mixed>> $children
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
        $treePayload = $this->decodeTreeSortablePayload($context->input('menu'));
        if ($treePayload !== []) {
            $this->persistOrderOverridesFromTree($treePayload);

            return RedirectResult::to(panel_href(static::uri()))
                ->success(__('svarium::messages.Menu settings have been saved.'));
        }

        $items = $context->input('menu_manager.items', []);
        if (! is_array($items)) {
            $items = [];
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

        return RedirectResult::to(panel_href(static::uri()))
            ->success(__('svarium::messages.Menu settings have been saved.'));
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
     * @param array<int, array<string, mixed>> $tree
     */
    protected function persistOrderOverridesFromTree(array $tree): void
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

        $bucket = 'main_menu';
        $currentBucket = $existing[$bucket] ?? [];
        if (! is_array($currentBucket)) {
            $currentBucket = [];
        }

        foreach ($orderOverrides as $nodeKey => $entry) {
            $currentEntry = $currentBucket[$nodeKey] ?? [];
            if (! is_array($currentEntry)) {
                $currentEntry = [];
            }

            $currentEntry['order'] = (int) ($entry['order'] ?? 0);
            $currentBucket[$nodeKey] = $currentEntry;
        }

        $existing[$bucket] = $currentBucket;

        $settingModel = (string) config('upsoftware.models.setting', \Upsoftware\Svarium\Models\Setting::class);
        if (class_exists($settingModel) && method_exists($settingModel, 'setSettingGlobal')) {
            $settingModel::setSettingGlobal('menu_manager.overrides', $existing, true);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     * @param array<string, array{order: int}> $overrides
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
