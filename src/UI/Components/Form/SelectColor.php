<?php

namespace Upsoftware\Svarium\UI\Components\Form;

use Upsoftware\Svarium\UI\Components\FieldComponent;

class SelectColor extends FieldComponent
{
    public function options(array $options): static
    {
        return $this->prop('options', $options);
    }

    public function colors(array $colors): static
    {
        return $this->options($colors);
    }

    public function placeholder(string $placeholder): static
    {
        return $this->prop('placeholder', $placeholder);
    }

    public function clear(bool $enabled = true): static
    {
        return $this->prop('clear', $enabled);
    }

    public function allowCustom(bool $enabled = true): static
    {
        return $this->prop('allowCustom', $enabled);
    }
}

