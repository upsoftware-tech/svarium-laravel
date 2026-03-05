<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class Badge extends Component
{
    public static function make(string|int|float|bool|null $label = null): static
    {
        $instance = parent::make();

        if ($label !== null) {
            $instance->label($label);
        }

        return $instance;
    }

    public function label(string|int|float|bool $label): static
    {
        return $this->prop('label', (string) $label);
    }

    public function text(string|int|float|bool $text): static
    {
        return $this->label($text);
    }

    public function variant(string $variant): static
    {
        return $this->prop('variant', $variant);
    }
}
