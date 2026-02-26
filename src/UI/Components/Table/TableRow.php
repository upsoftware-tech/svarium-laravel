<?php

namespace Upsoftware\Svarium\UI\Components\Table;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class TableRow extends Component
{
    use HasChildren;

    protected ?string $type = 'TableRow';

    public static function make(?string $name = null): static
    {
        return new static;
    }

    public function toArray(): array
    {
        return [
            'type' => 'TableRow',
            'props' => $this->props,
            'children' => $this->serializeChildren(),
            'slots' => [],
        ];
    }
}
