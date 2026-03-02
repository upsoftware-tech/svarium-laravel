<?php

namespace Upsoftware\Svarium\Modules;

use Upsoftware\Svarium\Menu\MenuRegistry;
use Upsoftware\Svarium\Panel\ResourceRegistry;

abstract class Module
{
    protected string $path;

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function path(string $append = ''): string
    {
        return $this->path.($append ? DIRECTORY_SEPARATOR.$append : '');
    }

    public function requires(): array
    {
        return [];
    }

    protected function registerResource(string $resourceClass): void
    {
        app(ResourceRegistry::class)->register($resourceClass);
    }

    public function listen(): array
    {
        return [];
    }

    public function menu(): array
    {
        return [];
    }

    protected function registerMenu(array $items, string|int|null $navigationId = null): void
    {
        app(MenuRegistry::class)->register($items, [
            'source' => static::class,
            'navigation_id' => $navigationId,
        ]);
    }

    abstract public function name(): string;

    public function register(): void {}

    public function boot(): void {}
}
