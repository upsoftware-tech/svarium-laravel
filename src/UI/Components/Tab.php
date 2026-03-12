<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class Tab extends Component
{
    use HasChildren;
    public static function make(array|string|null $name = null): static
    {
        return new static($name);
    }

    public function items(array $items): static
    {
        foreach ($items as $item) {
            if ($item instanceof TabItem) {
                $this->child($item);
            }
        }

        return $this;
    }

    public function header(Component|array|string|\Closure|null $content): static
    {
        return $this->slot('header', $content);
    }

    public function defaultOpen(string|int|null $value): static
    {
        $this->prop('defaultOpen', $value);

        return $this;
    }

    public function position(string $position): static
    {
        $normalized = strtolower(trim($position));

        $normalized = match ($normalized) {
            'vertical' => 'left',
            'horizontal' => 'top',
            default => $normalized,
        };

        if (! in_array($normalized, ['top', 'right', 'bottom', 'left'], true)) {
            $normalized = 'top';
        }

        return $this->prop('position', $normalized);
    }

    public function variant(string $variant): static
    {
        $normalized = strtolower(trim($variant));

        if (! in_array($normalized, ['default', 'simple'], true)) {
            $normalized = 'default';
        }

        return $this->prop('variant', $normalized);
    }

    public function top(): static
    {
        return $this->position('top');
    }

    public function left(): static
    {
        return $this->position('left');
    }

    public function right(): static
    {
        return $this->position('right');
    }

    public function bottom(): static
    {
        return $this->position('bottom');
    }

    public function vertical(): static
    {
        return $this->position('vertical');
    }

    public function horizontal(): static
    {
        return $this->position('horizontal');
    }
}
