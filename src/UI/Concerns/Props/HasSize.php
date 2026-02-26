<?php

namespace Upsoftware\Svarium\UI\Concerns\Props;

trait HasSize
{
    public string $size;

    public function size(string $size): static
    {
        $this->size = $size;
        $this->prop('size', $size);

        return $this;
    }
}
