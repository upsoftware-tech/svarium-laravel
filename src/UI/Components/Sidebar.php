<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;

class Sidebar extends Component
{
    public static function make(?string $name = null): static
    {
        return parent::make($name)
            ->width(280)
            ->position('left');
    }

    public function width(int $width): static
    {
        return $this->prop('width', $width);
    }

    public function offset(int $offset): static
    {
        return $this->prop('offset', $offset);
    }

    public function position(string $position): static
    {
        $position = strtolower(trim($position));

        if (! in_array($position, ['left', 'top'], true)) {
            throw new \InvalidArgumentException('Sidebar position must be one of: left, top.');
        }

        return $this->prop('position', $position);
    }
}
