<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class MenuManager extends Component
{
    public static function make(?string $name = null): static
    {
        return parent::make($name);
    }

    public function sections(array $sections): static
    {
        return $this->prop('sections', array_values($sections));
    }

    public function items(array $items): static
    {
        return $this->prop('items', array_values($items));
    }
}
