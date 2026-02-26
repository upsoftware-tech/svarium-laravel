<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class Icon extends Component implements \JsonSerializable
{
    protected ?string $name;

    public static function make(array|string|null $name = null): static
    {
        $instance = new static;
        $instance->name = $name;

        return $instance;
    }

    public function jsonSerialize(): mixed
    {
        $component = $this->toArray();
        $props = $component['props'] ?? null;
        $props['icon'] = $this->name;

        return array_merge($component, ['props' => $props, 'type' => 'Icon']);
    }
}
