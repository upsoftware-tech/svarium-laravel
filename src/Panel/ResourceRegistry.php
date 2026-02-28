<?php

namespace Upsoftware\Svarium\Panel;

use Illuminate\Support\Facades\Route;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceCreateOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceDeleteOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceDuplicateOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceEditOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceListOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourcePreviewOperation;
use Upsoftware\Svarium\Routing\SvariumHttpKernel;

class ResourceRegistry
{
    protected array $resources = [];

    public function register(string $resourceClass): void
    {
        $this->resources[] = $resourceClass;

        $resource = app($resourceClass);
        $slug = $resource::slug();
        $panel = $this->resolvePanelName();
        $this->registerModuleRouteAliases($resourceClass, $panel, $slug);

        $registry = app(OperationRegistry::class);

        $registry->register($panel, ['GET', 'POST'], $slug, ResourceListOperation::class, [
            'resource' => $resourceClass,
        ]);

        $registry->register($panel, ['GET', 'POST'], "{$slug}/create", ResourceCreateOperation::class, [
            'resource' => $resourceClass,
        ]);

        $registry->register($panel, ['GET', 'POST'], "{$slug}/{id}/edit", ResourceEditOperation::class, [
            'resource' => $resourceClass,
        ]);

        $registry->register($panel, ['GET'], "{$slug}/{id}/preview", ResourcePreviewOperation::class, [
            'resource' => $resourceClass,
        ]);

        $registry->register($panel, ['POST'], "{$slug}/{id}/delete", ResourceDeleteOperation::class, [
            'resource' => $resourceClass,
        ]);

        $registry->register($panel, ['GET', 'POST'], "{$slug}/{id}/duplicate", ResourceDuplicateOperation::class, [
            'resource' => $resourceClass,
        ]);
    }

    public function all(): array
    {
        return $this->resources;
    }

    protected function resolvePanelName(): string
    {
        $configured = config('upsoftware.panel.name');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $panels = app(PanelRegistry::class)->all();

        if ($panels !== []) {
            return array_key_first($panels);
        }

        return 'admin';
    }

    protected function registerModuleRouteAliases(string $resourceClass, string $panel, string $slug): void
    {
        $module = (string) str(class_basename($resourceClass))
            ->replace('Resource', '')
            ->snake();

        if ($module === '') {
            $module = (string) str($slug)->singular()->snake();
        }

        $base = trim(implode('/', array_filter([
            trim($panel, '/'),
            trim($slug, '/'),
        ])), '/');

        if ($base === '') {
            return;
        }

        $routes = [
            "module:{$module}" => $base,
            "module:{$module}.create" => "{$base}/create",
            "module:{$module}.edit" => "{$base}/{id}/edit",
            "module:{$module}.preview" => "{$base}/{id}/preview",
            "module:{$module}.delete" => "{$base}/{id}/delete",
            "module:{$module}.duplicate" => "{$base}/{id}/duplicate",
        ];

        foreach ($routes as $name => $uri) {
            if (Route::has($name)) {
                continue;
            }

            Route::middleware(['web'])
                ->any($uri, SvariumHttpKernel::class)
                ->name($name);
        }
    }
}
