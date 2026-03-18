<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class Separator extends Component
{
    use HasChildren;

    public static function make(?string $name = null): static
    {
        $instance = parent::make($name);

        if (is_string($name) && trim($name) !== '') {
            $instance->text($name);
        }

        return $instance;
    }

    public function text(string $text): static
    {
        return $this->children([
            Text::make($text),
        ]);
    }

    public function orientation(string $orientation): static
    {
        $value = strtolower(trim($orientation));
        if (! in_array($value, ['horizontal', 'vertical'], true)) {
            $value = 'horizontal';
        }

        return $this->prop('orientation', $value);
    }

    public function position(string $position): static
    {
        if ($position === 'left') {
            return $this->left(true);
        } elseif ($position === 'right') {
            return $this->right(true);
        }
    }

    public function left(bool $enabled = true): static
    {
        return $this->prop('left', $enabled);
    }

    public function right(bool $enabled = true): static
    {
        return $this->prop('right', $enabled);
    }
}
