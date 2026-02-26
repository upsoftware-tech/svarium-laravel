<?php

namespace Upsoftware\Svarium\Plugins;
use Illuminate\Support\ServiceProvider;

abstract class PluginServiceProvider extends ServiceProvider
{
    protected string $pluginName;
    protected bool $hasViews = true;
    protected bool $hasTranslations = true;
    protected bool $hasMigrations = true;

    public function boot()
    {
        if ($this->hasViews) {
            $this->loadViewsFrom(__DIR__ . '/../resources/views', $this->pluginName);
        }
        if ($this->hasTranslations) {
            $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', $this->pluginName);
        }
        if ($this->hasMigrations) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        $this->registerSvariumFeatures();
    }

    abstract public function registerSvariumFeatures(): void;
}
