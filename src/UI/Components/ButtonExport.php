<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class ButtonExport extends Component
{
    public static function make(?string $label = null): static
    {
        $instance = new static;

        if (is_string($label) && trim($label) !== '') {
            $instance->label($label);
        }

        return $instance;
    }
}

