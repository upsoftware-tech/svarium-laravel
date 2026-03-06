<?php

namespace Upsoftware\Svarium\Panel;

use Illuminate\Support\Facades\File;
use Upsoftware\Svarium\Menu\MenuRegistry;
use Upsoftware\Svarium\Modules\ActivationRegistry;
use Upsoftware\Svarium\Modules\ModuleRegistry;
use Upsoftware\Svarium\Widgets\WidgetRegistry;

class OperationRegistry
{
    protected array $routes = [];

    public function register(string $panel, array $methods, string $uri, string $operation, array $meta = []): void
    {

        [$pattern, $names] = $this->compile($uri);

        foreach ($methods as $method) {

            $this->routes[$panel][strtoupper($method)][] = [
                'operation' => $operation,
                'pattern' => $pattern,
                'names' => $names,
                'meta' => $meta,
            ];
        }
    }

    public function resolve(string $panel, string $method, string $uri): ?array
    {
        foreach ($this->routes[$panel][$method] ?? [] as $route) {

            if (preg_match($route['pattern'], $uri, $matches)) {

                array_shift($matches);

                $params = array_combine($route['names'], $matches);

                return [
                    'operation' => $route['operation'],
                    'params' => $params,
                    'meta' => $route['meta'] ?? [],
                ];
            }
        }

        return null;
    }

    protected function compile(string $uri): array
    {
        preg_match_all('/\{([^}]+)\}/', $uri, $paramNames);

        $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $uri);
        $pattern = '#^'.$pattern.'$#';

        return [$pattern, $paramNames[1]];
    }

    public function bootFromModules(ModuleRegistry $modules): void
    {
        $activation = app(ActivationRegistry::class);

        foreach ($modules->all() as $module) {

            $moduleClass = get_class($module);
            if (! $activation->isEnabled($moduleClass)) {
                continue;
            }

            $this->registerOperationsFromPath($module->path('Panel'));
        }

        // Preferred path for non-module operations.
        $this->registerOperationsFromPath(svarium_path('Operations'));
        // Backward compatible path for existing projects.
        $this->registerOperationsFromPath(svarium_path('Panel/Operations'));
        // Built-in operations provided by the package.
        $this->registerOperationsFromPath(__DIR__.'/Operations');
    }

    protected function registerOperationsFromPath(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (File::allFiles($path) as $file) {
            $class = $this->classFromFile($file->getPathname());

            if (! class_exists($class) || ! is_subclass_of($class, Operation::class)) {
                continue;
            }

            $uri = method_exists($class, 'uri')
                ? (string) $class::uri()
                : '';

            $menu = method_exists($class, 'menu')
                ? (array) $class::menu()
                : [];

            if ($menu !== []) {
                app(MenuRegistry::class)->register($menu, [
                    'source' => $class,
                ]);
            }

            $widgets = method_exists($class, 'widgets')
                ? (array) $class::widgets()
                : [];

            if ($widgets !== []) {
                app(WidgetRegistry::class)->register($widgets, [
                    'source' => $class,
                    'contexts' => $this->widgetContextsFromUri($uri),
                ]);
            }

            $methods = method_exists($class, 'methods')
                ? (array) $class::methods()
                : ['GET'];

            foreach ($this->resolvePanelsForOperation($class) as $panel) {
                $this->register(
                    $panel,
                    $methods,
                    $uri,
                    $class
                );
            }
        }
    }

    protected function resolvePanelsForOperation(string $class): array
    {
        $legacyPanel = $this->readStaticProperty($class, 'panel');

        if (is_string($legacyPanel) && trim($legacyPanel) !== '') {
            return [trim($legacyPanel)];
        }

        if (is_array($legacyPanel)) {
            $normalizedLegacyPanels = $this->normalizePanels($legacyPanel);
            if ($normalizedLegacyPanels !== []) {
                return $normalizedLegacyPanels;
            }
        }

        $panels = $this->readStaticProperty($class, 'panels');
        $normalizedPanels = $this->normalizePanels($panels);

        if (in_array('*', $normalizedPanels, true)) {
            $panelNames = array_keys(app(PanelRegistry::class)->all());

            if ($panelNames !== []) {
                return $panelNames;
            }
        }

        if ($normalizedPanels !== []) {
            return $normalizedPanels;
        }

        return [$this->resolveDefaultPanelName()];
    }

    protected function resolveDefaultPanelName(): string
    {
        $panels = app(PanelRegistry::class)->all();
        $configured = trim((string) config('upsoftware.panel.name', ''));

        if ($panels !== []) {
            $noPrefixPanels = array_filter(
                $panels,
                static fn ($panel): bool => $panel instanceof Panel && $panel->prefix === null
            );

            if (count($noPrefixPanels) === 1) {
                return (string) array_key_first($noPrefixPanels);
            }

            if ($configured !== '' && array_key_exists($configured, $panels)) {
                return $configured;
            }

            return (string) array_key_first($panels);
        }

        if ($configured !== '') {
            return $configured;
        }

        return 'admin';
    }

    protected function normalizePanels(mixed $panels): array
    {
        if (is_string($panels)) {
            $panels = [$panels];
        }

        if (! is_array($panels)) {
            return [];
        }

        $normalized = [];

        foreach ($panels as $panel) {
            if (! is_string($panel)) {
                continue;
            }

            $panel = trim($panel);

            if ($panel === '') {
                continue;
            }

            $normalized[] = $panel;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<int, string>
     */
    protected function widgetContextsFromUri(string $uri): array
    {
        $normalized = trim($uri, '/');

        if ($normalized === '') {
            return ['dashboard'];
        }

        $segments = array_values(array_filter(
            explode('/', $normalized),
            static function (string $segment): bool {
                $segment = trim($segment);

                if ($segment === '') {
                    return false;
                }

                if (str_starts_with($segment, '{') && str_ends_with($segment, '}')) {
                    return false;
                }

                return true;
            }
        ));

        if ($segments === []) {
            return ['dashboard'];
        }

        $dot = implode('.', $segments);
        $contexts = [$dot];

        if (count($segments) === 1) {
            $contexts[] = $segments[0].'.index';
            $contexts[] = $segments[0];
        }

        return array_values(array_unique(array_filter($contexts)));
    }

    protected function readStaticProperty(string $class, string $property): mixed
    {
        try {
            $reflection = new \ReflectionClass($class);

            if (! $reflection->hasProperty($property)) {
                return null;
            }

            $reflectionProperty = $reflection->getProperty($property);
            $reflectionProperty->setAccessible(true);

            return $reflectionProperty->getValue();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function classFromFile(string $path): string
    {
        $contents = (string) File::get($path);

        $namespace = null;
        if (preg_match('/^\s*namespace\s+([^;]+);/m', $contents, $namespaceMatch)) {
            $namespace = trim((string) ($namespaceMatch[1] ?? ''));
        }

        $class = null;
        if (preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/m', $contents, $classMatch)) {
            $class = trim((string) ($classMatch[1] ?? ''));
        }

        if ($namespace !== null && $namespace !== '' && $class !== null && $class !== '') {
            return $namespace.'\\'.$class;
        }

        $relative = str_replace(app_path().DIRECTORY_SEPARATOR, '', $path);
        $relative = str_replace(['/', '.php'], ['\\', ''], $relative);

        return 'App\\'.$relative;
    }
}
