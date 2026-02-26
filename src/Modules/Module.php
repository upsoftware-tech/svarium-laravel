<?php

namespace Upsoftware\Svarium\Modules;

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

    abstract public function name(): string;

    public function register(): void {}

    public function boot(): void {}
}
