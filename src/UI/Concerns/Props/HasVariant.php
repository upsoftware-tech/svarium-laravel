<?php

namespace Upsoftware\Svarium\UI\Concerns\Props;

trait HasVariant
{
    public string $variant;

    public function variant(string $variant): static
    {
        $this->variant = $variant;
        $this->prop('variant', $variant);

        return $this;
    }
}
