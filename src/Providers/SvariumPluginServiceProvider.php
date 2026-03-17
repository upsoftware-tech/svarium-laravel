<?php

namespace Upsoftware\Svarium\Providers;

use Illuminate\Support\ServiceProvider;
use Upsoftware\Svarium\Modules\Module;
use Upsoftware\Svarium\Modules\ModuleRegistry;
use Upsoftware\Svarium\Panel\OperationRegistry;

abstract class SvariumPluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {

    }

    protected function registerSvariumModule(string|Module $module): void
    {
        $callback = function () use ($module): void {
            $instance = is_string($module) ? app($module) : $module;

            if (! $instance instanceof Module) {
                return;
            }

            $registeredModule = app(ModuleRegistry::class)->registerRuntime($instance);

            app(OperationRegistry::class)->bootFromModule($registeredModule);
        };

        if (method_exists($this->app, 'isBooted') && $this->app->isBooted()) {
            $callback();

            return;
        }

        $this->app->booted($callback);
    }

    protected function registerSvariumModules(array $modules): void
    {
        foreach ($modules as $module) {
            if (! is_string($module) && ! $module instanceof Module) {
                continue;
            }

            $this->registerSvariumModule($module);
        }
    }
}
