<?php

namespace Upsoftware\Svarium\Panel;

use Upsoftware\Svarium\Panel\Resource\Operations\ResourceCreateOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceDeleteOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceDuplicateOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceEditOperation;
use Upsoftware\Svarium\Panel\Resource\Operations\ResourceListOperation;

class ResourceRegistry
{
    protected array $resources = [];

    public function register(string $resourceClass): void
    {
        $this->resources[] = $resourceClass;

        $resource = app($resourceClass);
        $slug = $resource::slug();
        $panel = $this->resolvePanelName();

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
}
