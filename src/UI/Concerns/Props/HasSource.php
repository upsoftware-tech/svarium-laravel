<?php

namespace Upsoftware\Svarium\UI\Concerns\Props;

trait HasSource
{
    public string $source;

    public function source(string $source): static
    {
        $this->source = $source;
        return $this;
    }
}
