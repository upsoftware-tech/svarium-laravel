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

    public function defaultOpen(string|int|null $value): static
    {
        $this->prop('defaultOpen', $value);

        return $this;
    }
}
