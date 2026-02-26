<?php

namespace Upsoftware\Svarium\UI\Concerns\Props;

use Upsoftware\Svarium\UI\Component;

trait HasIcon
{
    protected $icon = null;

    public function icon($icon): static
    {
        $this->icon = $icon;
        $this->prop('icon', $icon);
        return $this;
    }
}
