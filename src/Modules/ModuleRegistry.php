<?php

namespace Upsoftware\Svarium\Modules;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Translation\Translator;
use Upsoftware\Svarium\Events\EventBus;
use Upsoftware\Svarium\Menu\MenuRegistry;
use Upsoftware\Svarium\Panel\FieldAttributesRegistry;
use Upsoftware\Svarium\Roles\RoleParameterRegistry;
use Upsoftware\Svarium\Widgets\WidgetRegistry;

class ModuleRegistry
{
    protected array $modules = [];

    public function loadFromPackage(): void
    {
        $this->loadFromPath(
            __DIR__.DIRECTORY_SEPARATOR.'Builtin',
            'Upsoftware\\Svarium\\Modules\\Builtin',
            true
        );
    }

    public function loadFromApp(): void
    {
        $this->loadFromPath(
            svarium_modules(),
            'App\\Svarium\\Modules',
            false
        );
    }

    protected function loadFromPath(string $base, string $namespace, bool $packageModules = false): void
    {
        if (! is_dir($base)) {
            return;
        }

        foreach (File::allFiles($base) as $file) {
            if (! str_ends_with($file->getFilename(), 'Module.php')) {
                continue;
            }

            $class = $this->classFromFile($file->getPathname(), $base, $namespace);

            if (! $class || ! class_exists($class) || ! is_subclass_of($class, Module::class)) {
                continue;
            }

            $instance = app($class);

            if ($packageModules && ! $this->packageModuleEnabled($instance, $class)) {
                continue;
            }

            $instance->setPath(dirname($file->getPathname()));

            $this->register($instance);
        }
    }

    protected function packageModuleEnabled(Module $module, string $class): bool
    {
        $name = strtolower(trim($module->name()));

        if ($name === '') {
            $name = strtolower(trim((string) preg_replace('/Module$/', '', class_basename($class))));
        }

        if ($name === '') {
            return true;
        }

        return (bool) config("upsoftware.modules.builtin.{$name}", true);
    }

    protected function classFromFile(string $path, string $basePath, string $namespace): ?string
    {
        $normalizedBase = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $basePath), DIRECTORY_SEPARATOR);
        $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if (! str_starts_with($normalizedPath, $normalizedBase.DIRECTORY_SEPARATOR)) {
            return null;
        }

        $relative = substr($normalizedPath, strlen($normalizedBase) + 1);
        if ($relative === false || $relative === '') {
            return null;
        }

        $relative = str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relative);

        return trim($namespace, '\\').'\\'.$relative;
    }

    public function register(Module $module): void
    {
        $this->registerTranslations($module);
        $this->modules[$module->name()] = $module;
        app(ActivationRegistry::class)
            ->enable(get_class($module));
    }

    public function registerRuntime(Module $module): Module
    {
        $existing = $this->getByClass(get_class($module));
        if ($existing instanceof Module) {
            return $existing;
        }

        $reflection = new \ReflectionClass($module);
        $moduleFile = $reflection->getFileName();
        if (is_string($moduleFile) && $moduleFile !== '') {
            $module->setPath(dirname($moduleFile));
        }

        $this->register($module);
        $this->bootRegistrationForModule($module);
        $this->bootModule($module);

        return $module;
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
        $roleParameterRegistry = app(RoleParameterRegistry::class);
        $fieldAttributesRegistry->clearAll();
        $roleParameterRegistry->clear();
        $fieldAttributesRegistry->addLockedDefinitions($this->resolveGlobalFieldAttributes());

        foreach ($this->modules as $module) {
            $this->bootRegistrationForModule($module);
        }
    }

    protected function bootRegistrationForModule(Module $module): void
    {
        $fieldAttributes = $this->resolveFieldAttributesWithKeyLocale($module);
        if (is_array($fieldAttributes) && $fieldAttributes !== []) {
            app(FieldAttributesRegistry::class)->addDefinitions($fieldAttributes);
        }

        $roleParameters = $module->roleParameters();
        if (is_array($roleParameters) && $roleParameters !== []) {
            app(RoleParameterRegistry::class)->registerMany($roleParameters, get_class($module));
        }

        $module->register();

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
    }

    protected function resolveGlobalFieldAttributes(): array
    {
        $file = base_path('app/Svarium/attributes.php');

        if (! is_file($file)) {
            return [];
        }

        $definitions = $this->withFieldAttributesLocale(static function () use ($file) {
            $resolved = require $file;

            if ($resolved instanceof \Closure) {
                return $resolved();
            }

            return $resolved;
        });

        return is_array($definitions) ? $definitions : [];
    }

    protected function resolveFieldAttributesWithKeyLocale(Module $module): array
    {
        $resolved = $this->withFieldAttributesLocale(static fn () => $module->fieldAttributes());

        return is_array($resolved) ? $resolved : [];
    }

    protected function withFieldAttributesLocale(callable $resolver): mixed
    {
        $translator = app('translator');

        if (! $translator instanceof Translator) {
            return $resolver();
        }

        $originalLocale = $translator->getLocale();
        $keyLocale = $this->fieldAttributesKeyLocale($originalLocale);

        if ($keyLocale === $originalLocale) {
            return $resolver();
        }

        app()->setLocale($keyLocale);
        $translator->setLocale($keyLocale);

        try {
            return $resolver();
        } finally {
            app()->setLocale($originalLocale);
            $translator->setLocale($originalLocale);
        }
    }

    protected function fieldAttributesKeyLocale(string $fallback): string
    {
        $configured = config('upsoftware.lang.key_locale');

        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        return 'en';
    }

    public function bootPhase(): void
    {
        $bus = app(EventBus::class);

        foreach ($this->modules as $module) {
            $this->bootModule($module, $bus);
        }
    }

    protected function bootModule(Module $module, ?EventBus $bus = null): void
    {
        $bus ??= app(EventBus::class);

        $module->boot();

        foreach ($module->listen() as $event => $listener) {
            $bus->listen($event, $listener);
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
