<?php

namespace Upsoftware\Svarium\Menu;

use InvalidArgumentException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class MenuRegistry
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $items = [];
    /**
     * @var array<string, array<string, string>>
     */
    protected array $reverseTranslationCache = [];

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
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_values($this->items);
    }

    /**
     * @return array<int, string|int|null>
     */
    public function navigationIds(): array
    {
        $ids = [null];

        foreach ($this->items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $ids[] = $this->normalizeNavigationId($item['navigation_id'] ?? null);
        }

        $unique = [];
        foreach ($ids as $id) {
            if ($id === null) {
                $key = 'null';
            } elseif (is_int($id)) {
                $key = 'int:'.$id;
            } else {
                $key = 'str:'.$id;
            }

            if (array_key_exists($key, $unique)) {
                continue;
            }

            $unique[$key] = $id;
        }

        return array_values($unique);
    }

    /**
     * @param array<int, mixed> $items
     * @param array<string, mixed> $defaults
     * @param array<int, string> $pathPrefix
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeMany(
        array $items,
        array $defaults,
        array $pathPrefix = [],
        array $pathIdPrefix = []
    ): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $normalized = [
                ...$normalized,
                ...$this->normalizeItem($item, $defaults, $pathPrefix, $pathIdPrefix),
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
    protected function normalizeItem(
        mixed $rawItem,
        array $defaults,
        array $pathPrefix,
        array $pathIdPrefix
    ): array
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
        $label = $this->normalizeTranslatedLabel($label);
        $routeName = isset($rawItem['route_name']) ? trim((string) $rawItem['route_name']) : null;
        $url = isset($rawItem['url']) ? trim((string) $rawItem['url']) : null;
        $icon = isset($rawItem['icon']) ? trim((string) $rawItem['icon']) : null;
        $permission = isset($rawItem['permission']) ? trim((string) $rawItem['permission']) : null;
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
        $pathIds = [
            ...$pathIdPrefix,
            ...$this->normalizePathIds($rawItem['path_ids'] ?? $rawItem['path_keys'] ?? null, count($path)),
        ];
        $pathId = $this->normalizePathId($rawItem['path_id'] ?? $rawItem['path_key'] ?? null);
        $parentId = $this->normalizePathId($rawItem['parent'] ?? $rawItem['parent_id'] ?? null);

        if ($parentId !== '' && (($pathIds[0] ?? null) !== $parentId)) {
            array_unshift($pathIds, $parentId);
        }

        [$label, $path] = $this->normalizeLeafLabelAndPath(
            $type,
            $label,
            $path,
            $routeName,
            $url
        );

        $hasTarget = ($routeName !== null && $routeName !== '')
            || ($url !== null && $url !== '');

        if ($pathId === '' && $path === [] && $pathIds !== [] && ! $hasTarget) {
            $pathId = (string) ($pathIds[array_key_last($pathIds)] ?? '');
        }

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
                    'path_ids' => $pathIds,
                    'path_id' => $pathId,
                    'parent_id' => $parentId,
                    'type' => $type,
                    'label' => $label,
                    'route_name' => $routeName,
                    'url' => $url,
                    'permission' => $permission,
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
                'permission' => $permission !== '' ? $permission : null,
                'order' => $order,
                'path' => $path,
                'path_ids' => $pathIds,
                'path_id' => $pathId !== '' ? $pathId : null,
                'parent_id' => $parentId !== '' ? $parentId : null,
                'source' => $source,
            ];
        }

        $nestedPath = $path;
        if ($children !== [] && $label !== '') {
            $nestedPath[] = $label;
        }

        $nestedPathIds = $pathIds;
        if ($children !== [] && $pathId !== '') {
            $nestedPathIds[] = $pathId;
        }

        if ($children !== []) {
            $results = [
                ...$results,
                ...$this->normalizeMany($children, $defaults, $nestedPath, $nestedPathIds),
            ];
        }

        return $results;
    }

    /**
     * Normalize common duplicated menu definitions:
     * - label "Patients" + path ["Patients"] => single leaf "Patients" (no wrapper group)
     * - label "Patients" + path ["Patients","List"] => group "Patients" + leaf "List"
     *
     * @param array<int, string> $path
     * @return array{0: string, 1: array<int, string>}
     */
    protected function normalizeLeafLabelAndPath(
        string $type,
        string $label,
        array $path,
        ?string $routeName,
        ?string $url
    ): array {
        if ($type !== 'item' || $label === '' || $path === []) {
            return [$label, $path];
        }

        $hasTarget = ($routeName !== null && $routeName !== '') || ($url !== null && $url !== '');
        if (! $hasTarget) {
            return [$label, $path];
        }

        $firstSegment = $path[0] ?? null;
        if (! is_string($firstSegment) || strcasecmp($firstSegment, $label) !== 0) {
            return [$label, $path];
        }

        if (count($path) === 1) {
            return [$label, []];
        }

        $lastSegment = trim((string) ($path[array_key_last($path)] ?? ''));
        if ($lastSegment !== '') {
            return [$lastSegment, array_slice($path, 0, -1)];
        }

        return [$label, $path];
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
                $normalized[] = $this->normalizeTranslatedLabel($value);
            }
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    protected function normalizePathIds(mixed $pathIds, int $maxSegments): array
    {
        if (is_string($pathIds)) {
            $pathIds = preg_split('/\s*\/\s*/', trim($pathIds)) ?: [];
        }

        if (! is_array($pathIds) || $pathIds === []) {
            return [];
        }

        $limit = $maxSegments > 0 ? $maxSegments : PHP_INT_MAX;

        $normalized = [];

        foreach ($pathIds as $segment) {
            if (! is_scalar($segment)) {
                continue;
            }

            $value = trim((string) $segment);
            if ($value === '') {
                continue;
            }

            $normalized[] = (string) \Illuminate\Support\Str::of($value)
                ->replace([' ', '\\', '/'], '-')
                ->slug('-');

            if (count($normalized) >= $limit) {
                break;
            }
        }

        return $normalized;
    }

    protected function normalizePathId(mixed $pathId): string
    {
        if (! is_scalar($pathId)) {
            return '';
        }

        $value = trim((string) $pathId);
        if ($value === '') {
            return '';
        }

        return (string) \Illuminate\Support\Str::of($value)
            ->replace([' ', '\\', '/'], '-')
            ->slug('-');
    }

    protected function normalizeTranslatedLabel(string $label): string
    {
        $trimmed = trim($label);
        if ($trimmed === '') {
            return $label;
        }

        $locale = strtolower(trim((string) app()->getLocale()));
        if ($locale === '') {
            return $label;
        }

        $reverse = $this->reverseJsonTranslations($locale);
        $normalized = $reverse[$trimmed] ?? null;

        if (! is_string($normalized) || trim($normalized) === '') {
            return $label;
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    protected function reverseJsonTranslations(string $locale): array
    {
        if (array_key_exists($locale, $this->reverseTranslationCache)) {
            return $this->reverseTranslationCache[$locale];
        }

        $paths = array_filter([
            function_exists('lang_path') ? lang_path("{$locale}.json") : null,
            __DIR__.'/../lang/'.$locale.'.json',
        ], static fn ($path): bool => is_string($path) && $path !== '');

        $translations = [];

        foreach ($paths as $path) {
            if (! File::exists($path)) {
                continue;
            }

            $decoded = json_decode((string) File::get($path), true);
            if (! is_array($decoded)) {
                continue;
            }

            foreach ($decoded as $key => $value) {
                if (! is_string($key) || ! is_string($value)) {
                    continue;
                }

                $key = trim($key);
                $value = trim($value);

                if ($key === '' || $value === '') {
                    continue;
                }

                $translations[$value] = $key;
            }
        }

        foreach ($this->reversePhpTranslations($locale) as $value => $key) {
            if (! isset($translations[$value])) {
                $translations[$value] = $key;
            }
        }

        $this->reverseTranslationCache[$locale] = $translations;

        return $translations;
    }

    /**
     * @return array<string, string>
     */
    protected function reversePhpTranslations(string $locale): array
    {
        $translations = [];

        foreach ($this->phpTranslationFiles($locale) as $path) {
            if (! File::exists($path)) {
                continue;
            }

            $decoded = include $path;
            if (! is_array($decoded)) {
                continue;
            }

            foreach (Arr::dot($decoded) as $key => $value) {
                if (! is_string($key) || ! is_string($value)) {
                    continue;
                }

                $key = trim($key);
                $value = trim($value);

                if ($key === '' || $value === '') {
                    continue;
                }

                if (! isset($translations[$value])) {
                    $translations[$value] = $key;
                }
            }
        }

        return $translations;
    }

    /**
     * @return array<int, string>
     */
    protected function phpTranslationFiles(string $locale): array
    {
        $files = [];

        $appLocaleDir = function_exists('lang_path') ? lang_path($locale) : null;
        if (is_string($appLocaleDir) && $appLocaleDir !== '' && File::isDirectory($appLocaleDir)) {
            foreach (File::files($appLocaleDir) as $file) {
                if ($file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        $packageLocaleDir = __DIR__.'/../lang/'.$locale;
        if (File::isDirectory($packageLocaleDir)) {
            foreach (File::files($packageLocaleDir) as $file) {
                if ($file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        $modulesPath = app_path('Svarium/Modules');
        if (File::isDirectory($modulesPath)) {
            $pattern = $modulesPath.'/*/Lang/'.$locale.'/*.php';
            foreach ((array) glob($pattern) as $filePath) {
                if (is_string($filePath) && $filePath !== '') {
                    $files[] = $filePath;
                }
            }
        }

        return array_values(array_unique($files));
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
