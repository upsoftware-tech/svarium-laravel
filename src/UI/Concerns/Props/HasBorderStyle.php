<?php

namespace Upsoftware\Svarium\UI\Concerns\Props;

trait HasBorderStyle
{
    public ?string $borderStyle = null;

    public function borderStyle(string $url): static
    {
        $this->borderStyle = $url;
        $this->prop('borderStyle', $url);
        return $this;
    }
}
