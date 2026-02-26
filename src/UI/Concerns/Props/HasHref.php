<?php

namespace Upsoftware\Svarium\UI\Concerns\Props;

trait HasHref
{
    public ?string $href = null;

    public function href(string $url): static
    {
        $this->href = $url;
        $this->prop('href', $url);
        return $this;
    }
}
