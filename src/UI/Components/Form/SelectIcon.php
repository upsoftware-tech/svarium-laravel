<?php

namespace Upsoftware\Svarium\UI\Components\Form;

use Upsoftware\Svarium\UI\Components\FieldComponent;

class SelectIcon extends FieldComponent
{
    public function icons(array $icons): static
    {
        return $this->prop('icons', $icons);
    }

    public function placeholder(string $placeholder): static
    {
        return $this->prop('placeholder', $placeholder);
    }

    public function clear(bool $enabled = true): static
    {
        return $this->prop('clear', $enabled);
    }

    public function searchable(bool $enabled = true): static
    {
        return $this->prop('searchable', $enabled);
    }
}
