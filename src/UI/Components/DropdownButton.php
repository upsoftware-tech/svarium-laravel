<?php

namespace Upsoftware\Svarium\UI\Components;

use Upsoftware\Svarium\UI\Components\Form\Select;
use Upsoftware\Svarium\UI\Concerns\Props\HasIcon;

class DropdownButton extends Select
{
    use HasIcon;

    public function size(string $size): static
    {
        return $this->prop('size', trim($size));
    }

    public function align(string $align): static
    {
        $normalized = strtolower(trim($align));
        if (! in_array($normalized, ['start', 'center', 'end'], true)) {
            $normalized = 'start';
        }

        return $this->prop('align', $normalized);
    }

    public function default(mixed $value): static
    {
        parent::default($value);

        return $this->prop('defaultValue', $value);
    }
}
