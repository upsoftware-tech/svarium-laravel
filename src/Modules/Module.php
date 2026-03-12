<?php

namespace Upsoftware\Svarium\Modules;

use Illuminate\Support\Str;
use Upsoftware\Svarium\Panel\FieldAttributesRegistry;
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

    public function translationPath(): ?string
    {
        $path = $this->path('Lang');

        return is_dir($path) ? $path : null;
    }

    public function translationNamespace(): string
    {
        return (string) Str::of($this->name())->snake()->toString();
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

    public function widgets(): array
    {
        return [];
    }

    /**
     * Additional role parameter definitions exposed by the module.
     *
     * Example:
     * [
     *   'languages' => [
     *     'label' => __('Languages'),
     *     'options' => [
     *       ['value' => 'pl', 'label' => 'Polski'],
     *       ['value' => 'en', 'label' => 'English'],
     *     ],
     *   ],
     * ]
     */
    public function roleParameters(): array
    {
        return [];
    }

    /**
     * Global field attributes for module components.
     * Used when Column/Input has no explicit label/props.
     *
     * Example:
     * [
     *   'lastname' => ['label' => __('Last name')],
     *   'status' => [
     *     'label' => __('Status'),
     *     'column' => ['sortable' => true],
     *     'input' => ['placeholder' => __('Choose status')],
     *   ],
     * ]
     */
    public function fieldAttributes(): array
    {
        return [];
    }

    protected function registerFieldAttributes(array $definitions): void
    {
        if ($definitions === []) {
            return;
        }

        app(FieldAttributesRegistry::class)->addDefinitions($definitions);
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
