<?php

namespace Upsoftware\Svarium\UI\Components\Table;

use Upsoftware\Svarium\UI\Component;
use Upsoftware\Svarium\UI\Concerns\Props\HasChildren;

class TableCell extends Component
{
    use HasChildren;

    protected ?string $type = 'TableCell';

    public static function make(?string $name = null): static
    {
        return new static;
    }

    public function align(string $align): static
    {
        return $this->prop('align', $align); // start | center | end
    }

    public function width(string $width): static
    {
        return $this->prop('width', $width);
    }

    public function bold(bool $state = true): static
    {
        return $this->prop('bold', $state);
    }

    public function toArray(): array
    {
        return [
            'type' => 'TableCell',
            'props' => $this->props,
            'children' => $this->serializeChildren(),
            'slots' => [],
        ];
    }
}
