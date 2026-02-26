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

            $panelPath = $module->path('Panel');

            if (! is_dir($panelPath)) {
                continue;
            }

            foreach (File::allFiles($panelPath) as $file) {

                $class = $this->classFromFile($file->getPathname());

                if (! class_exists($class) || ! is_subclass_of($class, Operation::class)) {
                    continue;
                }

                foreach ((array) $class::$panels as $panel) {

                    $this->register(
                        $panel,
                        $class::methods(),
                        $class::uri(),
                        $class
                    );
                }
            }

        }
    }

    protected function classFromFile(string $path): string
    {
        $relative = str_replace(app_path().DIRECTORY_SEPARATOR, '', $path);
        $relative = str_replace(['/', '.php'], ['\\', ''], $relative);

        return 'App\\'.$relative;
    }
}
