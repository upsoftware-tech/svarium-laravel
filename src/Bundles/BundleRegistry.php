<?php

namespace Upsoftware\Svarium\Bundles;

use Upsoftware\Svarium\Modules\ActivationRegistry;
use Upsoftware\Svarium\Modules\ModuleRegistry;

class BundleRegistry
{
    protected array $bundles = [];

    public function register(string $bundleClass): void
    {
        $this->bundles[] = $bundleClass;
    }

    public function boot(): void
    {
        $modules = app(ModuleRegistry::class);
        $activation = app(ActivationRegistry::class);

        foreach ($this->bundles as $bundleClass) {

            $bundle = app($bundleClass);

            foreach ($bundle->modules() as $moduleClass) {

                if ($modules->has($moduleClass)) {
                    $activation->enable($moduleClass);
                }
            }

            $bundle->boot();
        }
    }

    public function all(): array
    {
        return $this->bundles;
    }
}
