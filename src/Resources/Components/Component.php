<?php

namespace Upsoftware\Svarium\Resources\Components;

use Upsoftware\Svarium\UI\Appearance;

abstract class Component
{
    protected ?string $name = null;
    protected string $component = '';
    protected string $className = '';
    protected array $appearanceProps = [];

    public function __construct(string|array|null $name = null)
    {
        $this->name = $name;
    }

    function is_multidimensional(array $array): bool {
        return count($array) !== count($array, COUNT_RECURSIVE);
    }

    public static function make(string|array|null $name = null): static
    {
        return new static($name);
    }

    public function renderComponent(string | array $children): string | array {
        return is_array($children) ? collect($children)->map(function ($item) {
            return $item instanceof Component ? $item->toArray() : $item;
        })->all()
            : $children;
    }

    public function appearance(array|Appearance $appearance): static
    {
        if ($appearance instanceof Appearance) {
            $appearance = $appearance->toArray();
        }

        $this->appearanceProps = [
            ...$this->appearanceProps,
            ...$appearance,
        ];

        return $this;
    }

    protected function mergeOptions(array $props = []): array
    {
        if (empty($this->appearanceProps)) {
            return $props;
        }

        $currentAppearance = $props['appearance'] ?? [];

        if (! is_array($currentAppearance)) {
            $currentAppearance = [];
        }

        return [
            ...$props,
            'appearance' => [
                ...$currentAppearance,
                ...$this->appearanceProps,
            ],
        ];
    }

    public function toArray(): array
    {
        $array = [
            'component' => ucfirst($this->component),
        ];

        if ($this->name) {
            $array['name'] = $this->name;
        }

        if ($this->className) {
            $array['class'] = $this->className;
        }

        if (! empty($this->appearanceProps)) {
            $array['props'] = [
                'appearance' => $this->appearanceProps,
            ];
        }

        return $array;
    }
}
