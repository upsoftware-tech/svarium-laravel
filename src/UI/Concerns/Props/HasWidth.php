<?php

namespace Upsoftware\Svarium\UI\Concerns\Props;

trait HasWidth
{
    protected $width = null;

    public function width($width): static
    {
        $this->width = $width;
        $this->prop('width', $width);
        return $this;
    }
}
