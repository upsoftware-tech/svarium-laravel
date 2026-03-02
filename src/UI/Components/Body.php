<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class Body extends Component
{
    public static function make(?string $name = null): static
    {
        return parent::make($name);
    }
}
