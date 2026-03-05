<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class RadioItem extends Component
{
    public function value(string|int|float|bool $value): static
    {
        return $this->prop('value', $value);
    }

    public function label(string $label): static
    {
        return $this->prop('label', $label);
    }

    public function disabled(bool $disabled = true): static
    {
        return $this->prop('disabled', $disabled);
    }
}
