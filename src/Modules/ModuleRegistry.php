<?php

namespace Upsoftware\Svarium\Modules;

use Illuminate\Support\Facades\File;
use Upsoftware\Svarium\Events\EventBus;

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
        $this->modules[$module->name()] = $module;
        app(ActivationRegistry::class)
            ->enable(get_class($module));
    }

    public function all(): array
    {
        return $this->modules;
    }

    public function registerPhase(): void
    {
        foreach ($this->modules as $module) {
            $module->register();
        }
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
