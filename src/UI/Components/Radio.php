<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class Radio extends Component
{
    public function name(string $name): static
    {
        return $this->prop('name', $name);
    }

    public function value(string|int|float|bool $value): static
    {
        return $this->prop('value', $value);
    }

    public function checked(bool $checked = true): static
    {
        return $this->prop('checked', $checked);
    }
}
