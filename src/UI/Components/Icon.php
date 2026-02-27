<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class Icon extends Component implements \JsonSerializable
{
    protected array|string|null $icon = null;

    public static function make(array|string|null $name = null): static
    {
        $instance = new static;
        $instance->icon = $name;

        return $instance;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        $component = parent::toArray();
        $props = $component['props'] ?? [];

        if (! is_array($props)) {
            $props = [];
        }

        $props['icon'] = $this->icon;
        $component['props'] = $props;
        $component['type'] = 'Icon';

        return $component;
    }
}
