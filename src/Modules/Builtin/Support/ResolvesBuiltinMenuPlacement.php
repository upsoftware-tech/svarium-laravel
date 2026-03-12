<?php

namespace Upsoftware\Svarium\Modules\Builtin\Support;

use Upsoftware\Svarium\Menu\MenuItem;

trait ResolvesBuiltinMenuPlacement
{
    /**
     * @param array{
     *   target?: string,
     *   order?: int,
     *   icon?: string|null,
     *   group_icon?: string|null,
     *   path?: array<int, string>|string|null,
     *   path_ids?: array<int, string>|string|null,
     *   navigation_id?: string|int|null
     * } $defaults
     * @return array<int, MenuItem>
     */
    protected function buildBuiltinMenuItems(
        string $moduleKey,
        string $label,
        string $url,
        array $defaults = []
    ): array {
        $placement = $this->resolveBuiltinMenuPlacement($moduleKey, $defaults);
        $target = $placement['target'] ?? 'main_menu';

        if ($target === 'none') {
            return [];
        }

        $item = MenuItem::make($label)
            ->url($url)
            ->order((int) ($placement['order'] ?? 0));

        $icon = trim((string) ($placement['icon'] ?? ''));
        if ($icon !== '') {
            $item->icon($icon);
        }

        $path = $placement['path'] ?? [];
        if (is_string($path)) {
            $path = explode('/', $path);
        }
        if (is_array($path)) {
            $normalizedPath = array_values(array_filter(array_map(
                fn (mixed $segment): string => $this->translateBuiltinPathSegment((string) $segment),
                $path
            ), static fn (string $segment): bool => $segment !== ''));

            if ($normalizedPath !== []) {
                $item->path($normalizedPath);
            }
        }

        $pathIds = $placement['path_ids'] ?? null;
        if (is_string($pathIds)) {
            $pathIds = explode('/', $pathIds);
        }
        if (is_array($pathIds)) {
            $normalizedPathIds = array_values(array_filter(array_map(
                static fn (mixed $segment): string => trim((string) $segment),
                $pathIds
            ), static fn (string $segment): bool => $segment !== ''));

            if ($normalizedPathIds !== []) {
                $item->pathIds($normalizedPathIds);
            }
        }

        $navigationId = $placement['navigation_id'] ?? null;
        if ((is_string($navigationId) && trim($navigationId) !== '') || is_int($navigationId)) {
            $item->navigation($navigationId);
        }

        $items = [];
        $groupIcon = trim((string) ($placement['group_icon'] ?? ''));

        if ($groupIcon !== '' && ($normalizedPath ?? []) !== []) {
            $groupLabel = (string) ($normalizedPath[array_key_last($normalizedPath)] ?? '');
            $groupPath = array_slice($normalizedPath, 0, -1);
            $groupPathIds = is_array($normalizedPathIds ?? null)
                ? $normalizedPathIds
                : [];
            $groupPathId = trim((string) ($groupPathIds[array_key_last($groupPathIds)] ?? ''));
            $groupPathIdPrefix = array_slice($groupPathIds, 0, -1);

            if ($groupLabel !== '') {
                $groupItem = MenuItem::make($groupLabel)
                    ->icon($groupIcon)
                    ->order((int) ($placement['order'] ?? 0));

                if ($groupPath !== []) {
                    $groupItem->path($groupPath);
                }

                if ($groupPathIdPrefix !== []) {
                    $groupItem->pathIds($groupPathIdPrefix);
                }

                if ($groupPathId !== '') {
                    $groupItem->pathId($groupPathId);
                }

                if ((is_string($navigationId) && trim($navigationId) !== '') || is_int($navigationId)) {
                    $groupItem->navigation($navigationId);
                }

                $items[] = $groupItem;
            }
        }

        $items[] = $item;

        return $items;
    }

    /**
     * @param array{
     *   target?: string,
     *   order?: int,
     *   icon?: string|null,
     *   group_icon?: string|null,
     *   path?: array<int, string>|string|null,
     *   path_ids?: array<int, string>|string|null,
     *   navigation_id?: string|int|null
     * } $defaults
     * @return array{
     *   target: string,
     *   order: int,
     *   icon: string|null,
     *   group_icon: string|null,
     *   path: array<int, string>|string|null,
     *   path_ids: array<int, string>|string|null,
     *   navigation_id: string|int|null
     * }
     */
    protected function resolveBuiltinMenuPlacement(string $moduleKey, array $defaults = []): array
    {
        $configured = config("upsoftware.modules.placements.{$moduleKey}", []);
        if (! is_array($configured)) {
            $configured = [];
        }

        $target = $this->normalizeBuiltinPlacementTarget(
            (string) ($configured['target'] ?? $defaults['target'] ?? 'main_menu')
        );

        $order = (int) ($configured['order'] ?? $defaults['order'] ?? 0);
        $icon = array_key_exists('icon', $configured)
            ? $configured['icon']
            : ($defaults['icon'] ?? null);
        $groupIcon = array_key_exists('group_icon', $configured)
            ? $configured['group_icon']
            : ($defaults['group_icon'] ?? null);
        $path = $configured['path'] ?? $defaults['path'] ?? [];
        $pathIds = $configured['path_ids'] ?? $configured['path_keys'] ?? $defaults['path_ids'] ?? null;
        $navigationId = $configured['navigation_id'] ?? $defaults['navigation_id'] ?? null;

        if ($target === 'sidebar_user' && ($navigationId === null || (is_string($navigationId) && trim($navigationId) === ''))) {
            $navigationId = 'sidebar_user';
        }

        return [
            'target' => $target,
            'order' => $order,
            'icon' => is_string($icon) || $icon === null ? $icon : null,
            'group_icon' => is_string($groupIcon) || $groupIcon === null ? $groupIcon : null,
            'path' => $path,
            'path_ids' => $pathIds,
            'navigation_id' => is_string($navigationId) || is_int($navigationId) ? $navigationId : null,
        ];
    }

    protected function translateBuiltinPathSegment(string $segment): string
    {
        $segment = trim($segment);
        if ($segment === '') {
            return '';
        }

        $translated = __($segment);
        if (is_string($translated) && trim($translated) !== '' && $translated !== $segment) {
            return trim($translated);
        }

        $messagesTranslated = __('svarium::messages.'.$segment);
        if (
            is_string($messagesTranslated)
            && trim($messagesTranslated) !== ''
            && $messagesTranslated !== 'svarium::messages.'.$segment
        ) {
            return trim($messagesTranslated);
        }

        return $segment;
    }

    protected function normalizeBuiltinPlacementTarget(string $target): string
    {
        $value = strtolower(trim($target));

        if (in_array($value, ['none', 'off', 'disabled'], true)) {
            return 'none';
        }

        if (in_array($value, ['sidebar_user', 'sidebar', 'user_menu', 'account_menu'], true)) {
            return 'sidebar_user';
        }

        return 'main_menu';
    }
}
