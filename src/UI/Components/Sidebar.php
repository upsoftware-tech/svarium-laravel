<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class Sidebar extends Component
{
    use HasChildren;

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

    public function header(Component|array|string|\Closure|null $content): static
    {
        return $this->slot('header', $content);
    }

    public function footer(Component|array|string|\Closure|null $content): static
    {
        return $this->slot('footer', $content);
    }

    public function top(Component|array|string|\Closure|null $content): static
    {
        return $this->slot('top', $content);
    }

    public function bottom(Component|array|string|\Closure|null $content): static
    {
        return $this->slot('bottom', $content);
    }
}
