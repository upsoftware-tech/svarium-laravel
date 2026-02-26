<?php

namespace Upsoftware\Svarium\UI\Components\Table;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class TableBody extends Component
{
    use HasChildren;

    protected ?string $type = 'TableBody';

    public static function make(?string $name = null): static
    {
        return new static;
    }

    public function toArray(): array
    {
        return [
            'type' => 'TableBody',
            'props' => $this->props,
            'children' => $this->serializeChildren(),
            'slots' => [],
        ];
    }
}
