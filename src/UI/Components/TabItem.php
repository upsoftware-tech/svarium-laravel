<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class TabItem extends Component
{
    public static function make(array|string|null $name = null): static
    {
        $instance = new static($name);

        if (is_string($name)) {
            $instance->prop('name', $name);
        }

        return $instance;
    }

    public function disabled(bool $state = true): static
    {
        return $this->prop('disabled', $state);
    }

    public function icon(string $icon): static
    {
        return $this->prop('icon', $icon);
    }

    public function badge(string|int $value): static
    {
        return $this->prop('badge', $value);
    }
}
