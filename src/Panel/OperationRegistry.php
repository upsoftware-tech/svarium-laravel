<?php

namespace Upsoftware\Svarium\Panel;

use Illuminate\Support\Facades\File;
use Upsoftware\Svarium\Modules\ActivationRegistry;
use Upsoftware\Svarium\Modules\ModuleRegistry;

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

        // Backward compatible path for non-module operations.
        $this->registerOperationsFromPath(svarium_path('Panel/Operations'));
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

        return $normalizedPanels !== []
            ? $normalizedPanels
            : ['admin'];
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
        $relative = str_replace(app_path().DIRECTORY_SEPARATOR, '', $path);
        $relative = str_replace(['/', '.php'], ['\\', ''], $relative);

        return 'App\\'.$relative;
    }
}
