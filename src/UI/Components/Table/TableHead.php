<?php

namespace Upsoftware\Svarium\UI\Components\Table;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class TableHead extends Component
{
    use HasChildren;

    public static function make(?string $name = null): static
    {
        return new static;
    }

    public function align(string $align): static
    {
        return $this->prop('align', $align); // start | center | end
    }

    public function sortable(bool $state = true): static
    {
        return $this->prop('sortable', $state);
    }

    public function toArray(): array
    {
        return [
            'type' => 'TableHead',
            'props' => $this->props,
            'children' => $this->serializeChildren(),
            'slots' => [],
        ];
    }
}
