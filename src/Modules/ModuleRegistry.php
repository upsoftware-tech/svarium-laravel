<?php

namespace Upsoftware\Svarium\Modules;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Upsoftware\Svarium\Events\EventBus;
use Upsoftware\Svarium\Menu\MenuRegistry;
use Upsoftware\Svarium\Panel\FieldAttributesRegistry;
use Upsoftware\Svarium\Widgets\WidgetRegistry;

class ModuleRegistry
{
    protected array $modules = [];

    public function loadFromApp(): void
    {
        $base = svarium_modules();

        if (! is_dir($base)) {
            return;
        }

        foreach (File::allFiles($base) as $file) {

            if (! str_ends_with($file->getFilename(), 'Module.php')) {
                continue;
            }

            $class = $this->classFromFile($file->getPathname());

            if (! $class || ! is_subclass_of($class, Module::class)) {
                continue;
            }

            $instance = app($class);

            $instance->setPath(dirname($file->getPathname()));

            $this->register($instance);
        }
    }

    protected function classFromFile(string $path): ?string
    {
        $relative = str_replace(app_path().DIRECTORY_SEPARATOR, '', $path);
        $relative = str_replace(['/', '.php'], ['\\', ''], $relative);

        return 'App\\'.$relative;
    }

    public function register(Module $module): void
    {
        $this->registerTranslations($module);
        $this->modules[$module->name()] = $module;
        app(ActivationRegistry::class)
            ->enable(get_class($module));
    }

    protected function registerTranslations(Module $module): void
    {
        $translationPath = $module->translationPath();
        $namespace = trim($module->translationNamespace());

        if ($translationPath === null) {
            return;
        }

        if ($namespace !== '') {
            app('translator')->addNamespace($namespace, $translationPath);
        }

        $this->registerGlobalTranslationLines($translationPath);
    }

    protected function registerGlobalTranslationLines(string $translationPath): void
    {
        if (! File::isDirectory($translationPath)) {
            return;
        }

        foreach (File::directories($translationPath) as $localeDir) {
            $locale = trim((string) basename($localeDir));
            if ($locale === '') {
                continue;
            }

            foreach (File::files($localeDir) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $group = trim((string) pathinfo($file->getFilename(), PATHINFO_FILENAME));
                if ($group === '') {
                    continue;
                }

                $content = include $file->getPathname();
                if (! is_array($content)) {
                    continue;
                }

                $flatLines = [];

                foreach (Arr::dot($content) as $key => $value) {
                    if (! is_string($key) || trim($key) === '') {
                        continue;
                    }

                    if (is_scalar($value) || $value === null) {
                        $flatKey = $group.'.'.ltrim(trim($key), '.');
                        if ($flatKey === $group.'.') {
                            continue;
                        }

                        $flatLines[$flatKey] = (string) $value;
                    }
                }

                if ($flatLines !== []) {
                    app('translator')->addLines($flatLines, $locale);
                }
            }
        }
    }

    public function all(): array
    {
        return $this->modules;
    }

    public function registerPhase(): void
    {
        $fieldAttributesRegistry = app(FieldAttributesRegistry::class);
        $fieldAttributesRegistry->clearAll();
        $fieldAttributesRegistry->addLockedDefinitions($this->resolveGlobalFieldAttributes());

        foreach ($this->modules as $module) {
            $fieldAttributes = $module->fieldAttributes();
            if (is_array($fieldAttributes) && $fieldAttributes !== []) {
                $fieldAttributesRegistry->addDefinitions($fieldAttributes);
            }

            $menu = $module->menu();
            if (is_array($menu) && $menu !== []) {
                app(MenuRegistry::class)->register($menu, [
                    'source' => get_class($module),
                ]);
            }

            $widgets = $module->widgets();
            if (is_array($widgets) && $widgets !== []) {
                app(WidgetRegistry::class)->register($widgets, [
                    'source' => get_class($module),
                ]);
            }

            $module->register();
        }
    }

    protected function resolveGlobalFieldAttributes(): array
    {
        $file = base_path('app/Svarium/attributes.php');

        if (! is_file($file)) {
            return [];
        }

        $definitions = require $file;

        if ($definitions instanceof \Closure) {
            $definitions = $definitions();
        }

        return is_array($definitions) ? $definitions : [];
    }

    public function bootPhase(): void
    {
        $bus = app(EventBus::class);

        foreach ($this->modules as $module) {

            $module->boot();

            foreach ($module->listen() as $event => $listener) {
                $bus->listen($event, $listener);
            }
        }
    }

    public function getByClass(string $class): ?Module
    {
        foreach ($this->modules as $module) {
            if (get_class($module) === $class) {
                return $module;
            }
        }

        return null;
    }

    public function has(string $class): bool
    {
        return (bool) $this->getByClass($class);
    }
}
